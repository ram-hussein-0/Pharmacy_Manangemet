<?php

namespace App\Services\Ai;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiAnalyticalExecutor
{
    public function execute(array $plan): array
    {
        if (($plan['answerable'] ?? false) !== true) {
            return ['queries' => [], 'calculations' => [], 'display_query' => null];
        }

        $results = [];
        $maxExecutionMs = max(250, min((int) config('llm.analytical_max_execution_ms', 3000), 10000));

        DB::statement('SET SESSION MAX_EXECUTION_TIME = '.(int) $maxExecutionMs);

        try {
            foreach ($plan['queries'] as $query) {
                $results[$query['id']] = $this->executeQuery($query);
            }
        } finally {
            DB::statement('SET SESSION MAX_EXECUTION_TIME = 0');
        }

        $calculations = $this->executeCalculations($plan['calculations'] ?? [], $results);

        return [
            'queries' => $results,
            'calculations' => $calculations,
            'display_query' => $plan['display_query'] ?? array_key_first($results),
        ];
    }

    private function executeQuery(array $query): array
    {
        $builder = DB::table($query['from']);

        foreach ($query['joins'] as $join) {
            if ($join['type'] === 'left') {
                $builder->leftJoin($join['table'], $join['left'], '=', $join['right']);
            } else {
                $builder->join($join['table'], $join['left'], '=', $join['right']);
            }
        }

        $selectSql = [];

        foreach ($query['select'] as $item) {
            $alias = $this->quoteName($item['alias']);

            if ($item['kind'] === 'column') {
                $selectSql[] = $this->quoteQualified($item['column']).' AS '.$alias;
                continue;
            }

            if ($item['kind'] === 'date_bucket') {
                $column = $this->quoteQualified($item['column']);
                $bucket = match ($item['unit']) {
                    'day' => 'DATE('.$column.')',
                    'week' => "DATE_FORMAT({$column}, '%x-W%v')",
                    'month' => "DATE_FORMAT({$column}, '%Y-%m')",
                    'year' => 'YEAR('.$column.')',
                    default => throw new RuntimeException('Unexpected validated date bucket.'),
                };
                $selectSql[] = $bucket.' AS '.$alias;
                continue;
            }

            $expression = $this->compileExpression($item['expression']);
            $function = $item['function'];

            $sql = match ($function) {
                'count' => 'COUNT('.$expression.')',
                'count_distinct' => 'COUNT(DISTINCT '.$expression.')',
                'sum' => 'COALESCE(SUM('.$expression.'), 0)',
                'avg' => 'AVG('.$expression.')',
                'min' => 'MIN('.$expression.')',
                'max' => 'MAX('.$expression.')',
                default => throw new RuntimeException('Unexpected validated aggregate.'),
            };

            $selectSql[] = $sql.' AS '.$alias;
        }

        $builder->selectRaw(implode(', ', $selectSql));

        foreach ($query['filters'] as $filter) {
            $this->applyFilter($builder, $filter);
        }

        if ($query['group_by'] !== []) {
            $builder->groupByRaw(implode(', ', array_map(
                fn (string $field): string => str_contains($field, '.') ? $this->quoteQualified($field) : $this->quoteName($field),
                $query['group_by'],
            )));
        }

        foreach ($query['order_by'] as $order) {
            $builder->orderBy($order['field'], $order['direction']);
        }

        return $builder
            ->limit((int) $query['limit'])
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    private function applyFilter(Builder $builder, array $filter): void
    {
        $column = $filter['column'];
        $operator = $filter['operator'];
        $value = $filter['value'];

        if ($operator === 'contains') {
            $builder->where($column, 'like', '%'.$this->resolveValue($value).'%');
            return;
        }

        if ($operator === 'in') {
            $builder->whereIn($column, array_map(fn ($entry) => $this->resolveValue($entry), $value));
            return;
        }

        if ($operator === 'between') {
            $builder->whereBetween($column, [
                $this->resolveValue($value[0]),
                $this->resolveValue($value[1]),
            ]);
            return;
        }

        $builder->where($column, $operator, $this->resolveValue($value));
    }

    private function resolveValue(mixed $value): mixed
    {
        if (is_array($value) && ($value['kind'] ?? null) === 'relative_date') {
            $date = Carbon::today()->addMonthsNoOverflow((int) ($value['offset_months'] ?? 0));
            $date = match ($value['anchor']) {
                'start_of_day' => $date->startOfDay(),
                'end_of_day' => $date->endOfDay(),
                'start_of_month' => $date->startOfMonth(),
                'end_of_month' => $date->endOfMonth(),
                'start_of_year' => $date->startOfYear(),
                'end_of_year' => $date->endOfYear(),
                default => $date,
            };
            $date = $date->addDays((int) ($value['offset_days'] ?? 0));

            return in_array($value['anchor'], ['start_of_day', 'end_of_day', 'start_of_month', 'end_of_month', 'start_of_year', 'end_of_year'], true)
                ? $date->format('Y-m-d H:i:s')
                : $date->toDateString();
        }

        if ($value === 'true') {
            return 1;
        }

        if ($value === 'false') {
            return 0;
        }

        return $value;
    }

    private function compileExpression(array $expression): string
    {
        return match ($expression['type']) {
            'column' => $this->quoteQualified($expression['column']),
            'number' => (string) (0 + $expression['value']),
            'binary' => '('.$this->compileExpression($expression['left']).' '.$expression['operator'].' '.$this->compileExpression($expression['right']).')',
            default => throw new RuntimeException('Unexpected validated expression.'),
        };
    }

    private function executeCalculations(array $calculations, array $queryResults): array
    {
        $computed = [];

        foreach ($calculations as $calculation) {
            $left = $this->operandValue($calculation['left'], $queryResults, $computed);
            $right = $this->operandValue($calculation['right'], $queryResults, $computed);

            $value = match ($calculation['operator']) {
                'add' => $left + $right,
                'subtract' => $left - $right,
                'multiply' => $left * $right,
                'divide' => abs($right) < 0.0000001 ? throw new RuntimeException('Division by zero in analytical calculation.') : $left / $right,
                default => throw new RuntimeException('Unexpected validated calculation operator.'),
            };

            $computed[$calculation['alias']] = $value;
        }

        return $computed;
    }

    private function operandValue(array $operand, array $queryResults, array $computed): float
    {
        if (isset($operand['query'], $operand['field'])) {
            $row = $queryResults[$operand['query']][0] ?? null;
            $value = is_array($row) ? ($row[$operand['field']] ?? null) : null;

            if (! is_numeric($value)) {
                throw new RuntimeException('Scalar calculation source did not produce a numeric first-row value.');
            }

            return (float) $value;
        }

        if (isset($operand['calculation'])) {
            $value = $computed[$operand['calculation']] ?? null;

            if (! is_numeric($value)) {
                throw new RuntimeException('Prior calculation value is unavailable.');
            }

            return (float) $value;
        }

        return (float) ($operand['number'] ?? 0);
    }

    private function quoteQualified(string $qualified): string
    {
        [$table, $column] = explode('.', $qualified, 2);

        return $this->quoteName($table).'.'.$this->quoteName($column);
    }

    private function quoteName(string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name) !== 1) {
            throw new RuntimeException('Unsafe identifier reached executor.');
        }

        return '`'.$name.'`';
    }
}

<?php

namespace App\Services\Ai;

class AiAnalyticalPlanValidator
{
    private const MAX_QUERIES = 4;
    private const MAX_JOINS = 6;
    private const MAX_SELECTS = 12;
    private const MAX_FILTERS = 12;
    private const MAX_GROUPS = 8;
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly AiSchemaCatalog $catalog,
        private readonly AiBusinessSemantics $semantics,
    ) {
    }

    public function validate(string $question, array $plan): array
    {
        $answerable = $plan['answerable'] ?? null;

        if (! is_bool($answerable)) {
            throw new AiPlanValidationException('Plan must contain boolean answerable.');
        }

        $reason = trim((string) ($plan['reason'] ?? ''));

        if ($answerable === false) {
            return [
                'answerable' => false,
                'reason' => $reason !== '' ? $reason : 'The current database does not provide enough evidence to answer safely.',
                'queries' => [],
                'calculations' => [],
                'display_query' => null,
            ];
        }

        $queries = $plan['queries'] ?? null;

        if (! is_array($queries) || $queries === [] || count($queries) > self::MAX_QUERIES) {
            throw new AiPlanValidationException('Answerable plans require between 1 and '.self::MAX_QUERIES.' queries.');
        }

        $validated = [];
        $queryAliases = [];

        foreach ($queries as $query) {
            if (! is_array($query)) {
                throw new AiPlanValidationException('Each query must be an object.');
            }

            $normalized = $this->validateQuery($question, $query);
            $id = $normalized['id'];

            if (isset($validated[$id])) {
                throw new AiPlanValidationException("Duplicate query id [{$id}].");
            }

            $validated[$id] = $normalized;
            $queryAliases[$id] = array_column($normalized['select'], 'alias');
        }

        $calculations = $this->validateCalculations($plan['calculations'] ?? [], $queryAliases);
        $displayQuery = $plan['display_query'] ?? array_key_first($validated);

        if ($displayQuery !== null && (! is_string($displayQuery) || ! isset($validated[$displayQuery]))) {
            throw new AiPlanValidationException('display_query must reference a validated query id.');
        }

        return [
            'answerable' => true,
            'reason' => $reason,
            'queries' => array_values($validated),
            'calculations' => $calculations,
            'display_query' => $displayQuery,
        ];
    }

    private function validateQuery(string $question, array $query): array
    {
        $id = (string) ($query['id'] ?? '');
        $from = (string) ($query['from'] ?? '');

        $this->assertName($id, 'query id');

        if (! $this->catalog->hasTable($from)) {
            throw new AiPlanValidationException("Unknown or protected table [{$from}].");
        }

        $joins = $query['joins'] ?? [];
        $select = $query['select'] ?? [];
        $filters = $query['filters'] ?? [];
        $groupBy = $query['group_by'] ?? [];
        $orderBy = $query['order_by'] ?? [];
        $limit = (int) ($query['limit'] ?? 50);

        if (! is_array($joins) || count($joins) > self::MAX_JOINS) {
            throw new AiPlanValidationException('Too many joins.');
        }

        if (! is_array($select) || $select === [] || count($select) > self::MAX_SELECTS) {
            throw new AiPlanValidationException('Each query needs a bounded select list.');
        }

        if (! is_array($filters) || count($filters) > self::MAX_FILTERS) {
            throw new AiPlanValidationException('Too many filters.');
        }

        if (! is_array($groupBy) || count($groupBy) > self::MAX_GROUPS) {
            throw new AiPlanValidationException('Too many group-by columns.');
        }

        if (! is_array($orderBy) || count($orderBy) > 4) {
            throw new AiPlanValidationException('Too many order-by fields.');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new AiPlanValidationException('Query limit must be between 1 and '.self::MAX_LIMIT.'.');
        }

        $availableTables = [$from => true];
        $parentExpansions = [];
        $validatedJoins = [];

        foreach ($joins as $join) {
            if (! is_array($join)) {
                throw new AiPlanValidationException('Join entries must be objects.');
            }

            $table = (string) ($join['table'] ?? '');
            $left = (string) ($join['left'] ?? '');
            $right = (string) ($join['right'] ?? '');
            $type = strtolower((string) ($join['type'] ?? 'inner'));

            if (! in_array($type, ['inner', 'left'], true)) {
                throw new AiPlanValidationException('Only inner and left joins are allowed.');
            }

            if (! $this->catalog->hasTable($table) || isset($availableTables[$table])) {
                throw new AiPlanValidationException("Join table [{$table}] is unknown, protected, or already joined.");
            }

            if (! $this->catalog->hasColumn($left) || ! $this->catalog->hasColumn($right)) {
                throw new AiPlanValidationException('Every join column must exist in the current safe schema catalog.');
            }

            [$leftTable] = $this->catalog->splitQualified($left);
            [$rightTable] = $this->catalog->splitQualified($right);

            // Do not use PHP's low-precedence `xor` operator in an assignment here.
            // Exactly one side of the FK equality must be the newly introduced table,
            // and the opposite side must already belong to the validated query graph.
            $newOnLeft = $leftTable === $table;
            $newOnRight = $rightTable === $table;

            if ($newOnLeft === $newOnRight) {
                throw new AiPlanValidationException('Each join must reference the declared new table on exactly one side.');
            }

            $existingTable = $newOnLeft ? $rightTable : $leftTable;

            if (! isset($availableTables[$existingTable])) {
                throw new AiPlanValidationException('Each join must connect the new table to the existing query graph.');
            }

            $relationship = $this->catalog->relationshipBetween($left, $right);

            if ($relationship === null) {
                throw new AiPlanValidationException("Join [{$left} = {$right}] is not backed by a current foreign key.");
            }

            $parentTable = $relationship['parent_table'];
            $parentExpansions[$parentTable] = ($parentExpansions[$parentTable] ?? 0) + 1;

            if ($parentExpansions[$parentTable] > 1) {
                throw new AiPlanValidationException("Fanout risk: table [{$parentTable}] participates in multiple child branches.");
            }

            $availableTables[$table] = true;
            $validatedJoins[] = compact('table', 'left', 'right', 'type');
        }

        $validatedSelect = [];
        $aliases = [];
        $groupableAliases = [];
        $groupRequirements = [];
        $hasAggregate = false;

        foreach ($select as $item) {
            if (! is_array($item)) {
                throw new AiPlanValidationException('Select entries must be objects.');
            }

            $kind = (string) ($item['kind'] ?? '');
            $alias = (string) ($item['alias'] ?? '');
            $this->assertName($alias, 'select alias');

            if (isset($aliases[$alias])) {
                throw new AiPlanValidationException("Duplicate select alias [{$alias}].");
            }

            $aliases[$alias] = true;

            if ($kind === 'column') {
                $column = (string) ($item['column'] ?? '');
                $this->assertColumnInQuery($column, $availableTables);
                $groupableAliases[$alias] = true;
                $groupRequirements[] = ['alias' => $alias, 'column' => $column];
                $validatedSelect[] = ['kind' => 'column', 'column' => $column, 'alias' => $alias];
                continue;
            }

            if ($kind === 'date_bucket') {
                $column = (string) ($item['column'] ?? '');
                $unit = strtolower((string) ($item['unit'] ?? ''));
                $this->assertColumnInQuery($column, $availableTables);

                if (! $this->catalog->isDateColumn($column)) {
                    throw new AiPlanValidationException("Date bucket column [{$column}] must be a date/datetime/timestamp.");
                }

                if (! in_array($unit, ['day', 'week', 'month', 'year'], true)) {
                    throw new AiPlanValidationException("Unsupported date bucket unit [{$unit}].");
                }

                $groupableAliases[$alias] = true;
                $groupRequirements[] = ['alias' => $alias, 'column' => null];
                $validatedSelect[] = ['kind' => 'date_bucket', 'column' => $column, 'unit' => $unit, 'alias' => $alias];
                continue;
            }

            if ($kind === 'aggregate') {
                $function = strtolower((string) ($item['function'] ?? ''));

                if (! in_array($function, ['count', 'count_distinct', 'sum', 'avg', 'min', 'max'], true)) {
                    throw new AiPlanValidationException("Unsupported aggregate [{$function}].");
                }

                // SUM and AVG require numeric inputs. COUNT/COUNT DISTINCT may
                // operate on any safe column, while MIN/MAX may validly target
                // dates or text as well as numbers. Binary expressions remain
                // numeric because validateExpression() enforces numeric operands.
                $requiresNumeric = in_array($function, ['sum', 'avg'], true);
                $expression = $this->validateExpression($item['expression'] ?? null, $availableTables, $requiresNumeric);
                $validatedSelect[] = [
                    'kind' => 'aggregate',
                    'function' => $function,
                    'expression' => $expression,
                    'alias' => $alias,
                ];
                $hasAggregate = true;
                continue;
            }

            throw new AiPlanValidationException("Unsupported select kind [{$kind}].");
        }

        $validatedGroups = [];

        foreach ($groupBy as $group) {
            $group = (string) $group;

            if (str_contains($group, '.')) {
                $this->assertColumnInQuery($group, $availableTables);
                $validatedGroups[] = $group;
                continue;
            }

            if (! isset($groupableAliases[$group])) {
                throw new AiPlanValidationException("Group field [{$group}] must be a selected non-aggregate alias or a qualified column.");
            }

            $validatedGroups[] = $group;
        }

        if ($hasAggregate) {
            foreach ($groupRequirements as $requirement) {
                $satisfied = in_array($requirement['alias'], $validatedGroups, true)
                    || ($requirement['column'] !== null && in_array($requirement['column'], $validatedGroups, true));

                if (! $satisfied) {
                    throw new AiPlanValidationException("Selected non-aggregate field [{$requirement['alias']}] must be grouped.");
                }
            }
        } elseif ($validatedGroups !== []) {
            throw new AiPlanValidationException('group_by is only allowed when aggregate selections are present.');
        }

        $validatedFilters = [];

        foreach ($filters as $filter) {
            $validatedFilters[] = $this->validateFilter($question, $filter, $availableTables);
        }

        $validatedOrder = [];

        foreach ($orderBy as $order) {
            if (! is_array($order)) {
                throw new AiPlanValidationException('Order entries must be objects.');
            }

            $field = (string) ($order['field'] ?? '');
            $direction = strtolower((string) ($order['direction'] ?? 'asc'));

            if (! isset($aliases[$field])) {
                throw new AiPlanValidationException("Order field [{$field}] must be a select alias.");
            }

            if (! in_array($direction, ['asc', 'desc'], true)) {
                throw new AiPlanValidationException('Order direction must be asc or desc.');
            }

            $validatedOrder[] = compact('field', 'direction');
        }

        return [
            'id' => $id,
            'from' => $from,
            'joins' => $validatedJoins,
            'select' => $validatedSelect,
            'filters' => $validatedFilters,
            'group_by' => $validatedGroups,
            'order_by' => $validatedOrder,
            'limit' => $limit,
        ];
    }

    private function validateExpression(mixed $expression, array $availableTables, bool $requireNumeric): array
    {
        if (! is_array($expression)) {
            throw new AiPlanValidationException('Aggregate expression must be an expression object.');
        }

        $type = (string) ($expression['type'] ?? '');

        if ($type === 'column') {
            $column = (string) ($expression['column'] ?? '');
            $this->assertColumnInQuery($column, $availableTables);

            if ($requireNumeric && ! $this->catalog->isNumericColumn($column)) {
                throw new AiPlanValidationException("Arithmetic aggregate column [{$column}] must be numeric.");
            }

            return ['type' => 'column', 'column' => $column];
        }

        if ($type === 'number') {
            $value = $expression['value'] ?? null;

            if (! is_int($value) && ! is_float($value)) {
                throw new AiPlanValidationException('Number expressions require a numeric value.');
            }

            return ['type' => 'number', 'value' => $value];
        }

        if ($type === 'binary') {
            $operator = (string) ($expression['operator'] ?? '');

            if (! in_array($operator, ['+', '-', '*', '/'], true)) {
                throw new AiPlanValidationException("Unsupported arithmetic operator [{$operator}].");
            }

            return [
                'type' => 'binary',
                'operator' => $operator,
                'left' => $this->validateExpression($expression['left'] ?? null, $availableTables, true),
                'right' => $this->validateExpression($expression['right'] ?? null, $availableTables, true),
            ];
        }

        throw new AiPlanValidationException("Unsupported expression type [{$type}].");
    }

    private function validateFilter(string $question, mixed $filter, array $availableTables): array
    {
        if (! is_array($filter)) {
            throw new AiPlanValidationException('Filter entries must be objects.');
        }

        $column = (string) ($filter['column'] ?? '');
        $operator = strtolower((string) ($filter['operator'] ?? '='));
        $source = strtolower((string) ($filter['source'] ?? 'user'));
        $value = $filter['value'] ?? null;

        $this->assertColumnInQuery($column, $availableTables);

        if (! in_array($operator, ['=', '!=', '>', '>=', '<', '<=', 'contains', 'in', 'between'], true)) {
            throw new AiPlanValidationException("Unsupported filter operator [{$operator}].");
        }

        if ($operator === 'in') {
            if (! is_array($value) || $value === [] || count($value) > 20) {
                throw new AiPlanValidationException('IN filters require 1-20 values.');
            }

            foreach ($value as $entry) {
                $this->validateLiteralEvidence($question, $column, $entry, $source);
            }
        } elseif ($operator === 'between') {
            if (! is_array($value) || count($value) !== 2) {
                throw new AiPlanValidationException('BETWEEN filters require exactly two values.');
            }

            foreach ($value as $entry) {
                $this->validateLiteralEvidence($question, $column, $entry, $source);
            }
        } else {
            $this->validateLiteralEvidence($question, $column, $value, $source);
        }

        return compact('column', 'operator', 'source', 'value');
    }

    private function validateLiteralEvidence(string $question, string $column, mixed $value, string $source): void
    {
        if (is_array($value) && ($value['kind'] ?? null) === 'relative_date') {
            $anchor = (string) ($value['anchor'] ?? '');
            $offsetDays = $value['offset_days'] ?? 0;
            $offsetMonths = $value['offset_months'] ?? 0;

            if (! $this->catalog->isDateColumn($column)
                || ! in_array($anchor, ['today', 'start_of_day', 'end_of_day', 'start_of_month', 'end_of_month', 'start_of_year', 'end_of_year'], true)
                || ! is_int($offsetDays) || $offsetDays < -3650 || $offsetDays > 3650
                || ! is_int($offsetMonths) || $offsetMonths < -120 || $offsetMonths > 120) {
                throw new AiPlanValidationException('Invalid relative_date literal.');
            }

            return;
        }

        if (! is_scalar($value) && $value !== null) {
            throw new AiPlanValidationException('Filter literals must be scalar or relative_date objects.');
        }

        if ($source === 'semantic') {
            if (! $this->semantics->isTrustedLiteral($column, $value)) {
                throw new AiPlanValidationException("Literal for [{$column}] is not a trusted business semantic.");
            }

            return;
        }

        if ($source !== 'user') {
            throw new AiPlanValidationException('Literal source must be user or semantic.');
        }

        $needle = $this->normalizeEvidence((string) $value);
        $haystack = $this->normalizeEvidence($question);

        if ($needle === '' || ! str_contains($haystack, $needle)) {
            throw new AiPlanValidationException("Literal [{$value}] is not grounded in the user question.");
        }
    }

    private function validateCalculations(mixed $calculations, array $queryAliases): array
    {
        if (! is_array($calculations) || count($calculations) > 8) {
            throw new AiPlanValidationException('calculations must be an array with at most 8 entries.');
        }

        $validated = [];
        $knownCalculations = [];

        foreach ($calculations as $calculation) {
            if (! is_array($calculation)) {
                throw new AiPlanValidationException('Calculation entries must be objects.');
            }

            $alias = (string) ($calculation['alias'] ?? '');
            $operator = strtolower((string) ($calculation['operator'] ?? ''));
            $this->assertName($alias, 'calculation alias');

            if (isset($knownCalculations[$alias])) {
                throw new AiPlanValidationException("Duplicate calculation alias [{$alias}].");
            }

            if (! in_array($operator, ['add', 'subtract', 'multiply', 'divide'], true)) {
                throw new AiPlanValidationException("Unsupported calculation operator [{$operator}].");
            }

            $left = $this->validateOperand($calculation['left'] ?? null, $queryAliases, $knownCalculations);
            $right = $this->validateOperand($calculation['right'] ?? null, $queryAliases, $knownCalculations);

            $validated[] = compact('alias', 'operator', 'left', 'right');
            $knownCalculations[$alias] = true;
        }

        return $validated;
    }

    private function validateOperand(mixed $operand, array $queryAliases, array $knownCalculations): array
    {
        if (! is_array($operand)) {
            throw new AiPlanValidationException('Calculation operands must be objects.');
        }

        if (isset($operand['query'], $operand['field'])) {
            $query = (string) $operand['query'];
            $field = (string) $operand['field'];

            if (! isset($queryAliases[$query]) || ! in_array($field, $queryAliases[$query], true)) {
                throw new AiPlanValidationException("Unknown calculation query field [{$query}.{$field}].");
            }

            return ['query' => $query, 'field' => $field];
        }

        if (isset($operand['calculation'])) {
            $name = (string) $operand['calculation'];

            if (! isset($knownCalculations[$name])) {
                throw new AiPlanValidationException("Unknown prior calculation [{$name}].");
            }

            return ['calculation' => $name];
        }

        if (array_key_exists('number', $operand) && (is_int($operand['number']) || is_float($operand['number']))) {
            return ['number' => $operand['number']];
        }

        throw new AiPlanValidationException('Unsupported calculation operand.');
    }

    private function assertColumnInQuery(string $column, array $availableTables): void
    {
        if (! $this->catalog->hasColumn($column)) {
            throw new AiPlanValidationException("Unknown or protected column [{$column}].");
        }

        [$table] = $this->catalog->splitQualified($column);

        if (! isset($availableTables[$table])) {
            throw new AiPlanValidationException("Column [{$column}] references a table not joined into the query.");
        }
    }

    private function assertName(string $value, string $label): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value) !== 1) {
            throw new AiPlanValidationException("Invalid {$label} [{$value}].");
        }
    }

    private function normalizeEvidence(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[\x{2018}\x{2019}\x{201C}\x{201D}"\']+/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}

<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiSchemaCatalog
{
    private ?array $cache = null;

    /** @return array{database:string,tables:array<string,array>,relationships:array<int,array>} */
    public function catalog(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('The analytical catalog currently requires MySQL metadata.');
        }

        $database = $connection->getDatabaseName();
        $tableRows = DB::select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [$database, 'BASE TABLE'],
        );

        $tables = [];

        foreach ($tableRows as $tableRow) {
            $table = (string) $tableRow->TABLE_NAME;

            if ($this->isExcludedTable($table)) {
                continue;
            }

            $columnRows = DB::select(
                'SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                [$database, $table],
            );

            $columns = [];

            foreach ($columnRows as $columnRow) {
                $column = (string) $columnRow->COLUMN_NAME;

                if ($this->isSensitiveColumn($table, $column)) {
                    continue;
                }

                $columns[$column] = [
                    'type' => (string) $columnRow->COLUMN_TYPE,
                    'data_type' => (string) $columnRow->DATA_TYPE,
                    'nullable' => (string) $columnRow->IS_NULLABLE === 'YES',
                    'key' => (string) ($columnRow->COLUMN_KEY ?: ''),
                ];
            }

            if ($columns !== []) {
                $tables[$table] = ['columns' => $columns];
            }
        }

        $relationships = [];
        $fkRows = DB::select(
            'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION',
            [$database],
        );

        foreach ($fkRows as $fk) {
            $childTable = (string) $fk->TABLE_NAME;
            $childColumn = (string) $fk->COLUMN_NAME;
            $parentTable = (string) $fk->REFERENCED_TABLE_NAME;
            $parentColumn = (string) $fk->REFERENCED_COLUMN_NAME;

            if (! isset($tables[$childTable], $tables[$parentTable])) {
                continue;
            }

            if (! isset($tables[$childTable]['columns'][$childColumn], $tables[$parentTable]['columns'][$parentColumn])) {
                continue;
            }

            $relationships[] = [
                'child_table' => $childTable,
                'child_column' => $childColumn,
                'parent_table' => $parentTable,
                'parent_column' => $parentColumn,
            ];
        }

        return $this->cache = [
            'database' => $database,
            'tables' => $tables,
            'relationships' => $relationships,
        ];
    }

    public function promptJson(): string
    {
        return json_encode($this->catalog(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    public function hasTable(string $table): bool
    {
        return isset($this->catalog()['tables'][$table]);
    }

    public function hasColumn(string $qualified): bool
    {
        [$table, $column] = $this->splitQualified($qualified);

        return isset($this->catalog()['tables'][$table]['columns'][$column]);
    }

    public function columnMeta(string $qualified): array
    {
        [$table, $column] = $this->splitQualified($qualified);
        $meta = $this->catalog()['tables'][$table]['columns'][$column] ?? null;

        if (! is_array($meta)) {
            throw new AiPlanValidationException("Unknown or protected column [{$qualified}].");
        }

        return $meta;
    }

    public function isNumericColumn(string $qualified): bool
    {
        $type = strtolower((string) ($this->columnMeta($qualified)['data_type'] ?? ''));

        return in_array($type, [
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint',
            'decimal', 'numeric', 'float', 'double', 'real', 'bit',
        ], true);
    }

    public function isDateColumn(string $qualified): bool
    {
        $type = strtolower((string) ($this->columnMeta($qualified)['data_type'] ?? ''));

        return in_array($type, ['date', 'datetime', 'timestamp'], true);
    }

    public function relationshipBetween(string $left, string $right): ?array
    {
        foreach ($this->catalog()['relationships'] as $relationship) {
            $child = $relationship['child_table'].'.'.$relationship['child_column'];
            $parent = $relationship['parent_table'].'.'.$relationship['parent_column'];

            if (($left === $child && $right === $parent) || ($left === $parent && $right === $child)) {
                return $relationship;
            }
        }

        return null;
    }

    /** @return array{0:string,1:string} */
    public function splitQualified(string $qualified): array
    {
        if (preg_match('/^([a-z][a-z0-9_]*)\.([a-z][a-z0-9_]*)$/', $qualified, $matches) !== 1) {
            throw new AiPlanValidationException("Invalid qualified column [{$qualified}].");
        }

        return [$matches[1], $matches[2]];
    }

    private function isExcludedTable(string $table): bool
    {
        if (in_array($table, [
            'personal_access_tokens',
            'password_reset_tokens',
            'sessions',
            'migrations',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
        ], true)) {
            return true;
        }

        return str_starts_with($table, 'telescope_') || str_starts_with($table, 'pulse_');
    }

    private function isSensitiveColumn(string $table, string $column): bool
    {
        $lower = strtolower($column);

        foreach (['password', 'token', 'secret', 'api_key', 'remember_token', 'payload'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        if ($table === 'users' && in_array($column, ['email', 'phone'], true)) {
            return true;
        }

        if ($table === 'sale_invoices' && in_array($column, ['customer_name', 'customer_phone'], true)) {
            return true;
        }

        return false;
    }
}

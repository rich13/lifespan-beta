<?php

namespace Tests\Hygiene;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseHygieneTest extends TestCase
{
    /**
     * Tables that should be excluded from our convention checks
     */
    private array $excludedTables = [
        'failed_jobs',
        'jobs',
        'job_batches',
        'migrations',
        'password_reset_tokens',
        'personal_access_tokens',
        'sessions',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring'
    ];

    /**
     * Columns that are allowed to use string type for IDs
     */
    private array $allowedStringIds = [
        'connection_types.id',
        'connection_types.name',
    ];

    /**
     * Valid UUID type names based on database driver
     */
    private array $uuidTypes = ['uuid', 'char', 'char(36)', 'string', 'guid'];

    /**
     * Valid ID types for ID columns
     */
    private array $validIdTypes = ['uuid', 'char', 'char(36)', 'string', 'varchar', 'guid'];

    /**
     * Test that all tables have timestamps
     */
    public function test_tables_have_timestamps(): void
    {
        $violations = [];

        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();
        
        foreach ($tables as $table) {
            if (in_array($table, $this->excludedTables)) {
                continue;
            }

            $hasCreatedAt = Schema::hasColumn($table, 'created_at');
            $hasUpdatedAt = Schema::hasColumn($table, 'updated_at');

            if (!$hasCreatedAt || !$hasUpdatedAt) {
                $violations[] = $table;
            }
        }

        $this->assertEmpty($violations, 'Tables missing timestamps: ' . implode(', ', $violations));
    }

    /**
     * Test that foreign keys follow naming convention
     */
    public function test_foreign_key_naming_convention(): void
    {
        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();
        $violations = [];

        foreach ($tables as $table) {
            if (in_array($table, $this->excludedTables)) {
                continue;
            }

            $foreignKeys = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableForeignKeys($table);

            foreach ($foreignKeys as $foreignKey) {
                $columnNames = $foreignKey->getLocalColumns();
                foreach ($columnNames as $columnName) {
                    // Check if foreign key columns end with _id or are allowed string IDs
                    if (!str_ends_with($columnName, '_id') && 
                        !in_array("{$table}.{$columnName}", $this->allowedStringIds)) {
                        $violations[] = "{$table}.{$columnName}";
                    }
                }
            }
        }

        $this->assertEmpty($violations, 'Foreign keys not following naming convention: ' . implode(', ', $violations));
    }

    /**
     * Test that all foreign keys have indexes
     */
    public function test_foreign_keys_have_indexes(): void
    {
        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();
        $violations = [];

        foreach ($tables as $table) {
            if (in_array($table, $this->excludedTables)) {
                continue;
            }

            $foreignKeys = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableForeignKeys($table);
            
            $indexes = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableIndexes($table);

            foreach ($foreignKeys as $foreignKey) {
                $columns = $foreignKey->getLocalColumns();
                $hasIndex = false;

                foreach ($indexes as $index) {
                    if ($index->getColumns() === $columns) {
                        $hasIndex = true;
                        break;
                    }
                }

                if (!$hasIndex) {
                    $violations[] = "{$table}." . implode(',', $columns);
                }
            }
        }

        $this->assertEmpty($violations, 'Foreign keys missing indexes: ' . implode(', ', $violations));
    }

    /**
     * Test that ID columns use appropriate types (UUID or string for specific cases)
     */
    public function test_id_columns_have_correct_types(): void
    {
        $violations = [];
        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();

        foreach ($tables as $tableName) {
            if (in_array($tableName, $this->excludedTables)) {
                continue;
            }

            $columns = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableColumns($tableName);

            foreach ($columns as $column) {
                if (str_ends_with($column->getName(), '_id') || $column->getName() === 'id') {
                    $columnKey = "{$tableName}.{$column->getName()}";
                    
                    if (in_array($columnKey, $this->allowedStringIds)) {
                        continue;
                    }

                    $typeName = $column->getType()->getName();
                    if (!in_array($typeName, $this->validIdTypes)) {
                        $violations[] = "{$columnKey} has type {$typeName} but should be UUID type";
                    }
                }
            }
        }

        $this->assertEmpty($violations, 'ID columns with incorrect types: ' . implode(', ', $violations));
    }

    /**
     * Test that soft deletable models have proper columns
     */
    public function test_soft_delete_implementation(): void
    {
        $softDeleteTables = [
            'spans',
            'users',
            // Add other tables that should use soft deletes
        ];

        $violations = [];

        foreach ($softDeleteTables as $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) {
                $violations[] = $table;
            }
        }

        $this->assertEmpty($violations, 'Tables missing soft delete column: ' . implode(', ', $violations));
    }
} 
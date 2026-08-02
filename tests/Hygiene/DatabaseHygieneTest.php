<?php

namespace Tests\Hygiene;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseHygieneTest extends TestCase
{

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
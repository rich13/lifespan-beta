<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing trigger if it exists
        DB::statement('DROP TRIGGER IF EXISTS update_family_connection_dates ON spans;');
        DB::statement('DROP FUNCTION IF EXISTS update_family_connection_dates;');

        // Create the trigger function
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION update_family_connection_dates()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Only proceed if dates have changed
                IF (
                    NEW.start_year IS DISTINCT FROM OLD.start_year OR
                    NEW.start_month IS DISTINCT FROM OLD.start_month OR
                    NEW.start_day IS DISTINCT FROM OLD.start_day OR
                    NEW.end_year IS DISTINCT FROM OLD.end_year OR
                    NEW.end_month IS DISTINCT FROM OLD.end_month OR
                    NEW.end_day IS DISTINCT FROM OLD.end_day
                ) THEN
                    -- Update connection spans where this span is the child
                    UPDATE spans
                    SET 
                        start_year = NEW.start_year,
                        start_month = NEW.start_month,
                        start_day = NEW.start_day,
                        start_precision = NEW.start_precision
                    FROM connections
                    WHERE 
                        spans.id = connections.connection_span_id
                        AND connections.child_id = NEW.id
                        AND connections.type_id = 'family';

                    -- Update connection spans where this span is the parent and has an end date
                    -- (only update end date if parent dies before child)
                    IF NEW.end_year IS NOT NULL THEN
                        UPDATE spans
                        SET 
                            end_year = NEW.end_year,
                            end_month = NEW.end_month,
                            end_day = NEW.end_day,
                            end_precision = NEW.end_precision
                        FROM connections c1
                        WHERE 
                            spans.id = c1.connection_span_id
                            AND c1.parent_id = NEW.id
                            AND c1.type_id = 'family'
                            AND (
                                -- Only set parent's death as end date if child is still alive
                                -- or died after parent
                                SELECT COUNT(*)
                                FROM connections c2
                                JOIN spans child ON child.id = c2.child_id
                                WHERE 
                                    c2.id = c1.id
                                    AND (
                                        child.end_year IS NULL
                                        OR child.end_year > NEW.end_year
                                        OR (
                                            child.end_year = NEW.end_year
                                            AND (
                                                child.end_month IS NULL
                                                OR child.end_month > NEW.end_month
                                                OR (
                                                    child.end_month = NEW.end_month
                                                    AND (
                                                        child.end_day IS NULL
                                                        OR child.end_day > NEW.end_day
                                                    )
                                                )
                                            )
                                        )
                                    )
                            ) > 0;
                    END IF;

                    -- Update connection spans where this span is the child and has an end date
                    -- (only update end date if child dies before parent)
                    IF NEW.end_year IS NOT NULL THEN
                        UPDATE spans
                        SET 
                            end_year = NEW.end_year,
                            end_month = NEW.end_month,
                            end_day = NEW.end_day,
                            end_precision = NEW.end_precision
                        FROM connections c1
                        WHERE 
                            spans.id = c1.connection_span_id
                            AND c1.child_id = NEW.id
                            AND c1.type_id = 'family'
                            AND (
                                -- Only set child's death as end date if parent is still alive
                                -- or died after child
                                SELECT COUNT(*)
                                FROM connections c2
                                JOIN spans parent ON parent.id = c2.parent_id
                                WHERE 
                                    c2.id = c1.id
                                    AND (
                                        parent.end_year IS NULL
                                        OR parent.end_year > NEW.end_year
                                        OR (
                                            parent.end_year = NEW.end_year
                                            AND (
                                                parent.end_month IS NULL
                                                OR parent.end_month > NEW.end_month
                                                OR (
                                                    parent.end_month = NEW.end_month
                                                    AND (
                                                        parent.end_day IS NULL
                                                        OR parent.end_day > NEW.end_day
                                                    )
                                                )
                                            )
                                        )
                                    )
                            ) > 0;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // Create the trigger
        DB::statement('
            CREATE TRIGGER update_family_connection_dates
            AFTER UPDATE ON spans
            FOR EACH ROW
            EXECUTE FUNCTION update_family_connection_dates();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS update_family_connection_dates ON spans;');
        DB::statement('DROP FUNCTION IF EXISTS update_family_connection_dates;');
    }
}; 
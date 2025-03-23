<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing triggers and functions
        DB::statement('DROP TRIGGER IF EXISTS enforce_temporal_constraint ON connections;');
        DB::statement('DROP FUNCTION IF EXISTS check_temporal_constraint;');

        // Drop the temporal_constraints table and its dependencies
        Schema::dropIfExists('temporal_constraints');
        Schema::dropIfExists('validation_monitoring');

        // Add constraint_type column if it doesn't exist
        if (!Schema::hasColumn('connection_types', 'constraint_type')) {
            Schema::table('connection_types', function (Blueprint $table) {
                $table->string('constraint_type')->default('single');
            });
        }

        // Create a simpler trigger function
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_temporal_constraint()
            RETURNS TRIGGER AS $$
            DECLARE
                connection_span RECORD;
                constraint_type TEXT;
                is_placeholder BOOLEAN;
            BEGIN
                -- Get the connection span record
                SELECT * INTO connection_span FROM spans WHERE id = NEW.connection_span_id;

                -- Ensure the connection span exists
                IF connection_span IS NULL THEN
                    RAISE EXCEPTION 'Connection span % not found', NEW.connection_span_id;
                END IF;

                -- Get the constraint type directly from connection_types
                SELECT ct.constraint_type INTO constraint_type 
                FROM connection_types ct
                WHERE ct.type = NEW.type_id;

                -- Ensure the connection type exists
                IF constraint_type IS NULL THEN
                    RAISE EXCEPTION 'Connection type % not found', NEW.type_id;
                END IF;

                -- Check if this is a placeholder (all date fields null)
                is_placeholder := connection_span.start_year IS NULL AND 
                                connection_span.start_month IS NULL AND 
                                connection_span.start_day IS NULL AND 
                                connection_span.end_year IS NULL AND 
                                connection_span.end_month IS NULL AND 
                                connection_span.end_day IS NULL;

                -- If not a placeholder, validate date precision hierarchy
                IF NOT is_placeholder THEN
                    -- Start date validation
                    IF connection_span.start_year IS NULL THEN
                        RAISE EXCEPTION 'Start year is required for non-placeholder spans';
                    END IF;

                    IF connection_span.start_day IS NOT NULL AND connection_span.start_month IS NULL THEN
                        RAISE EXCEPTION 'Start month is required when start day is provided';
                    END IF;

                    -- End date validation (if provided)
                    IF connection_span.end_day IS NOT NULL AND connection_span.end_month IS NULL THEN
                        RAISE EXCEPTION 'End month is required when end day is provided';
                    END IF;

                    IF connection_span.end_month IS NOT NULL AND connection_span.end_year IS NULL THEN
                        RAISE EXCEPTION 'End year is required when end month is provided';
                    END IF;

                    -- Validate date ranges
                    IF connection_span.start_month IS NOT NULL AND (connection_span.start_month < 1 OR connection_span.start_month > 12) THEN
                        RAISE EXCEPTION 'Start month must be between 1 and 12';
                    END IF;

                    IF connection_span.start_day IS NOT NULL AND (connection_span.start_day < 1 OR connection_span.start_day > 31) THEN
                        RAISE EXCEPTION 'Start day must be between 1 and 31';
                    END IF;

                    IF connection_span.end_year IS NOT NULL THEN
                        IF connection_span.end_month IS NOT NULL AND (connection_span.end_month < 1 OR connection_span.end_month > 12) THEN
                            RAISE EXCEPTION 'End month must be between 1 and 12';
                        END IF;

                        IF connection_span.end_day IS NOT NULL AND (connection_span.end_day < 1 OR connection_span.end_day > 31) THEN
                            RAISE EXCEPTION 'End day must be between 1 and 31';
                        END IF;

                        -- Compare dates at the appropriate precision level
                        IF connection_span.end_year < connection_span.start_year THEN
                            RAISE EXCEPTION 'End date cannot be before start date';
                        END IF;

                        IF connection_span.end_year = connection_span.start_year AND 
                           connection_span.end_month IS NOT NULL AND 
                           connection_span.start_month IS NOT NULL AND
                           connection_span.end_month < connection_span.start_month THEN
                            RAISE EXCEPTION 'End date cannot be before start date';
                        END IF;

                        IF connection_span.end_year = connection_span.start_year AND 
                           connection_span.end_month = connection_span.start_month AND 
                           connection_span.end_day IS NOT NULL AND
                           connection_span.start_day IS NOT NULL AND
                           connection_span.end_day < connection_span.start_day THEN
                            RAISE EXCEPTION 'End date cannot be before start date';
                        END IF;
                    END IF;
                END IF;

                -- Apply constraint-specific validation
                IF constraint_type = 'single' THEN
                    IF EXISTS (
                        SELECT 1 FROM connections
                        WHERE parent_id = NEW.parent_id
                        AND child_id = NEW.child_id
                        AND type_id = NEW.type_id
                        AND id IS DISTINCT FROM NEW.id
                    ) THEN
                        RAISE EXCEPTION 'Only one connection of this type is allowed between these spans';
                    END IF;
                ELSIF constraint_type = 'non_overlapping' AND NOT is_placeholder THEN
                    -- Only check overlaps for non-placeholder spans
                    -- Check for overlapping dates with existing connections
                    IF EXISTS (
                        SELECT 1 FROM connections c
                        JOIN spans s ON s.id = c.connection_span_id
                        WHERE c.parent_id = NEW.parent_id
                        AND c.child_id = NEW.child_id
                        AND c.type_id = NEW.type_id
                        AND c.id IS DISTINCT FROM NEW.id
                        AND NOT (
                            -- s is the existing connection span
                            -- connection_span is the new span
                            -- No overlap if:
                            -- 1. New span ends before existing span starts
                            -- 2. New span starts after existing span ends
                            (connection_span.end_year IS NOT NULL AND s.start_year IS NOT NULL AND
                             (connection_span.end_year < s.start_year OR
                              (connection_span.end_year = s.start_year AND
                               connection_span.end_month IS NOT NULL AND s.start_month IS NOT NULL AND
                               connection_span.end_month < s.start_month) OR
                              (connection_span.end_year = s.start_year AND
                               connection_span.end_month = s.start_month AND
                               connection_span.end_day IS NOT NULL AND s.start_day IS NOT NULL AND
                               connection_span.end_day < s.start_day)))
                            OR
                            (s.end_year IS NOT NULL AND connection_span.start_year IS NOT NULL AND
                             (s.end_year < connection_span.start_year OR
                              (s.end_year = connection_span.start_year AND
                               s.end_month IS NOT NULL AND connection_span.start_month IS NOT NULL AND
                               s.end_month < connection_span.start_month) OR
                              (s.end_year = connection_span.start_year AND
                               s.end_month = connection_span.start_month AND
                               s.end_day IS NOT NULL AND connection_span.start_day IS NOT NULL AND
                               s.end_day < connection_span.start_day)))
                        )
                    ) THEN
                        RAISE EXCEPTION 'Connection dates overlap with an existing connection';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // Create the trigger
        DB::statement('
            CREATE TRIGGER enforce_temporal_constraint
            BEFORE INSERT OR UPDATE ON connections
            FOR EACH ROW
            EXECUTE FUNCTION check_temporal_constraint();
        ');

        // Update existing connection types to use the correct constraint type
        DB::table('connection_types')
            ->whereIn('type', ['employment', 'residence', 'attendance', 'ownership', 'membership', 'travel', 'participation', 'education'])
            ->update(['constraint_type' => 'non_overlapping']);

        DB::table('connection_types')
            ->whereIn('type', ['family', 'relationship'])
            ->update(['constraint_type' => 'single']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the trigger and function
        DB::statement('DROP TRIGGER IF EXISTS enforce_temporal_constraint ON connections;');
        DB::statement('DROP FUNCTION IF EXISTS check_temporal_constraint;');

        // Remove the constraint_type column if we added it
        if (Schema::hasColumn('connection_types', 'constraint_type')) {
            Schema::table('connection_types', function (Blueprint $table) {
                $table->dropColumn('constraint_type');
            });
        }
    }
};

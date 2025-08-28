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
        // Add a check constraint that only applies to new/updated records
        // This constraint will prevent future invalid date ranges but won't affect existing data
        DB::statement("
            ALTER TABLE spans 
            ADD CONSTRAINT check_span_temporal_constraint 
            CHECK (
                -- Only apply constraint when both start and end years are provided
                (start_year IS NULL OR end_year IS NULL) OR
                -- And when both are provided, ensure end_year >= start_year
                (start_year IS NOT NULL AND end_year IS NOT NULL AND end_year >= start_year)
            )
        ");

        \Log::info('Added temporal constraint to spans table', [
            'migration' => 'add_temporal_constraint_to_spans_table',
            'constraint' => 'check_span_temporal_constraint'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE spans 
            DROP CONSTRAINT IF EXISTS check_span_temporal_constraint
        ");

        \Log::info('Removed temporal constraint from spans table', [
            'migration' => 'add_temporal_constraint_to_spans_table'
        ]);
    }
};

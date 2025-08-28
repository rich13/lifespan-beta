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
        // Fix blue plaque spans that have invalid date ranges
        // These are plaques where end_year < start_year, which is impossible
        // Set their end dates to null since plaques don't have end dates unless removed
        
        $fixedCount = DB::table('spans')
            ->where('type_id', 'thing')
            ->whereRaw('metadata->>\'subtype\' = \'plaque\'')
            ->whereNotNull('start_year')
            ->whereNotNull('end_year')
            ->whereRaw('end_year < start_year')
            ->update([
                'end_year' => null,
                'end_month' => null,
                'end_day' => null,
                'updated_at' => now()
            ]);

        // Log the fix
        \Log::info('Fixed blue plaque date ranges', [
            'fixed_count' => $fixedCount,
            'migration' => 'fix_blue_plaque_date_ranges'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We can't easily reverse this since we don't know what the original end dates were
        // The original data was invalid anyway, so this migration is essentially irreversible
        \Log::warning('Cannot reverse blue plaque date range fix - original data was invalid');
    }
};

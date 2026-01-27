<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Retrospectively updates span states based on date presence:
     * - Placeholder spans with dates → draft
     * - Draft spans without dates → placeholder
     * - Complete spans are left untouched
     * - Timeless spans are skipped
     */
    public function up(): void
    {
        // Get timeless span type IDs
        $timelessTypes = DB::table('span_types')
            ->where('metadata->>timeless', 'true')
            ->pluck('type_id')
            ->toArray();

        // Update placeholder spans that have dates → draft
        // (excluding timeless types and spans marked timeless in metadata)
        $placeholderToDraftCount = DB::table('spans')
            ->where('state', 'placeholder')
            ->where(function($query) {
                $query->whereNotNull('start_year')
                      ->orWhereNotNull('end_year');
            })
            ->whereNotIn('type_id', $timelessTypes)
            ->where(function($query) {
                $query->whereRaw("metadata->>'timeless' != 'true'")
                      ->orWhereNull('metadata->>timeless');
            })
            ->update(['state' => 'draft']);

        // Update draft spans that have no dates → placeholder
        // (excluding timeless types and spans marked timeless in metadata)
        $draftToPlaceholderCount = DB::table('spans')
            ->where('state', 'draft')
            ->whereNull('start_year')
            ->whereNull('end_year')
            ->whereNotIn('type_id', $timelessTypes)
            ->where(function($query) {
                $query->whereRaw("metadata->>'timeless' != 'true'")
                      ->orWhereNull('metadata->>timeless');
            })
            ->update(['state' => 'placeholder']);

        // Log the results
        Log::info('Migration: Retrospectively fixed span states based on dates', [
            'placeholder_to_draft' => $placeholderToDraftCount,
            'draft_to_placeholder' => $draftToPlaceholderCount,
            'timeless_types_skipped' => count($timelessTypes),
        ]);
    }

    /**
     * Reverse the migrations.
     * 
     * Note: We cannot reliably restore the original states since we don't
     * know what they were before. This migration is effectively one-way.
     */
    public function down(): void
    {
        // This migration is non-destructive in the down direction
        // We cannot reliably restore original states without additional tracking
        Log::info('Migration rollback: Span state retrospective fix cannot be reversed');
    }
};

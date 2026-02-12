<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds the 'series' and 'episode' subtypes to the 'thing' span type
     * to support Programme = brand; relate with contains (programme → series, programme → episode, series → episode).
     */
    public function up(): void
    {
        // Get the current thing span type
        $thingType = DB::table('span_types')->where('type_id', 'thing')->first();

        if (!$thingType) {
            throw new Exception('Thing span type not found. Run base migrations first.');
        }

        // Decode current metadata
        $metadata = json_decode($thingType->metadata, true);

        // Add 'series' and 'episode' to the subtype options if they don't exist
        $subtypeOptions = $metadata['schema']['subtype']['options'] ?? [];
        foreach (['series', 'episode'] as $subtype) {
            if (!in_array($subtype, $subtypeOptions)) {
                $subtypeOptions[] = $subtype;
            }
        }
        $metadata['schema']['subtype']['options'] = $subtypeOptions;

        // Update the span type
        DB::table('span_types')
            ->where('type_id', 'thing')
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now()
            ]);

        // Invalidate cached subtype options so ThingCapability picks up the new subtypes
        Cache::forget('span_type_thing_subtype_options');
    }

    /**
     * Reverse the migrations.
     *
     * Remove the 'series' and 'episode' subtypes from thing subtypes.
     */
    public function down(): void
    {
        // Get the current thing span type
        $thingType = DB::table('span_types')->where('type_id', 'thing')->first();

        if ($thingType) {
            // Decode current metadata
            $metadata = json_decode($thingType->metadata, true);

            // Remove 'series' and 'episode' from the subtype options
            $subtypeOptions = $metadata['schema']['subtype']['options'] ?? [];
            $subtypeOptions = array_filter($subtypeOptions, function ($option) {
                return !in_array($option, ['series', 'episode']);
            });
            $metadata['schema']['subtype']['options'] = array_values($subtypeOptions);

            // Update the span type
            DB::table('span_types')
                ->where('type_id', 'thing')
                ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now()
            ]);

            Cache::forget('span_type_thing_subtype_options');
        }
    }
};

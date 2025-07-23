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
        $setType = DB::table('span_types')->where('type_id', 'set')->first();
        if ($setType) {
            $metadata = json_decode($setType->metadata, true);
            if (isset($metadata['schema']['name'])) {
                unset($metadata['schema']['name']);
                DB::table('span_types')
                    ->where('type_id', 'set')
                    ->update([
                        'metadata' => json_encode($metadata),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $setType = DB::table('span_types')->where('type_id', 'set')->first();
        if ($setType) {
            $metadata = json_decode($setType->metadata, true);
            if (!isset($metadata['schema']['name'])) {
                $metadata['schema']['name'] = [
                    'type' => 'text',
                    'required' => true,
                    'label' => 'Set Name',
                ];
                DB::table('span_types')
                    ->where('type_id', 'set')
                    ->update([
                        'metadata' => json_encode($metadata),
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}; 
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
        // Update the set span type to include subtype schema
        DB::table('span_types')
            ->where('type_id', 'set')
            ->update([
                'metadata' => json_encode([
                    'timeless' => true,
                    'schema' => [
                        'name' => [
                            'type' => 'text',
                            'required' => true,
                            'label' => 'Set Name'
                        ],
                        'description' => [
                            'type' => 'text',
                            'required' => false,
                            'label' => 'Description'
                        ],
                        'subtype' => [
                            'type' => 'select',
                            'required' => false,
                            'label' => 'Set Type',
                            'options' => [
                                'desertislanddiscs',
                                'starred',
                                'custom'
                            ],
                            'help' => 'The type of set (optional for custom sets)'
                        ]
                    ]
                ]),
                'updated_at' => now()
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the set span type to original schema
        DB::table('span_types')
            ->where('type_id', 'set')
            ->update([
                'metadata' => json_encode([
                    'timeless' => true,
                    'schema' => [
                        'name' => [
                            'type' => 'text',
                            'required' => true,
                            'label' => 'Set Name'
                        ],
                        'description' => [
                            'type' => 'text',
                            'required' => false,
                            'label' => 'Description'
                        ]
                    ]
                ]),
                'updated_at' => now()
            ]);
    }
}; 
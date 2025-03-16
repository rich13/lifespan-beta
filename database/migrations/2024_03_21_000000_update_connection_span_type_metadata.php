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
        // Get all connection types from the database
        $connectionTypes = DB::table('connection_types')
            ->select('type')
            ->orderBy('forward_predicate')
            ->get()
            ->pluck('type')
            ->toArray();

        // Update the connection span type's metadata
        DB::table('span_types')
            ->where('type_id', 'connection')
            ->update([
                'metadata' => json_encode([
                    'schema' => [
                        'connection_type' => [
                            'type' => 'select',
                            'label' => 'Connection Type',
                            'component' => 'select',
                            'options' => $connectionTypes,
                            'help' => 'Type of connection',
                            'required' => true
                        ],
                        'role' => [
                            'type' => 'text',
                            'label' => 'Role',
                            'component' => 'text-input',
                            'help' => 'Role or position in this connection',
                            'required' => false
                        ],
                        'notes' => [
                            'type' => 'textarea',
                            'label' => 'Notes',
                            'component' => 'textarea',
                            'help' => 'Additional notes about this connection',
                            'required' => false
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
        // Revert to the original hardcoded options
        DB::table('span_types')
            ->where('type_id', 'connection')
            ->update([
                'metadata' => json_encode([
                    'schema' => [
                        'connection_type' => [
                            'type' => 'select',
                            'label' => 'Connection Type',
                            'component' => 'select',
                            'options' => [
                                'family', 'education', 'work',
                                'residence', 'relationship', 'other'
                            ],
                            'help' => 'Type of connection',
                            'required' => true
                        ],
                        'role' => [
                            'type' => 'text',
                            'label' => 'Role',
                            'component' => 'text-input',
                            'help' => 'Role or position in this connection',
                            'required' => false
                        ],
                        'notes' => [
                            'type' => 'textarea',
                            'label' => 'Notes',
                            'component' => 'textarea',
                            'help' => 'Additional notes about this connection',
                            'required' => false
                        ]
                    ]
                ]),
                'updated_at' => now()
            ]);
    }
}; 
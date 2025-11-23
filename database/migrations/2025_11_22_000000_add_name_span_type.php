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
        DB::table('span_types')->insert([
            'type_id' => 'name',
            'name' => 'Name',
            'description' => 'A name entity - represents a temporal name of any span',
            'metadata' => json_encode([
                'schema' => [
                    'subtype' => [
                        'type' => 'select',
                        'label' => 'Type of Name',
                        'component' => 'select',
                        'options' => [
                            'birth_name',
                            'legal_name',
                            'stage_name',
                            'regnal_name',
                            'nickname',
                            'alias',
                            'married_name',
                            'maiden_name',
                            'brand_name',
                            'former_name',
                            'other'
                        ],
                        'help' => 'What kind of name is this?',
                        'required' => false
                    ]
                ]
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('span_types')
            ->where('type_id', 'name')
            ->delete();
    }
};


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
        Schema::create('relationship_types', function (Blueprint $table) {
            $table->string('type')->primary();
            $table->string('display_name');
            $table->string('forward_predicate');
            $table->string('inverse_predicate');
            $table->boolean('is_referential')->default(false);
            $table->boolean('requires_temporal_span')->default(false);
        });

        // Insert default relationship types
        DB::table('relationship_types')->insert([
            [
                'type' => 'parent',
                'display_name' => 'Parent',
                'forward_predicate' => 'is parent of',
                'inverse_predicate' => 'is child of',
                'is_referential' => true,
                'requires_temporal_span' => false
            ],
            [
                'type' => 'member_of',
                'display_name' => 'Member',
                'forward_predicate' => 'is member of',
                'inverse_predicate' => 'has member',
                'is_referential' => false,
                'requires_temporal_span' => true
            ],
            [
                'type' => 'located_at',
                'display_name' => 'Location',
                'forward_predicate' => 'is located at',
                'inverse_predicate' => 'is location of',
                'is_referential' => false,
                'requires_temporal_span' => true
            ],
            [
                'type' => 'participated_in',
                'display_name' => 'Participant',
                'forward_predicate' => 'participated in',
                'inverse_predicate' => 'had participant',
                'is_referential' => false,
                'requires_temporal_span' => true
            ],
            [
                'type' => 'at_work',
                'display_name' => 'Work',
                'forward_predicate' => 'worked at',
                'inverse_predicate' => 'employed',
                'is_referential' => false,
                'requires_temporal_span' => true
            ],
            [
                'type' => 'at_education',
                'display_name' => 'Education',
                'forward_predicate' => 'studied at',
                'inverse_predicate' => 'educated',
                'is_referential' => false,
                'requires_temporal_span' => true
            ],
            [
                'type' => 'at_place',
                'display_name' => 'Place',
                'forward_predicate' => 'was at',
                'inverse_predicate' => 'was location of',
                'is_referential' => false,
                'requires_temporal_span' => true
            ],
            [
                'type' => 'at_organisation',
                'display_name' => 'Organisation',
                'forward_predicate' => 'was at',
                'inverse_predicate' => 'had member',
                'is_referential' => false,
                'requires_temporal_span' => true
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relationship_types');
    }
};

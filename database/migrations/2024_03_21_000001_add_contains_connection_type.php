<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddContainsConnectionType extends Migration
{
    public function up()
    {
        // Check if the connection type already exists
        if (!DB::table('connection_types')->where('type', 'contains')->exists()) {
            DB::table('connection_types')->insert([
                'type' => 'contains',
                'forward_predicate' => 'contains',
                'forward_description' => 'A contains B means that A is a container or whole that includes B as a part',
                'inverse_predicate' => 'is contained in',
                'inverse_description' => 'B is contained in A means that B is a part of the container or whole A',
                'allowed_span_types' => json_encode([
                    'parent' => ['thing'],
                    'child' => ['thing']
                ])
            ]);
        }
    }

    public function down()
    {
        DB::table('connection_types')->where('type', 'contains')->delete();
    }
}
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
        // First, add a temporary UUID column
        Schema::table('invitation_codes', function (Blueprint $table) {
            $table->uuid('new_id')->nullable();
        });

        // Generate UUIDs for existing records
        DB::statement('UPDATE invitation_codes SET new_id = gen_random_uuid()');

        // Drop the primary key constraint
        DB::statement('ALTER TABLE invitation_codes DROP CONSTRAINT invitation_codes_pkey');

        // Drop the old ID column
        Schema::table('invitation_codes', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        // Rename new_id to id and make it the primary key
        DB::statement('ALTER TABLE invitation_codes RENAME COLUMN new_id TO id');
        DB::statement('ALTER TABLE invitation_codes ADD PRIMARY KEY (id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add a temporary bigint column
        Schema::table('invitation_codes', function (Blueprint $table) {
            $table->bigInteger('new_id')->nullable();
        });

        // Generate sequential IDs
        DB::statement('UPDATE invitation_codes SET new_id = nextval(\'invitation_codes_id_seq\')');

        // Drop the primary key constraint
        DB::statement('ALTER TABLE invitation_codes DROP CONSTRAINT invitation_codes_pkey');

        // Drop the old UUID column
        Schema::table('invitation_codes', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        // Rename new_id to id and make it the primary key
        DB::statement('ALTER TABLE invitation_codes RENAME COLUMN new_id TO id');
        DB::statement('ALTER TABLE invitation_codes ADD PRIMARY KEY (id)');
    }
}; 
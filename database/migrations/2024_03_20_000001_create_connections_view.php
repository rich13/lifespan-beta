<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("
            CREATE VIEW connections_spo AS
            SELECT 
                id,
                parent_id as subject_id,
                child_id as object_id,
                type_id,
                connection_span_id,
                created_at,
                updated_at
            FROM connections;
        ");
    }

    public function down()
    {
        DB::statement('DROP VIEW IF EXISTS connections_spo;');
    }
}; 
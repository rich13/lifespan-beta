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
        // Add CHECK constraint to ensure either user_id or group_id is set, but not both
        DB::statement('
            ALTER TABLE span_permissions 
            ADD CONSTRAINT span_permissions_user_or_group_check 
            CHECK (
                (user_id IS NOT NULL AND group_id IS NULL) OR 
                (user_id IS NULL AND group_id IS NOT NULL)
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            ALTER TABLE span_permissions 
            DROP CONSTRAINT IF EXISTS span_permissions_user_or_group_check
        ');
    }
};

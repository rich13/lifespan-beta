<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Fixes the personal_access_tokens table to use UUID for tokenable_id
     * instead of bigint, to match the users table which uses UUID.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL: Change tokenable_id from bigint to uuid
            DB::statement('ALTER TABLE personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_tokenable_id_foreign;');
            DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE uuid USING tokenable_id::text::uuid;');
            
            // Change user_id from bigint to uuid as well (if it exists)
            $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'personal_access_tokens'");
            $columnNames = array_map(fn($col) => $col->column_name, $columns);
            
            if (in_array('user_id', $columnNames)) {
                DB::statement('ALTER TABLE personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_user_id_foreign;');
                DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN user_id TYPE uuid USING user_id::text::uuid;');
            }
            
            // Re-add foreign key constraints
            DB::statement('ALTER TABLE personal_access_tokens ADD CONSTRAINT personal_access_tokens_tokenable_id_foreign FOREIGN KEY (tokenable_id) REFERENCES users(id) ON DELETE CASCADE;');
            
            if (in_array('user_id', $columnNames)) {
                DB::statement('ALTER TABLE personal_access_tokens ADD CONSTRAINT personal_access_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;');
            }
        } elseif (DB::getDriverName() === 'mysql') {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->dropForeignKey(['tokenable_id']);
                $table->uuid('tokenable_id')->change();
                $table->foreign('tokenable_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reversing this would lose data, so we keep it simple
        // In production, you would want more sophisticated rollback logic
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_tokenable_id_foreign;');
            DB::statement('ALTER TABLE personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_user_id_foreign;');
        }
    }
};

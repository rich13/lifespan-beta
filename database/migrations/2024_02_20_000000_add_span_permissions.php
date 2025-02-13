<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            // Drop the is_personal_span column as it's redundant with type_id='person'
            $table->dropColumn('is_personal_span');
            
            // Add Unix-style permissions (default: rw-r--r--)
            if (DB::getDriverName() === 'pgsql') {
                // PostgreSQL requires explicit bit type for bitwise operations
                DB::statement('ALTER TABLE spans ADD COLUMN permissions INTEGER DEFAULT 420'); // 420 is 0644 in decimal
            } else {
                $table->integer('permissions')->default(0644)->after('metadata');
            }
            
            // Add permission inheritance mode
            $table->string('permission_mode')->default('own')->after('permissions');
            
            // Add index for permission mode lookups
            $table->index('permission_mode');
        });

        // Add index for permissions to optimize bitwise operations
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX spans_permissions_index ON spans USING btree (permissions)');
        }
    }

    public function down(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->boolean('is_personal_span')->default(false);
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS spans_permissions_index');
            }
            $table->dropColumn(['permissions', 'permission_mode']);
            $table->dropIndex(['permission_mode']);
        });
    }
}; 
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('import_progress', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->after('plaque_type');
            $table->index(['import_type', 'plaque_type', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_progress', function (Blueprint $table) {
            $table->dropIndex(['import_type', 'plaque_type', 'user_id']);
            $table->dropColumn('user_id');
        });
    }
};

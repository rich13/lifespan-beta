<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->uuid('improvement_parent_id')->nullable();
            $table->index('improvement_parent_id', 'spans_improvement_parent_index');
        });

        DB::statement("ALTER TABLE spans ALTER COLUMN improvement_mode SET DEFAULT 'auto'");

        DB::table('spans')
            ->where('improvement_generation', 0)
            ->where('improvement_mode', 'defer')
            ->update(['improvement_mode' => 'auto']);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE spans ALTER COLUMN improvement_mode SET DEFAULT 'defer'");

        Schema::table('spans', function (Blueprint $table) {
            $table->dropIndex('spans_improvement_parent_index');
            $table->dropColumn('improvement_parent_id');
        });
    }
};

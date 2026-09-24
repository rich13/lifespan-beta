<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->string('improvement_mode', 16)->default('auto');
            $table->unsignedTinyInteger('improvement_generation')->default(0);
            $table->index(['improvement_mode', 'improvement_generation'], 'spans_improvement_queue_index');
        });
    }

    public function down(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->dropIndex('spans_improvement_queue_index');
            $table->dropColumn(['improvement_mode', 'improvement_generation']);
        });
    }
};

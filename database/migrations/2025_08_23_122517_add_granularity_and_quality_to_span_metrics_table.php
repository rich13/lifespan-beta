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
        Schema::table('span_metrics', function (Blueprint $table) {
            $table->decimal('residence_granularity', 5, 2)->nullable()->after('residence_score');
            $table->decimal('residence_quality', 5, 2)->nullable()->after('residence_granularity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('span_metrics', function (Blueprint $table) {
            $table->dropColumn(['residence_granularity', 'residence_quality']);
        });
    }
};

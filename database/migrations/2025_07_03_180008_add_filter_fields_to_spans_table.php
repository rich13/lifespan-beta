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
        Schema::table('spans', function (Blueprint $table) {
            // Add filter fields for smart sets
            $table->string('filter_type')->nullable()->after('metadata');
            $table->json('filter_criteria')->nullable()->after('filter_type');
            $table->boolean('is_predefined')->default(false)->after('filter_criteria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->dropColumn(['filter_type', 'filter_criteria', 'is_predefined']);
        });
    }
};

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
        // span_versions
        Schema::table('span_versions', function (Blueprint $table) {
            $table->dropForeign(['changed_by']);
            $table->uuid('changed_by')->nullable()->change();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });
        // connection_versions
        Schema::table('connection_versions', function (Blueprint $table) {
            $table->dropForeign(['changed_by']);
            $table->uuid('changed_by')->nullable()->change();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // span_versions
        Schema::table('span_versions', function (Blueprint $table) {
            $table->dropForeign(['changed_by']);
            $table->uuid('changed_by')->nullable(false)->change();
            $table->foreign('changed_by')->references('id')->on('users');
        });
        // connection_versions
        Schema::table('connection_versions', function (Blueprint $table) {
            $table->dropForeign(['changed_by']);
            $table->uuid('changed_by')->nullable(false)->change();
            $table->foreign('changed_by')->references('id')->on('users');
        });
    }
}; 
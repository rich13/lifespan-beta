<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Remove cascade delete from version tables so that deletion snapshots
     * are preserved even after the parent span/connection is deleted.
     * This allows us to maintain a full audit trail.
     */
    public function up(): void
    {
        // Drop foreign key constraints entirely for version tables
        // Version tables are audit logs and should preserve history even when
        // the parent span/connection is deleted (orphaned references are OK)
        Schema::table('span_versions', function (Blueprint $table) {
            $table->dropForeign(['span_id']);
            // Don't re-add it - versions are audit logs, orphaned references are intentional
        });
        
        Schema::table('connection_versions', function (Blueprint $table) {
            $table->dropForeign(['connection_id']);
            // Don't re-add it - versions are audit logs, orphaned references are intentional
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore cascade delete behavior
        Schema::table('span_versions', function (Blueprint $table) {
            $table->dropForeign(['span_id']);
        });
        
        Schema::table('span_versions', function (Blueprint $table) {
            $table->foreign('span_id')->references('id')->on('spans')->onDelete('cascade');
        });
        
        Schema::table('connection_versions', function (Blueprint $table) {
            $table->dropForeign(['connection_id']);
        });
        
        Schema::table('connection_versions', function (Blueprint $table) {
            $table->foreign('connection_id')->references('id')->on('connections')->onDelete('cascade');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns to spans table
        Schema::table('spans', function (Blueprint $table) {
            $table->enum('access_level', ['private', 'shared', 'public'])
                ->default('private')
                ->after('permission_mode');
            
            // Rename creator_id to owner_id
            $table->renameColumn('creator_id', 'owner_id');
        });

        // Create span_permissions table
        Schema::create('span_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('span_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('group_id')->nullable(); // For future expansion
            $table->enum('permission_type', ['view', 'edit']);
            $table->timestamps();

            // Unique constraint to prevent duplicate permissions
            $table->unique(['span_id', 'user_id', 'group_id', 'permission_type']);

            $table->index('span_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('span_permissions');

        Schema::table('spans', function (Blueprint $table) {
            $table->renameColumn('owner_id', 'creator_id');
            $table->dropColumn('access_level');
        });
    }
}; 
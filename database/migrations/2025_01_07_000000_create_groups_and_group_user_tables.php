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
        // Create groups table
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            
            $table->index('owner_id');
        });

        // Create group_user pivot table
        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignUuid('group_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            // Prevent duplicate memberships
            $table->unique(['group_id', 'user_id']);
            
            $table->index('group_id');
            $table->index('user_id');
        });

        // Update span_permissions table to properly support groups
        Schema::table('span_permissions', function (Blueprint $table) {
            // Add foreign key constraint for group_id if it doesn't exist
            if (!Schema::hasColumn('span_permissions', 'group_id')) {
                $table->foreignUuid('group_id')->nullable()->constrained()->cascadeOnDelete();
            } else {
                // Add the foreign key constraint to existing column
                $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            }
            
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign key constraint from span_permissions
        Schema::table('span_permissions', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropIndex(['group_id']);
        });

        // Drop tables in reverse order
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }
}; 
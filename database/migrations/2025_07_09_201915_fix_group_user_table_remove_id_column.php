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
        Schema::table('group_user', function (Blueprint $table) {
            // Check if the id column exists before trying to drop it
            if (Schema::hasColumn('group_user', 'id')) {
                $table->dropPrimary();
                $table->dropColumn('id');
                
                // Set the composite key as primary
                $table->primary(['group_id', 'user_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_user', function (Blueprint $table) {
            $table->dropPrimary();
            $table->uuid('id')->primary()->first();
        });
    }
};

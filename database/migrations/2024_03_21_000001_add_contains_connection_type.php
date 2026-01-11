<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NOTE: This migration is a duplicate of 2024_03_21_000000_add_contains_connection_type.php
     * The "contains" connection type is already created by the earlier migration.
     * This migration is kept for historical reasons but does nothing.
     */
    public function up(): void
    {
        // Connection type already created by 2024_03_21_000000_add_contains_connection_type.php
        // No action needed - this migration is effectively a no-op
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do nothing - let the earlier migration handle the down() operation
    }
};

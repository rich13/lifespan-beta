<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NOTE: This migration is superseded by 2025_07_11_080525_make_user_id_nullable_in_span_permissions_table.php
     * which properly handles the foreign key constraint. This migration is kept for historical reasons
     * but does nothing to avoid conflicts.
     */
    public function up(): void
    {
        // No-op: The change is handled by 2025_07_11_080525_make_user_id_nullable_in_span_permissions_table.php
        // which properly drops and re-adds the foreign key constraint
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: The rollback is handled by 2025_07_11_080525_make_user_id_nullable_in_span_permissions_table.php
    }
};

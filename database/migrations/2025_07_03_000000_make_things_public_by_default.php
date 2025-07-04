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
        // Note: PostgreSQL doesn't support changing column defaults easily
        // We'll handle this in the model instead by overriding the default
        // This migration is for documentation and future reference
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed since we're not changing the schema
    }
}; 
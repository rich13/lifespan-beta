<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Span;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Since we're doing a fresh install, we don't need to migrate data
        // The schema changes are handled in the previous migration
    }

    public function down(): void
    {
        // Clear all span permissions
        DB::table('span_permissions')->truncate();
    }
}; 
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make ALL connection spans public
        DB::table('spans')
            ->where('type_id', 'connection')
            ->update(['access_level' => 'public']);
            
        // Also make all system-owned spans public (for other infrastructure)
        $systemUser = DB::table('users')
            ->where('email', 'system@lifespan.app')
            ->first();

        if ($systemUser) {
            DB::table('spans')
                ->where('owner_id', $systemUser->id)
                ->update(['access_level' => 'public']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is non-destructive in the down direction
        // System-owned spans should remain public
    }
};

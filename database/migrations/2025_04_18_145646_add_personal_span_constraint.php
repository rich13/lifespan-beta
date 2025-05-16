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
        // First clean up any duplicate personal spans
        $this->cleanupDuplicatePersonalSpans();
        
        // Add a unique index on owner_id for spans where is_personal_span = true
        Schema::table('spans', function (Blueprint $table) {
            // Create a unique partial index on owner_id where is_personal_span = true
            DB::statement('CREATE UNIQUE INDEX personal_span_owner_unique ON spans (owner_id) WHERE is_personal_span = true');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            DB::statement('DROP INDEX IF EXISTS personal_span_owner_unique');
        });
    }
    
    /**
     * Helper function to clean up duplicate personal spans
     */
    private function cleanupDuplicatePersonalSpans(): void
    {
        // Find all users with multiple personal spans
        $users = DB::select("
            SELECT owner_id, COUNT(*) as count 
            FROM spans 
            WHERE is_personal_span = true 
            GROUP BY owner_id 
            HAVING COUNT(*) > 1
        ");
        
        foreach ($users as $user) {
            // For each user, find all their personal spans
            $spans = DB::select("
                SELECT id, owner_id 
                FROM spans 
                WHERE owner_id = ? AND is_personal_span = true 
                ORDER BY updated_at DESC
            ", [$user->owner_id]);
            
            // Keep the most recently updated one and mark others as not personal
            $keep = array_shift($spans);
            
            if (!empty($spans)) {
                $spanIds = array_map(function($span) {
                    return $span->id;
                }, $spans);
                
                DB::table('spans')
                    ->whereIn('id', $spanIds)
                    ->update(['is_personal_span' => false]);
                
                // Ensure the user's personal_span_id points to the kept span
                DB::table('users')
                    ->where('id', $user->owner_id)
                    ->update(['personal_span_id' => $keep->id]);
            }
        }
    }
};

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
        // Add unique constraint to prevent duplicate Flickr photos per user
        // This creates a partial unique index that only applies to photo spans with flickr_id
        DB::statement("
            CREATE UNIQUE INDEX unique_flickr_photo_per_user 
            ON spans (owner_id, (metadata->>'flickr_id')) 
            WHERE type_id = 'thing' 
              AND metadata->>'subtype' = 'photo' 
              AND metadata->>'flickr_id' IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the unique index
        DB::statement("DROP INDEX IF EXISTS unique_flickr_photo_per_user");
    }
};

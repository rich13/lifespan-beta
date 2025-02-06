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
        Schema::create('span_types', function (Blueprint $table) {
            $table->string('type')->primary();
            $table->text('description')->nullable();
            $table->jsonb('metadata_schema')->default('{}');
            $table->timestamps();

            // Add indexes
            $table->index('type');
        });

        // Insert default span types
        DB::table('span_types')->insert([
            ['type' => 'person', 'description' => 'A person or individual'],
            ['type' => 'event', 'description' => 'A historical or personal event'],
            ['type' => 'place', 'description' => 'A physical location or place'],
            ['type' => 'organisation', 'description' => 'An organization or institution'],
            ['type' => 'band', 'description' => 'A musical band or group'],
            ['type' => 'war', 'description' => 'A military conflict'],
            ['type' => 'thing', 'description' => 'A physical object or item'],
            ['type' => 'education', 'description' => 'An educational period or achievement'],
            ['type' => 'work', 'description' => 'A period of employment or work'],
            ['type' => 'residence', 'description' => 'A period of residence at a location'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('span_types');
    }
};

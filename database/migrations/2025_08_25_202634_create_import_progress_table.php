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
        Schema::create('import_progress', function (Blueprint $table) {
            $table->id();
            $table->string('import_type'); // 'blue_plaques', 'wikimedia', etc.
            $table->string('plaque_type')->nullable(); // 'london_blue', 'london_green', 'custom'
            $table->integer('total_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->integer('created_items')->default(0);
            $table->integer('skipped_items')->default(0);
            $table->integer('error_count')->default(0);
            $table->enum('status', ['running', 'completed', 'failed', 'cancelled'])->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Store additional data like errors, activity log, etc.
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['import_type', 'status']);
            $table->index(['plaque_type', 'status']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_progress');
    }
};

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
        Schema::create('relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id');
            $table->uuid('child_id');
            $table->string('type');
            $table->uuid('relationship_span_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            // Foreign keys
            $table->foreign('parent_id')->references('id')->on('spans')->cascadeOnDelete();
            $table->foreign('child_id')->references('id')->on('spans')->cascadeOnDelete();
            $table->foreign('relationship_span_id')->references('id')->on('spans')->nullOnDelete();
            $table->foreign('type')->references('type')->on('relationship_types');

            // Indexes
            $table->index('parent_id');
            $table->index('child_id');
            $table->index('type');
            $table->index('relationship_span_id');

            // Constraints
            $table->unique(['parent_id', 'child_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};

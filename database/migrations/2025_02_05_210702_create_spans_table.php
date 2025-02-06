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
        Schema::create('spans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->string('slug')->unique();
            $table->uuid('parent_id')->nullable();
            $table->uuid('root_id')->nullable();
            $table->integer('depth')->default(0);
            
            // Dates with precision
            $table->integer('start_year');
            $table->integer('start_month')->nullable();
            $table->integer('start_day')->nullable();
            $table->string('start_precision_level')->default('year');
            $table->integer('end_year')->nullable();
            $table->integer('end_month')->nullable();
            $table->integer('end_day')->nullable();
            $table->string('end_precision_level')->nullable();
            
            // Additional fields
            $table->string('state')->default('complete');
            $table->jsonb('metadata')->default('{}');
            $table->uuid('created_by');
            $table->uuid('updated_by');
            $table->timestamps();

            // Foreign keys (except self-referential ones)
            $table->foreign('type')->references('type')->on('span_types');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');

            // Indexes
            $table->index('type');
            $table->index(['start_year', 'start_month', 'start_day']);
            $table->index(['end_year', 'end_month', 'end_day']);
            $table->index(['parent_id', 'root_id', 'depth']);
            $table->index('slug');
            $table->index('created_by');
            $table->index('state');
        });

        // Add self-referential foreign keys after table creation
        Schema::table('spans', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('spans')->nullOnDelete();
            $table->foreign('root_id')->references('id')->on('spans')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spans');
    }
};

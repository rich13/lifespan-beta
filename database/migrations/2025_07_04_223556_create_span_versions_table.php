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
        Schema::create('span_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('span_id');
            $table->integer('version_number');
            $table->string('name');
            $table->string('slug');
            $table->string('type_id');
            $table->boolean('is_personal_span')->default(false);
            
            // Hierarchical structure
            $table->uuid('parent_id')->nullable();
            $table->uuid('root_id')->nullable();
            
            // Dates
            $table->integer('start_year')->nullable();
            $table->integer('start_month')->nullable();
            $table->integer('start_day')->nullable();
            $table->integer('end_year')->nullable();
            $table->integer('end_month')->nullable();
            $table->integer('end_day')->nullable();
            $table->string('start_precision')->default('year');
            $table->string('end_precision')->default('year');
            
            // State and metadata
            $table->string('state')->default('draft');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->jsonb('sources')->nullable();
            
            // Permissions and access control
            $table->integer('permissions')->default(0644);
            $table->string('permission_mode')->default('own');
            $table->enum('access_level', ['private', 'shared', 'public'])->default('private');
            
            // Filter fields
            $table->string('filter_type')->nullable();
            $table->json('filter_criteria')->nullable();
            $table->boolean('is_predefined')->default(false);
            
            // Version metadata
            $table->text('change_summary')->nullable();
            $table->uuid('changed_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('span_id')->references('id')->on('spans')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users');

            // Indexes
            $table->index('span_id');
            $table->index('version_number');
            $table->index('changed_by');
            $table->index(['span_id', 'version_number']);
            
            // Ensure unique version numbers per span
            $table->unique(['span_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('span_versions');
    }
};

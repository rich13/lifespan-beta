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
        Schema::create('connection_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connection_id');
            $table->integer('version_number');
            $table->uuid('parent_id');
            $table->uuid('child_id');
            $table->string('type_id');
            $table->uuid('connection_span_id');
            $table->jsonb('metadata')->default('{}');
            
            // Version metadata
            $table->text('change_summary')->nullable();
            $table->uuid('changed_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('connection_id')->references('id')->on('connections')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users');

            // Indexes
            $table->index('connection_id');
            $table->index('version_number');
            $table->index('changed_by');
            $table->index(['connection_id', 'version_number']);
            
            // Ensure unique version numbers per connection
            $table->unique(['connection_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connection_versions');
    }
};

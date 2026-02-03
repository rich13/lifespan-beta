<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores progress for bulk delete zero-connection duplicates so both web and queue worker see the same state.
     */
    public function up(): void
    {
        Schema::create('bulk_delete_progress', function (Blueprint $table) {
            $table->uuid('run_id')->primary();
            $table->unsignedInteger('total_groups')->default(0);
            $table->unsignedInteger('groups_processed')->default(0);
            $table->unsignedInteger('deleted_count')->default(0);
            $table->string('status', 32)->default('running'); // running, finished
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_delete_progress');
    }
};

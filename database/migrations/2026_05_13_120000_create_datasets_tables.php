<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('value_label');
            $table->string('unit', 64)->nullable();
            $table->text('attribution');
            $table->string('source_url', 2048)->nullable();
            $table->string('import_format', 64)->default('owid_grapher_csv');
            $table->timestamps();
        });

        Schema::create('dataset_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->string('series_key', 128);
            $table->string('label');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'series_key']);
        });

        Schema::create('dataset_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('series_id')->constrained('dataset_series')->cascadeOnDelete();
            $table->decimal('t_start', 20, 10);
            $table->decimal('t_end', 20, 10);
            $table->decimal('value', 28, 14);
            $table->timestamps();

            $table->unique(['series_id', 't_start']);
            $table->index(['series_id', 't_start', 't_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_observations');
        Schema::dropIfExists('dataset_series');
        Schema::dropIfExists('datasets');
    }
};

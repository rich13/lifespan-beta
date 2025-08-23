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
        Schema::create('span_metrics', function (Blueprint $table) {
            $table->id();
            $table->uuid('span_id');
            $table->json('metrics_data');
            $table->decimal('overall_score', 5, 2);
            $table->decimal('basic_score', 5, 2);
            $table->decimal('connection_score', 5, 2);
            $table->decimal('residence_score', 5, 2)->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->foreign('span_id')->references('id')->on('spans')->onDelete('cascade');
            $table->index(['span_id']);
            $table->index(['overall_score']);
            $table->index(['calculated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('span_metrics');
    }
};

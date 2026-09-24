<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('span_id')->index();
            $table->string('improver');
            $table->string('outcome');
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['improver', 'outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_attempts');
    }
};

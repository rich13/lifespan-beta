<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            // Add description and notes fields
            $table->text('description')->nullable()->after('state');
            $table->text('notes')->nullable()->after('description');
        });

        // Rename precision fields
        Schema::table('spans', function (Blueprint $table) {
            $table->renameColumn('start_precision_level', 'start_precision');
            $table->renameColumn('end_precision_level', 'end_precision');
        });
    }

    public function down(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'notes'
            ]);
        });

        Schema::table('spans', function (Blueprint $table) {
            $table->renameColumn('start_precision', 'start_precision_level');
            $table->renameColumn('end_precision', 'end_precision_level');
        });
    }
}; 
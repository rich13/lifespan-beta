<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\App;

return new class extends Migration
{
    /**
     * Run the migration only in testing environment.
     *
     * @return bool
     */
    public function shouldRun()
    {
        return App::environment('testing');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        // This migration only runs in the testing environment
        // It can be used to set up specific testing data or schema modifications
        
        // For example, you could create test-specific tables or indexes here
        // Or seed specific testing data that all tests can rely on
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        // Clean up any testing-specific changes
    }
}; 
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckIfSeedingNeeded extends Seeder
{
    /**
     * Check if seeding is needed by checking if the users table is empty.
     *
     * @return void
     */
    public function run()
    {
        // If users table doesn't exist or any other tables don't exist yet, 
        // we should seed
        if (!Schema::hasTable('users')) {
            $this->command->info('Users table does not exist - seeding needed');
            return;
        }

        // Check if users table is empty
        $userCount = DB::table('users')->count();
        
        if ($userCount === 0) {
            $this->command->info('Users table is empty - seeding needed');
            return;
        }
        
        // If we get here, we have users and don't need to seed
        $this->command->error('Database already has users - seeding not needed');
        exit(1); // Non-zero exit code will make the conditional in entrypoint.sh fail
    }
} 
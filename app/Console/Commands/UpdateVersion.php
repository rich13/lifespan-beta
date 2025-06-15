<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateVersion extends Command
{
    protected $signature = 'version:update {version?}';
    protected $description = 'Update the application version number';

    public function handle()
    {
        $version = $this->argument('version');

        if (!$version) {
            // If no version provided, get it from git
            $commitCount = trim(shell_exec("git rev-list --count HEAD 2>/dev/null"));
            if ($commitCount) {
                $version = '0.' . $commitCount;
            } else {
                $this->error('Could not determine version from git. Please provide a version number.');
                return 1;
            }
        }

        // Update .env file
        $envPath = base_path('.env');
        if (File::exists($envPath)) {
            $envContent = File::get($envPath);
            
            if (preg_match('/^APP_VERSION=.*/m', $envContent)) {
                $envContent = preg_replace('/^APP_VERSION=.*/m', 'APP_VERSION=' . $version, $envContent);
            } else {
                $envContent .= "\nAPP_VERSION=" . $version;
            }
            
            File::put($envPath, $envContent);
            $this->info("Version updated to {$version} in .env");
        }

        // Update .env.example if it exists
        $envExamplePath = base_path('.env.example');
        if (File::exists($envExamplePath)) {
            $envExampleContent = File::get($envExamplePath);
            
            if (preg_match('/^APP_VERSION=.*/m', $envExampleContent)) {
                $envExampleContent = preg_replace('/^APP_VERSION=.*/m', 'APP_VERSION=' . $version, $envExampleContent);
            } else {
                $envExampleContent .= "\nAPP_VERSION=" . $version;
            }
            
            File::put($envExamplePath, $envExampleContent);
            $this->info("Version updated to {$version} in .env.example");
        }

        return 0;
    }
} 
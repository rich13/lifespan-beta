<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupVersionHook extends Command
{
    protected $signature = 'version:setup-hook';
    protected $description = 'Set up the git pre-commit hook for version updates';

    public function handle()
    {
        $hookPath = base_path('.git/hooks/pre-commit');
        $hookContent = <<<'EOT'
#!/bin/sh

# Run the version update command
php artisan version:update

# Add the modified .env and .env.example files to the commit
git add .env .env.example

# Continue with the commit
exit 0
EOT;

        // Create the hook file
        File::put($hookPath, $hookContent);

        // Make it executable
        chmod($hookPath, 0755);

        $this->info('Git pre-commit hook has been set up successfully.');
        $this->info('The version number will now be automatically updated on each commit.');

        return 0;
    }
} 
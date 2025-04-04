<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Models\User;
use App\Services\Import\Types\PersonImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class ImportYaml extends Command
{
    protected $signature = 'import:yaml {file : Path to the YAML file to import} {--user= : ID of the user to associate with the import}';
    protected $description = 'Import data from a YAML file';

    public function handle()
    {
        $filePath = $this->argument('file');
        
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        // Get or create a user for the import
        $userId = $this->option('user');
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User not found with ID: {$userId}");
                return 1;
            }
        } else {
            // Create a system user if none specified
            $user = User::firstOrCreate(
                ['email' => 'system@lifespan.local'],
                ['password' => bcrypt(Str::random(32))]
            );

            // Create personal span for system user if it doesn't exist
            if (!$user->personal_span_id) {
                $user->createPersonalSpan([
                    'name' => 'System User',
                    'birth_year' => now()->year,
                    'birth_month' => now()->month,
                    'birth_day' => now()->day,
                ]);
            }
        }

        try {
            $data = Yaml::parseFile($filePath);
            Log::info('YAML file parsed successfully', ['data' => $data]);

            if (!isset($data['type']) || $data['type'] !== 'person') {
                $this->error('Only person type imports are supported at this time');
                return 1;
            }

            $importer = new PersonImporter($user);
            $result = $importer->import($filePath);

            if ($result['success']) {
                $this->info('Import completed successfully');
                $this->table(
                    ['Section', 'Created', 'Existing'],
                    collect($result)->filter(function ($details, $section) {
                        return is_array($details) && isset($details['created']) && isset($details['existing']);
                    })->map(function ($details, $section) {
                        return [
                            $section,
                            $details['created'] ?? 0,
                            $details['existing'] ?? 0
                        ];
                    })->toArray()
                );
            } else {
                $this->error('Import failed');
                foreach ($result['errors'] as $error) {
                    $this->error($error['type'] . ': ' . $error['message']);
                }
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Error importing file: " . $e->getMessage());
            Log::error('Import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
} 
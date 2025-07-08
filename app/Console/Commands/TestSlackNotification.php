<?php

namespace App\Console\Commands;

use App\Services\SlackNotificationService;
use Illuminate\Console\Command;

class TestSlackNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:test {type=system} {--message=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Slack notifications';

    /**
     * Execute the console command.
     */
    public function handle(SlackNotificationService $slackService)
    {
        $type = $this->argument('type');
        $message = $this->option('message');

        $this->info("Testing Slack notification type: {$type}");

        switch ($type) {
            case 'system':
                $slackService->notifySystemEvent('Test System Event', [
                    'environment' => config('app.env'),
                    'timestamp' => now()->toISOString(),
                    'message' => $message ?: 'This is a test system event from Lifespan',
                ], 'info');
                break;

            case 'ai':
                $slackService->notifyAiYamlGenerated('Test Person', true);
                break;

            case 'ai-fail':
                $slackService->notifyAiYamlGenerated('Test Person', false, 'Test error message');
                break;

            case 'import':
                $slackService->notifyImportCompleted('Test Import', 100, 95, 5);
                break;

            case 'backup':
                $slackService->notifyBackupCompleted(true);
                break;

            case 'backup-fail':
                $slackService->notifyBackupCompleted(false, 'Test backup failure');
                break;

            default:
                $this->error("Unknown notification type: {$type}");
                $this->info("Available types: system, ai, ai-fail, import, backup, backup-fail");
                return 1;
        }

        $this->info('Slack notification sent successfully!');
        return 0;
    }
} 
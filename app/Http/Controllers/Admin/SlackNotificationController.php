<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SlackNotificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlackNotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Show the Slack notification settings page
     */
    public function index(): View
    {
        $config = [
            'enabled' => config('slack-notifications.enabled'),
            'webhook_url' => config('services.slack.webhook_url'),
            'channel' => config('services.slack.channel'),
            'username' => config('services.slack.username'),
            'icon' => config('services.slack.icon'),
            'events' => config('slack-notifications.events'),
            'environments' => config('slack-notifications.environments'),
            'minimum_level' => config('slack-notifications.minimum_level'),
            'span_types' => config('slack-notifications.span_types'),
        ];

        return view('admin.slack-notifications.index', compact('config'));
    }

    /**
     * Test Slack notification
     */
    public function test(Request $request)
    {
        $request->validate([
            'type' => 'required|in:system,ai,ai-fail,import,backup,backup-fail',
            'message' => 'nullable|string|max:255',
        ]);

        try {
            $slackService = app(SlackNotificationService::class);
            
            $type = $request->input('type');
            $message = $request->input('message');

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
            }

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current Slack configuration status
     */
    public function status()
    {
        $webhookUrl = config('services.slack.webhook_url');
        $enabled = config('slack-notifications.enabled');
        $environment = config('app.env');
        $environmentEnabled = config("slack-notifications.environments.{$environment}", false);

        $status = [
            'webhook_configured' => !empty($webhookUrl),
            'notifications_enabled' => $enabled,
            'environment_enabled' => $environmentEnabled,
            'environment' => $environment,
            'overall_status' => !empty($webhookUrl) && $enabled && $environmentEnabled,
        ];

        return response()->json($status);
    }
} 
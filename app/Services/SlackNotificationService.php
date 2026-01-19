<?php

namespace App\Services;

use App\Models\Span;
use App\Models\User;
use App\Notifications\Slack\SpanCreatedNotification;
use App\Notifications\Slack\SpanUpdatedNotification;
use App\Notifications\Slack\SystemEventNotification;
use App\Notifications\SlackNotifiable;
use Illuminate\Support\Facades\Log;

class SlackNotificationService
{
    protected SlackNotifiable $notifiable;

    public function __construct()
    {
        $this->notifiable = new SlackNotifiable();
    }

    /**
     * Send notification when a span is created
     */
    public function notifySpanCreated(Span $span): void
    {
        if (!$this->shouldNotify('span_created', $span)) {
            return;
        }

        try {
            $this->notifiable->notify(new SpanCreatedNotification($span));
            Log::info('Slack notification sent for span creation', [
                'span_id' => $span->id,
                'span_name' => $span->name
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification for span creation', [
                'span_id' => $span->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification when a span is updated
     */
    public function notifySpanUpdated(Span $span, array $changes = []): void
    {
        if (!$this->shouldNotify('span_updated', $span)) {
            return;
        }

        try {
            $this->notifiable->notify(new SpanUpdatedNotification($span, $changes));
            Log::info('Slack notification sent for span update', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'changes' => array_keys($changes)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification for span update', [
                'span_id' => $span->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification for system events
     */
    public function notifySystemEvent(string $event, array $data = [], string $level = 'info'): void
    {
        if (!$this->shouldNotify('system_events', null, $level)) {
            return;
        }

        try {
            $this->notifiable->notify(new SystemEventNotification($event, $data, $level));
            Log::info('Slack notification sent for system event', [
                'event' => $event,
                'level' => $level
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification for system event', [
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification for user registration
     */
    public function notifyUserRegistered(User $user): void
    {
        if (!$this->shouldNotify('user_registered', null, 'success', $user)) {
            return;
        }

        $this->notifySystemEvent('User Registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'is_admin' => $user->is_admin ? 'Yes' : 'No',
        ], 'success');
    }

    /**
     * Send notification for successful user sign in
     */
    public function notifyUserSignedIn(User $user, ?string $ip = null): void
    {
        if (!$this->shouldNotify('user_signed_in', null, 'info', $user)) {
            return;
        }

        $data = [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name ?? 'N/A',
        ];

        if ($ip) {
            $data['ip'] = $ip;
        }

        $this->notifySystemEvent('User Signed In', $data, 'info');
    }

    /**
     * Send notification for password reset request
     */
    public function notifyPasswordResetRequested(User $user, ?string $ip = null): void
    {
        if (!$this->shouldNotify('password_reset_requested', null, 'warning', $user)) {
            return;
        }

        $data = [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name ?? 'N/A',
        ];

        if ($ip) {
            $data['ip'] = $ip;
        }

        $this->notifySystemEvent('Password Reset Requested', $data, 'warning');
    }

    /**
     * Send notification for successful password reset
     */
    public function notifyPasswordResetCompleted(User $user, ?string $ip = null): void
    {
        if (!$this->shouldNotify('password_reset_completed', null, 'warning', $user)) {
            return;
        }

        $data = [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name ?? 'N/A',
        ];

        if ($ip) {
            $data['ip'] = $ip;
        }

        $this->notifySystemEvent('Password Reset Completed', $data, 'warning');
    }

    /**
     * Send notification for blocked sign-in attempt (approval/verification issues)
     */
    public function notifySignInBlocked(User $user, string $reason, ?string $ip = null): void
    {
        if (!$this->shouldNotify('sign_in_blocked', null, 'warning', $user)) {
            return;
        }

        $data = [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name ?? 'N/A',
            'reason' => $reason,
        ];

        if ($ip) {
            $data['ip'] = $ip;
        }

        $this->notifySystemEvent('Sign In Blocked', $data, 'warning');
    }

    /**
     * Send notification for suspicious registration patterns
     */
    public function notifySuspiciousRegistration(array $data): void
    {
        if (!$this->shouldNotify('suspicious_registration', null, 'warning')) {
            return;
        }

        $this->notifySystemEvent('Suspicious Registration Pattern Detected', [
            'ip' => $data['ip'] ?? 'unknown',
            'email' => $data['email'] ?? 'unknown',
            'recent_registrations' => $data['recent_registrations'] ?? 0,
            'pattern' => $data['pattern'] ?? 'unknown',
        ], 'warning');
    }

    /**
     * Send notification for AI YAML generation
     */
    public function notifyAiYamlGenerated(string $name, bool $success, ?string $error = null): void
    {
        if (!$this->shouldNotify('ai_yaml_generated', null, $success ? 'success' : 'error')) {
            return;
        }

        $data = [
            'name' => $name,
            'status' => $success ? 'Success' : 'Failed',
        ];

        if (!$success && $error) {
            $data['error'] = $error;
        }

        $this->notifySystemEvent('AI YAML Generation', $data, $success ? 'success' : 'error');
    }

    /**
     * Send notification for import operations
     */
    public function notifyImportCompleted(string $type, int $total, int $successful, int $failed): void
    {
        if (!$this->shouldNotify('import_completed', null, $failed > 0 ? 'warning' : 'success')) {
            return;
        }

        $this->notifySystemEvent('Import Completed', [
            'type' => $type,
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 1) . '%' : '0%',
        ], $failed > 0 ? 'warning' : 'success');
    }

    /**
     * Send notification for backup operations
     */
    public function notifyBackupCompleted(bool $success, ?string $error = null): void
    {
        if (!$this->shouldNotify('backup_completed', null, $success ? 'success' : 'error')) {
            return;
        }

        $data = [
            'status' => $success ? 'Success' : 'Failed',
        ];

        if (!$success && $error) {
            $data['error'] = $error;
        }

        $this->notifySystemEvent('Database Backup', $data, $success ? 'success' : 'error');
    }

    /**
     * Check if Slack notifications are enabled
     */
    protected function isSlackEnabled(): bool
    {
        return !empty(config('services.slack.webhook_url')) && 
               config('app.env') !== 'testing' &&
               config('slack-notifications.enabled', true);
    }

    /**
     * Determine if a notification should be sent based on configuration
     */
    protected function shouldNotify(string $eventType, ?Span $span = null, string $level = 'info', ?User $user = null): bool
    {
        // Check if Slack is enabled
        if (!$this->isSlackEnabled()) {
            return false;
        }

        // Check if this event type is enabled
        if (!config("slack-notifications.events.{$eventType}", true)) {
            return false;
        }

        // Check environment filtering
        $environment = config('app.env');
        if (!config("slack-notifications.environments.{$environment}", false)) {
            return false;
        }

        // Check notification level
        $minimumLevel = config('slack-notifications.minimum_level', 'info');
        if (!$this->meetsMinimumLevel($level, $minimumLevel)) {
            return false;
        }

        // Check span type filtering for span-related events
        if ($span && !$this->shouldNotifyForSpanType($span)) {
            return false;
        }

        // Check user filtering for user-related events
        if ($user && !$this->shouldNotifyForUser($user)) {
            return false;
        }

        return true;
    }

    /**
     * Check if notification level meets minimum requirement
     */
    protected function meetsMinimumLevel(string $level, string $minimumLevel): bool
    {
        $levels = ['info' => 1, 'warning' => 2, 'error' => 3, 'success' => 4];
        return ($levels[$level] ?? 1) >= ($levels[$minimumLevel] ?? 1);
    }

    /**
     * Check if notification should be sent for this span type
     */
    protected function shouldNotifyForSpanType(Span $span): bool
    {
        $includeTypes = config('slack-notifications.span_types.include', []);
        $excludeTypes = config('slack-notifications.span_types.exclude', []);

        // If include list is not empty, only notify for included types
        if (!empty($includeTypes) && !in_array($span->type_id, $includeTypes)) {
            return false;
        }

        // If exclude list is not empty, don't notify for excluded types
        if (!empty($excludeTypes) && in_array($span->type_id, $excludeTypes)) {
            return false;
        }

        return true;
    }

    /**
     * Check if notification should be sent for this user
     */
    protected function shouldNotifyForUser(User $user): bool
    {
        $includeUsers = config('slack-notifications.users.include', []);
        $excludeUsers = config('slack-notifications.users.exclude', []);

        // If include list is not empty, only notify for included users
        if (!empty($includeUsers) && !in_array($user->email, $includeUsers)) {
            return false;
        }

        // If exclude list is not empty, don't notify for excluded users
        if (!empty($excludeUsers) && in_array($user->email, $excludeUsers)) {
            return false;
        }

        return true;
    }
} 
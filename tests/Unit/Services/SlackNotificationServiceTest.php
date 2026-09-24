<?php

namespace Tests\Unit\Services;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use App\Services\SlackNotificationService;
use Tests\TestCase;

class SlackNotificationServiceTest extends TestCase
{
    private SlackNotificationServiceForTest $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'slack-notifications.enabled' => true,
            'slack-notifications.environments.testing' => true,
            'slack-notifications.events.span_created' => true,
            'slack-notifications.events.span_updated' => true,
            'services.slack.webhook_url' => 'https://example.test/webhook',
        ]);

        Connection::$skipCacheClearingDuringImport = false;
        $this->service = new SlackNotificationServiceForTest();
    }

    protected function tearDown(): void
    {
        Connection::$skipCacheClearingDuringImport = false;
        parent::tearDown();
    }

    public function test_it_notifies_for_manually_created_spans(): void
    {
        $span = $this->makePersonSpan();

        $this->assertTrue($this->service->shouldNotifyPublic('span_created', $span));
        $this->assertTrue($this->service->shouldNotifyPublic('span_updated', $span));
    }

    public function test_it_skips_span_notifications_when_import_flag_is_set(): void
    {
        Connection::$skipCacheClearingDuringImport = true;
        $span = $this->makePersonSpan();

        $this->assertFalse($this->service->shouldNotifyPublic('span_created', $span));
        $this->assertFalse($this->service->shouldNotifyPublic('span_updated', $span));
    }

    public function test_it_skips_span_notifications_for_imported_data_source(): void
    {
        $span = $this->makePersonSpan([
            'metadata' => ['data_source' => 'desertislanddiscs'],
        ]);

        $this->assertFalse($this->service->shouldNotifyPublic('span_created', $span));
        $this->assertFalse($this->service->shouldNotifyPublic('span_updated', $span));
    }

    public function test_it_skips_span_notifications_for_background_processes(): void
    {
        $this->service->treatAsBackgroundProcess = true;
        $span = $this->makePersonSpan();

        $this->assertFalse($this->service->shouldNotifyPublic('span_created', $span));
        $this->assertFalse($this->service->shouldNotifyPublic('span_updated', $span));
    }

    public function test_import_skip_does_not_block_other_event_types(): void
    {
        Connection::$skipCacheClearingDuringImport = true;

        $this->assertTrue($this->service->shouldNotifyPublic('import_completed'));
    }

    private function makePersonSpan(array $attributes = []): Span
    {
        $user = User::factory()->withoutPersonalSpan()->create();

        return Span::factory()->create(array_merge([
            'name' => 'Ada Lovelace',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'metadata' => [],
        ], $attributes));
    }
}

class SlackNotificationServiceForTest extends SlackNotificationService
{
    public bool $treatAsBackgroundProcess = false;

    public function shouldNotifyPublic(string $eventType, ?Span $span = null, string $level = 'info', ?User $user = null): bool
    {
        return $this->shouldNotify($eventType, $span, $level, $user);
    }

    protected function isSlackEnabled(): bool
    {
        return true;
    }

    protected function isBackgroundProcess(): bool
    {
        return $this->treatAsBackgroundProcess;
    }
}

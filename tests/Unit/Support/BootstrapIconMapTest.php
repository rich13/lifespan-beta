<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BootstrapIconMap;
use PHPUnit\Framework\TestCase;

final class BootstrapIconMapTest extends TestCase
{
    public function test_span_person_suffix(): void
    {
        $this->assertSame('person-fill', BootstrapIconMap::suffix('span', 'person'));
    }

    public function test_span_unknown_uses_default(): void
    {
        $this->assertSame('box', BootstrapIconMap::suffix('span', 'unknown_future_type'));
    }

    public function test_connection_employment(): void
    {
        $this->assertSame('briefcase-fill', BootstrapIconMap::suffix('connection', 'employment'));
        $this->assertSame('briefcase-fill', BootstrapIconMap::suffix('connection', 'work'));
    }

    public function test_subtype_film(): void
    {
        $this->assertSame('camera-video', BootstrapIconMap::suffix('subtype', 'film'));
    }

    public function test_bi_class_prefix(): void
    {
        $this->assertSame('bi-person-fill', BootstrapIconMap::biClass('span', 'person'));
    }

    public function test_for_client_includes_defaults_and_span_keys(): void
    {
        $client = BootstrapIconMap::forClient();
        $this->assertArrayHasKey('defaults', $client);
        $this->assertSame('box', $client['defaults']['span']);
        $this->assertArrayHasKey('person', $client['span']);
    }

    public function test_no_subtype_action_is_x(): void
    {
        $this->assertSame('x', BootstrapIconMap::suffix('action', 'no_subtype'));
    }
}

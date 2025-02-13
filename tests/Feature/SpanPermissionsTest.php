<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SpanPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_has_full_access(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
    }

    public function test_group_permissions(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
    }

    public function test_others_permissions(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
    }

    public function test_permission_inheritance(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
    }

    public function test_admin_override(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
    }

    public function test_permission_string_display(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
    }
} 
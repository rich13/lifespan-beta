<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestSpanAtDateAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_public_span_at_date(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Public Person',
            'start_year' => 1900,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2000,
            'end_month' => 1,
            'end_day' => 1,
        ]);

        $response = $this->get(route('spans.at-date', [
            'span' => $span,
            'date' => '1950-06-15',
        ]));

        $response->assertOk();
    }

    public function test_guest_cannot_view_private_span_at_date(): void
    {
        $owner = User::factory()->create();
        $span = Span::factory()->create([
            'type_id' => 'person',
            'owner_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Private Person',
        ]);

        $response = $this->get(route('spans.at-date', [
            'span' => $span,
            'date' => '1950-06-15',
        ]));

        $response->assertRedirect(route('login'));
    }
}

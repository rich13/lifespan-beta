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
        $response->assertSee('Public Person');
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

    public function test_at_date_internal_error_returns_500(): void
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

        $this->mock(\App\Services\Lifespan\UrlDateParser::class, function ($mock) {
            $mock->shouldReceive('parseAnchor')
                ->andThrow(new \RuntimeException('parser exploded'));
        });

        $response = $this->get(route('spans.at-date', [
            'span' => $span,
            'date' => '1950-06-15',
        ]));

        $response->assertStatus(500);
        $response->assertSee('Euston', false);
    }

    public function test_guest_can_explore_a_date_url(): void
    {
        Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Public Date Person',
            'start_year' => 1950,
            'start_month' => 6,
            'start_day' => 15,
        ]);

        $owner = User::factory()->create();
        Span::factory()->create([
            'type_id' => 'person',
            'owner_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Private Date Person',
            'start_year' => 1950,
            'start_month' => 6,
            'start_day' => 15,
        ]);

        $response = $this->get(route('date.explore', ['date' => '1950-06-15']));

        $response->assertOk();
        $response->assertViewIs('spans.date-explore');
        $response->assertSee('Public Date Person');
        $response->assertDontSee('Private Date Person');
    }
}

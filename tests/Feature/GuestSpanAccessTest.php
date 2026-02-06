<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestSpanAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_public_span(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Public Person',
        ]);

        $response = $this->get(route('spans.show', $span));

        $response->assertOk();
    }

    public function test_guest_cannot_view_private_span(): void
    {
        $owner = User::factory()->create();
        $span = Span::factory()->create([
            'type_id' => 'person',
            'owner_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Private Person',
        ]);

        $response = $this->get(route('spans.show', $span));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_view_shared_span_without_permission(): void
    {
        $owner = User::factory()->create();
        $span = Span::factory()->create([
            'type_id' => 'person',
            'owner_id' => $owner->id,
            'access_level' => 'shared',
            'name' => 'Shared Person',
        ]);

        $response = $this->get(route('spans.show', $span));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_can_view_public_photo(): void
    {
        $photo = Span::factory()->create([
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'photo'],
            'access_level' => 'public',
            'name' => 'Public Photo',
        ]);

        $response = $this->get(route('photos.show', $photo));

        $response->assertOk();
    }

    public function test_guest_cannot_view_private_photo(): void
    {
        $owner = User::factory()->create();
        $photo = Span::factory()->create([
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Private Photo',
        ]);

        $response = $this->get(route('photos.show', $photo));

        $response->assertForbidden();
    }

    public function test_guest_cannot_view_shared_photo(): void
    {
        $owner = User::factory()->create();
        $photo = Span::factory()->create([
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $owner->id,
            'access_level' => 'shared',
            'name' => 'Shared Photo',
        ]);

        $response = $this->get(route('photos.show', $photo));

        $response->assertForbidden();
    }
}

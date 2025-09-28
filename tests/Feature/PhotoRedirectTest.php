<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Tests\TestCase;

class PhotoRedirectTest extends TestCase
{
    public function test_photo_span_redirects_to_photos_route(): void
    {
        // Create a photo span
        $photo = Span::factory()->create([
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'photo'],
            'access_level' => 'public'
        ]);

        // Test UUID redirect - should redirect to slug first, then to photos
        $response = $this->get("/spans/{$photo->id}");
        if ($photo->slug) {
            // If slug exists, should redirect to slug first
            $response->assertRedirect("/spans/{$photo->slug}");
        } else {
            // If no slug, should redirect directly to photos
            $response->assertRedirect("/photos/{$photo->id}");
        }
        $response->assertStatus(301);

        // Test slug redirect (if slug exists) - should redirect to photos
        if ($photo->slug) {
            $response = $this->get("/spans/{$photo->slug}");
            $response->assertRedirect("/photos/{$photo->id}");
            $response->assertStatus(301);
        }
    }

    public function test_non_photo_span_does_not_redirect_to_photos_route(): void
    {
        // Create a person span
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public'
        ]);

        // Test that person span doesn't redirect to photos
        $response = $this->get("/spans/{$person->id}");
        
        // Should either show the span or redirect to slug, but not to photos
        if ($response->isRedirect()) {
            $this->assertStringNotContainsString('/photos/', $response->getTargetUrl());
        }
    }

    public function test_photo_span_edit_redirects_to_photos_edit_route(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a photo span owned by the user
        $photo = Span::factory()->create([
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $user->id,
            'access_level' => 'private'
        ]);

        $response = $this->get("/spans/{$photo->id}/edit");
        $response->assertRedirect("/photos/{$photo->id}/edit");
        $response->assertStatus(301);
    }

    public function test_photo_span_compare_redirects_to_photos_compare_route(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a photo span owned by the user
        $photo = Span::factory()->create([
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $user->id,
            'access_level' => 'private'
        ]);

        $response = $this->get("/spans/{$photo->id}/compare");
        $response->assertRedirect("/photos/{$photo->id}/compare");
        $response->assertStatus(301);
    }

    public function test_photo_span_story_redirects_to_photos_story_route(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a photo span owned by the user
        $photo = Span::factory()->create([
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $user->id,
            'access_level' => 'private'
        ]);

        $response = $this->get("/spans/{$photo->id}/story");
        $response->assertRedirect("/photos/{$photo->id}/story");
        $response->assertStatus(301);
    }
}

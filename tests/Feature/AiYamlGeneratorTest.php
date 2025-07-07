<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiYamlGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_placeholder_spans_returns_only_person_spans()
    {
        // Create an admin user
        $admin = User::factory()->create(['is_admin' => true]);
        
        // Create some placeholder person spans
        $personSpan1 = Span::create([
            'name' => 'John Doe',
            'type_id' => 'person',
            'state' => 'placeholder',
            'owner_id' => $admin->id,
            'updater_id' => $admin->id,
        ]);
        
        $personSpan2 = Span::create([
            'name' => 'Jane Smith',
            'type_id' => 'person',
            'state' => 'placeholder',
            'owner_id' => $admin->id,
            'updater_id' => $admin->id,
        ]);
        
        // Create other types of placeholder spans (should be excluded)
        $organisationSpan = Span::create([
            'name' => 'Test Company',
            'type_id' => 'organisation',
            'state' => 'placeholder',
            'owner_id' => $admin->id,
            'updater_id' => $admin->id,
        ]);
        
        $connectionSpan = Span::create([
            'name' => 'Connection Span',
            'type_id' => 'connection',
            'state' => 'placeholder',
            'owner_id' => $admin->id,
            'updater_id' => $admin->id,
        ]);
        
        $setSpan = Span::create([
            'name' => 'Test Set',
            'type_id' => 'set',
            'state' => 'placeholder',
            'owner_id' => $admin->id,
            'updater_id' => $admin->id,
        ]);
        
        // Create a complete person span (should be excluded)
        $completeSpan = Span::create([
            'name' => 'Complete Person',
            'type_id' => 'person',
            'state' => 'complete',
            'start_year' => 1990,
            'start_month' => 1,
            'start_day' => 1,
            'owner_id' => $admin->id,
            'updater_id' => $admin->id,
        ]);

        // Act as the admin user
        $this->actingAs($admin);

        // Make the request
        $response = $this->getJson('/admin/ai-yaml-generator/placeholders');

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $data = $response->json();
        $placeholders = $data['placeholders'];

        // Should return only 2 placeholder person spans
        $this->assertCount(2, $placeholders);
        
        // Should include only the person spans
        $spanNames = collect($placeholders)->pluck('name')->toArray();
        $this->assertContains('John Doe', $spanNames);
        $this->assertContains('Jane Smith', $spanNames);
        
        // Should NOT include other types or complete spans
        $this->assertNotContains('Test Company', $spanNames);
        $this->assertNotContains('Connection Span', $spanNames);
        $this->assertNotContains('Test Set', $spanNames);
        $this->assertNotContains('Complete Person', $spanNames);
    }

    public function test_get_placeholder_spans_requires_admin_access()
    {
        // Create a non-admin user
        $user = User::factory()->create(['is_admin' => false]);
        
        // Act as the user
        $this->actingAs($user);

        // Make the request
        $response = $this->getJson('/admin/ai-yaml-generator/placeholders');

        // Should be forbidden
        $response->assertStatus(403);
    }

    public function test_get_placeholder_spans_returns_empty_array_when_no_placeholders()
    {
        // Create an admin user
        $admin = User::factory()->create(['is_admin' => true]);
        
        // Act as the admin user
        $this->actingAs($admin);

        // Make the request
        $response = $this->getJson('/admin/ai-yaml-generator/placeholders');

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'placeholders' => []
            ]);
    }
} 
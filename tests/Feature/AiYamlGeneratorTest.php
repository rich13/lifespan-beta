<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Tests\TestCase;
use App\Services\AiYamlCreatorService;

class AiYamlGeneratorTest extends TestCase
{

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

        // Should return at least 2 placeholder person spans (may be more from other tests)
        $this->assertGreaterThanOrEqual(2, count($placeholders));
        
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

    public function test_improve_span_with_ai()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create an existing span
        $existingSpan = Span::factory()->create([
            'name' => 'Jonny Greenwood',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'state' => 'placeholder',
            'description' => 'Radiohead guitarist',
            'metadata' => ['subtype' => 'public_figure'],
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null
        ]);

        // Mock the AI service to return improved YAML
        $improvedYaml = <<<'YAML'
name: 'Jonny Greenwood'
type: person
start: '1971-10-05'
description: 'English musician and composer, best known as the lead guitarist and keyboardist of Radiohead'
metadata:
  subtype: public_figure
  occupation: 'Musician, Composer'
sources:
  - 'https://en.wikipedia.org/wiki/Jonny_Greenwood'
connections:
  membership:
    - name: 'Radiohead'
      type: 'band'
      start: '1985'
      metadata:
        role: 'Lead Guitarist'
        instrument: 'Guitar, Keyboard'
  residence:
    - name: 'Oxford'
      type: 'place'
      start: '1971'
      end: '1991'
YAML;

        // Make the improve request
        $response = $this->postJson("/spans/{$existingSpan->id}/improve", [
            'ai_yaml' => $improvedYaml
        ]);

        // Debug: dump the response content
        if ($response->status() !== 200) {
            dump('Response status: ' . $response->status());
            dump('Response content: ' . $response->content());
        }

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Span improved successfully with AI data.'
            ]);

        // Verify the span was updated
        $existingSpan->refresh();
        $this->assertEquals('Jonny Greenwood', $existingSpan->name);
        $this->assertEquals(1971, $existingSpan->start_year);
        $this->assertEquals(10, $existingSpan->start_month);
        $this->assertEquals(5, $existingSpan->start_day);
        $this->assertEquals('complete', $existingSpan->state);
        $this->assertEquals('English musician and composer, best known as the lead guitarist and keyboardist of Radiohead', $existingSpan->description);
        $this->assertEquals('public_figure', $existingSpan->metadata['subtype']);
        $this->assertEquals('Musician, Composer', $existingSpan->metadata['occupation']);
        $this->assertContains('https://en.wikipedia.org/wiki/Jonny_Greenwood', $existingSpan->sources);

        // Verify connections were created
        $this->assertDatabaseHas('connections', [
            'parent_id' => $existingSpan->id,
            'type_id' => 'membership'
        ]);

        $this->assertDatabaseHas('connections', [
            'parent_id' => $existingSpan->id,
            'type_id' => 'residence'
        ]);
    }

    public function test_preview_span_improvement()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create an existing span
        $existingSpan = Span::factory()->create([
            'name' => 'Jonny Greenwood',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'state' => 'placeholder',
            'description' => 'Radiohead guitarist',
            'metadata' => ['subtype' => 'public_figure'],
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null
        ]);

        // Improved YAML data
        $improvedYaml = <<<'YAML'
name: 'Jonny Greenwood'
type: person
start: '1971-10-05'
description: 'English musician and composer, best known as the lead guitarist and keyboardist of Radiohead'
metadata:
  subtype: public_figure
  occupation: 'Musician, Composer'
sources:
  - 'https://en.wikipedia.org/wiki/Jonny_Greenwood'
connections:
  membership:
    - name: 'Radiohead'
      type: 'band'
      start: '1985'
      metadata:
        role: 'Lead Guitarist'
        instrument: 'Guitar, Keyboard'
  residence:
    - name: 'Oxford'
      type: 'place'
      start: '1971'
      end: '1991'
YAML;

        // Make the preview request
        $response = $this->postJson("/spans/{$existingSpan->id}/improve/preview", [
            'ai_yaml' => $improvedYaml
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Preview generated successfully'
            ])
            ->assertJsonStructure([
                'success',
                'impacts',
                'diff',
                'current_data',
                'merged_data',
                'message'
            ]);

        // Verify the preview data structure
        $data = $response->json();
        
        // Check that impacts are present
        $this->assertIsArray($data['impacts']);
        $this->assertNotEmpty($data['impacts']);
        
        // Check that diff is present and has the expected structure
        $this->assertIsArray($data['diff']);
        $this->assertArrayHasKey('basic_fields', $data['diff']);
        $this->assertArrayHasKey('metadata', $data['diff']);
        $this->assertArrayHasKey('sources', $data['diff']);
        $this->assertArrayHasKey('connections', $data['diff']);
        
        // Check that basic fields diff shows the description change
        $descriptionChanges = array_filter($data['diff']['basic_fields'], function($field) {
            return $field['field'] === 'description';
        });
        $this->assertNotEmpty($descriptionChanges);
        
        // Check that the span wasn't actually modified
        $existingSpan->refresh();
        $this->assertEquals('Radiohead guitarist', $existingSpan->description);
        $this->assertNull($existingSpan->start_year);
    }
} 
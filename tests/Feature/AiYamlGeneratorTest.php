<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Illuminate\Support\Facades\Log;
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

    /**
     * Test organisation YAML generation endpoint
     */
    public function test_generate_organisation_yaml_endpoint()
    {
        // Mock Log facade to prevent error logs from appearing in test output
        Log::shouldReceive('error')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('info')->withAnyArgs()->andReturnNull();

        // Create an admin user
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $response = $this->postJson('/admin/ai-yaml-generator/generate-organisation', [
            'name' => 'Apple Inc.',
            'disambiguation' => 'the tech company founded by Steve Jobs'
        ]);

        // In test environment without OpenAI API key, expect error
        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error' => 'Failed to generate YAML: OpenAI API key not configured'
        ]);
    }

    /**
     * Test organisation YAML improvement endpoint
     */
    public function test_improve_organisation_yaml_endpoint()
    {
        // Mock Log facade to prevent error logs from appearing in test output
        Log::shouldReceive('error')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('info')->withAnyArgs()->andReturnNull();

        // Create an admin user
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $existingYaml = "name: 'Apple Inc.'\ntype: organisation\nstate: placeholder\nstart: '1976'\nend: null\nmetadata:\n  subtype: corporation\n  industry: Technology\n  size: large\naccess_level: public";

        $response = $this->postJson('/admin/ai-yaml-generator/improve-organisation', [
            'name' => 'Apple Inc.',
            'existing_yaml' => $existingYaml,
            'disambiguation' => 'the tech company founded by Steve Jobs'
        ]);

        // In test environment without OpenAI API key, expect error
        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error' => 'Failed to improve YAML: OpenAI API key not configured'
        ]);
    }

    /**
     * Test improving an organisation span with AI through the span improvement endpoint
     */
    public function test_improve_organisation_span_with_ai()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create an existing organisation span
        $existingSpan = Span::factory()->create([
            'name' => 'Apple Inc.',
            'type_id' => 'organisation',
            'owner_id' => $user->id,
            'state' => 'placeholder',
            'description' => 'Technology company',
            'metadata' => ['subtype' => 'corporation'],
            'start_year' => 1976,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null
        ]);

        // Mock the AI service to return improved YAML
        $improvedYaml = <<<'YAML'
name: 'Apple Inc.'
type: organisation
start: '1976-04-01'
end: null
description: 'American multinational technology company that specializes in consumer electronics, computer software, and online services'
metadata:
  subtype: corporation
  industry: 'Technology'
  size: large
sources:
  - 'https://en.wikipedia.org/wiki/Apple_Inc.'
access_level: public
connections:
  located:
    - name: 'Cupertino, California'
      type: place
      start: '1976-04-01'
      end: null
      metadata: {}
YAML;

        // Make the improve request
        $response = $this->postJson("/spans/{$existingSpan->id}/improve", [
            'ai_yaml' => $improvedYaml
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Span improved successfully with AI data.'
            ]);

        // Verify the span was updated
        $existingSpan->refresh();
        $this->assertEquals('Apple Inc.', $existingSpan->name);
        $this->assertEquals(1976, $existingSpan->start_year);
        $this->assertEquals(4, $existingSpan->start_month);
        $this->assertEquals(1, $existingSpan->start_day);
        // Spans with dates are now auto-upgraded from placeholder to draft, not complete
        $this->assertEquals('draft', $existingSpan->state);
        $this->assertEquals('American multinational technology company that specializes in consumer electronics, computer software, and online services', $existingSpan->description);
        $this->assertEquals('corporation', $existingSpan->metadata['subtype']);
        $this->assertEquals('Technology', $existingSpan->metadata['industry']);
        $this->assertEquals('large', $existingSpan->metadata['size']);
        $this->assertContains('https://en.wikipedia.org/wiki/Apple_Inc.', $existingSpan->sources);

        // Verify connections were created
        $this->assertDatabaseHas('connections', [
            'parent_id' => $existingSpan->id,
            'type_id' => 'located'
        ]);
    }

    /**
     * Test that the supportsAiImprovement method correctly identifies supported span types
     */
    public function test_supports_ai_improvement_method()
    {
        // Test supported span types
        $this->assertTrue(AiYamlCreatorService::supportsAiImprovement('person'));
        $this->assertTrue(AiYamlCreatorService::supportsAiImprovement('organisation'));
        $this->assertTrue(AiYamlCreatorService::supportsAiImprovement('place'));
        $this->assertTrue(AiYamlCreatorService::supportsAiImprovement('event'));
        $this->assertTrue(AiYamlCreatorService::supportsAiImprovement('thing'));
        $this->assertTrue(AiYamlCreatorService::supportsAiImprovement('band'));

        // Test unsupported span types
        $this->assertFalse(AiYamlCreatorService::supportsAiImprovement('connection'));
        $this->assertFalse(AiYamlCreatorService::supportsAiImprovement('set'));
        $this->assertFalse(AiYamlCreatorService::supportsAiImprovement('role'));
        $this->assertFalse(AiYamlCreatorService::supportsAiImprovement('phase'));
        $this->assertFalse(AiYamlCreatorService::supportsAiImprovement('invalid_type'));
    }
} 
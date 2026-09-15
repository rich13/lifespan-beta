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
                'span_effects' => [
                    'updated',
                    'created',
                ],
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

        // Subject should be in updated
        $this->assertNotEmpty($data['span_effects']['updated']);
        $this->assertSame($existingSpan->id, $data['span_effects']['updated'][0]['id']);
        $this->assertSame('update', $data['span_effects']['updated'][0]['action']);

        $allEffectNames = collect($data['span_effects']['updated'])
            ->merge($data['span_effects']['created'])
            ->pluck('name')
            ->all();
        $this->assertContains('Radiohead', $allEffectNames);
        $this->assertContains('Oxford', $allEffectNames);

        // Targets that do not already exist should be listed as created
        $existingTargetNames = Span::whereIn('name', ['Radiohead', 'Oxford'])->pluck('name')->all();
        $createdNames = collect($data['span_effects']['created'])->pluck('name')->all();
        foreach (['Radiohead', 'Oxford'] as $targetName) {
            if (in_array($targetName, $existingTargetNames, true)) {
                $this->assertNotContains($targetName, $createdNames);
            } else {
                $this->assertContains($targetName, $createdNames);
            }
        }
        
        // Check that basic fields diff shows the description change
        $descriptionChanges = array_filter($data['diff']['basic_fields'], function($field) {
            return $field['field'] === 'description';
        });
        $this->assertNotEmpty($descriptionChanges);
        
        // Check that the span wasn't actually modified
        $existingSpan->refresh();
        $this->assertEquals('Radiohead guitarist', $existingSpan->description);
        $this->assertNull($existingSpan->start_year);

        $subjectChanges = $data['span_effects']['updated'][0]['changes'] ?? [];
        $this->assertNotEmpty($subjectChanges);
        $joinedChanges = implode("\n", $subjectChanges);
        $this->assertStringNotContainsString('metadata fields will be updated', $joinedChanges);
        $this->assertStringNotContainsString('connections will be added', $joinedChanges);
        $this->assertTrue(
            collect($subjectChanges)->contains(fn ($change) => str_contains($change, 'Description:')),
            'Expected a specific description change listing, got: ' . $joinedChanges
        );

        $connectionEffectChanges = collect($data['span_effects']['updated'])
            ->merge($data['span_effects']['created'])
            ->filter(fn ($item) => ($item['role'] ?? null) === 'connection_target')
            ->flatMap(fn ($item) => $item['changes'] ?? [])
            ->values()
            ->all();
        $joinedConnectionChanges = implode("\n", $connectionEffectChanges);
        $this->assertTrue(
            collect($connectionEffectChanges)->contains(fn ($change) => str_contains($change, 'Start:')),
            'Expected connection start dates in span effects, got: ' . $joinedConnectionChanges
        );
        $this->assertStringContainsString('Start: 1985', $joinedConnectionChanges);
        $this->assertStringContainsString('Start: 1971', $joinedConnectionChanges);
        $this->assertStringContainsString('End: 1991', $joinedConnectionChanges);
    }

    public function test_preview_span_improvement_links_existing_spans()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $existingSpan = Span::factory()->create([
            'name' => 'Thom Yorke Preview',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder',
            'description' => 'Musician',
        ]);

        $band = Span::factory()->create([
            'name' => 'Unique Band For Preview Link',
            'type_id' => 'band',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder',
        ]);

        $improvedYaml = <<<'YAML'
name: 'Thom Yorke Preview'
type: person
start: '1968-10-07'
description: 'English musician'
connections:
  membership:
    - name: 'Unique Band For Preview Link'
      type: 'band'
      start: '1985'
  residence:
    - name: 'Unique Place For Preview Create'
      type: 'place'
      start: '1971'
YAML;

        $response = $this->postJson("/spans/{$existingSpan->id}/improve/preview", [
            'ai_yaml' => $improvedYaml
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $updatedIds = collect($data['span_effects']['updated'])->pluck('id')->all();
        $this->assertContains($existingSpan->id, $updatedIds);
        $this->assertContains($band->id, $updatedIds);

        $createdNames = collect($data['span_effects']['created'])->pluck('name')->all();
        $this->assertContains('Unique Place For Preview Create', $createdNames);
        $this->assertNotContains('Unique Band For Preview Link', $createdNames);

        $bandEffect = collect($data['span_effects']['updated'])
            ->firstWhere('id', $band->id);
        $this->assertNotNull($bandEffect);
        $bandChanges = implode("\n", $bandEffect['changes'] ?? []);
        $this->assertStringContainsString('Will be linked via membership', $bandChanges);
        $this->assertStringContainsString('Start: 1985', $bandChanges);

        $this->assertSame('connection', $bandEffect['card']['kind'] ?? null);
        $this->assertSame('link', $bandEffect['card']['action'] ?? null);
        $this->assertSame('membership', $bandEffect['card']['predicate']['type_id'] ?? null);
        $this->assertNotEmpty($bandEffect['card']['predicate']['label'] ?? null);
        $this->assertSame(1985, (int) ($bandEffect['card']['start']['link'] ?? 0));
        $this->assertSame('Unique Band For Preview Link', $bandEffect['card']['object']['name'] ?? null);

        $placeEffect = collect($data['span_effects']['created'])
            ->firstWhere('name', 'Unique Place For Preview Create');
        $this->assertNotNull($placeEffect);
        $placeChanges = implode("\n", $placeEffect['changes'] ?? []);
        $this->assertStringContainsString('Will be created and linked via residence', $placeChanges);
        $this->assertStringContainsString('Start: 1971', $placeChanges);
        $this->assertSame('connection', $placeEffect['card']['kind'] ?? null);
        $this->assertSame('create', $placeEffect['card']['action'] ?? null);
        $this->assertTrue(($placeEffect['card']['has_dates'] ?? false));
        $this->assertSame('1971', (string) ($placeEffect['card']['start']['link'] ?? ''));
    }

    public function test_preview_reports_when_connection_has_no_dates()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $existingSpan = Span::factory()->create([
            'name' => 'No Dates Preview Person',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder',
            'description' => 'Musician',
        ]);

        $improvedYaml = <<<'YAML'
name: 'No Dates Preview Person'
type: person
start: '1968'
description: 'English musician'
connections:
  membership:
    - name: 'Band Without Dates'
      type: 'band'
YAML;

        $response = $this->postJson("/spans/{$existingSpan->id}/improve/preview", [
            'ai_yaml' => $improvedYaml
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $bandEffect = collect($data['span_effects']['created'])
            ->firstWhere('name', 'Band Without Dates');
        $this->assertNotNull($bandEffect);
        $changes = implode("\n", $bandEffect['changes'] ?? []);
        $this->assertStringContainsString('No dates found for this connection', $changes);
        $this->assertFalse($bandEffect['card']['has_dates'] ?? true);
        $this->assertContains(
            'No dates found for this connection',
            $bandEffect['card']['notes'] ?? []
        );
    }

    public function test_preview_does_not_report_unchanged_slug_and_dates_as_new()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $existingSpan = Span::factory()->create([
            'name' => 'Alec Guinness Preview',
            'slug' => 'alec-guinness-preview',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'complete',
            'description' => 'Actor',
            'metadata' => ['subtype' => 'public_figure'],
            'start_year' => 1914,
            'start_month' => 4,
            'start_day' => 2,
            'end_year' => 2000,
            'end_month' => 8,
            'end_day' => 5,
        ]);

        $improvedYaml = <<<'YAML'
name: 'Alec Guinness Preview'
type: person
slug: alec-guinness-preview
start: '1914-04-02'
end: '2000-08-05'
description: 'English actor'
metadata:
  subtype: public_figure
YAML;

        $response = $this->postJson("/spans/{$existingSpan->id}/improve/preview", [
            'ai_yaml' => $improvedYaml
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $basicFields = collect($data['diff']['basic_fields'] ?? []);
        foreach (['slug', 'start_year', 'start_month', 'start_day', 'end_year', 'end_month', 'end_day'] as $field) {
            $change = $basicFields->firstWhere('field', $field);
            $this->assertNull(
                $change,
                "Expected unchanged {$field} not to appear in diff, got: " . json_encode($change)
            );
        }

        $subjectChanges = $data['span_effects']['updated'][0]['changes'] ?? [];
        foreach ($subjectChanges as $change) {
            $this->assertStringNotContainsString('Slug will be set', $change);
            $this->assertStringNotContainsString('Start year will be set', $change);
            $this->assertStringNotContainsString('End year will be set', $change);
        }
    }

    public function test_preview_accepts_discrete_date_fields_from_ai_yaml()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $existingSpan = Span::factory()->create([
            'name' => 'Discrete Dates Preview',
            'slug' => 'discrete-dates-preview',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'complete',
            'description' => 'Actor',
            'metadata' => ['subtype' => 'public_figure'],
            'start_year' => 1914,
            'start_month' => 4,
            'start_day' => 2,
            'end_year' => 2000,
            'end_month' => 8,
            'end_day' => 5,
        ]);

        // AI sometimes returns database-style date parts instead of YAML start/end
        $improvedYaml = <<<'YAML'
name: 'Discrete Dates Preview'
type: person
slug: discrete-dates-preview
start_year: 1914
start_month: 4
start_day: 2
end_year: 2000
end_month: 8
end_day: 5
description: 'English actor'
metadata:
  subtype: public_figure
YAML;

        $response = $this->postJson("/spans/{$existingSpan->id}/improve/preview", [
            'ai_yaml' => $improvedYaml
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
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

        // In test environment without Anthropic API key, expect error
        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error' => 'Failed to generate YAML: Anthropic API key not configured'
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

        // In test environment without Anthropic API key, expect error
        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error' => 'Failed to improve YAML: Anthropic API key not configured'
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

    public function test_improve_page_is_available_for_supported_span_types()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $span = Span::factory()->create([
            'name' => 'Improve Page Person',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder',
        ]);

        $response = $this->get(route('spans.improve', $span));

        $response->assertStatus(200)
            ->assertSee('Improve ' . $span->name, false)
            ->assertSee('AI is researching', false)
            ->assertSee(route('spans.improve.apply', $span), false);
    }

    public function test_improve_page_returns_not_found_for_unsupported_span_types()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $span = Span::factory()->create([
            'name' => 'Unsupported Set',
            'type_id' => 'set',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder',
        ]);

        $this->get(route('spans.improve', $span))->assertStatus(404);
    }

    public function test_improve_page_requires_update_authorisation()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $span = Span::factory()->create([
            'name' => 'Owned Person',
            'type_id' => 'person',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'state' => 'placeholder',
            'access_level' => 'private',
        ]);

        $this->actingAs($otherUser);

        $this->get(route('spans.improve', $span))->assertStatus(403);
    }
}

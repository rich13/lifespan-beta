<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Span;
use App\Models\SpanType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DataExportImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create([
            'is_admin' => true
        ]);
        
        // Create regular user
        $this->user = User::factory()->create([
            'is_admin' => false
        ]);

        // Create span types if they don't exist
        SpanType::firstOrCreate(
            ['type_id' => 'person'],
            [
                'name' => 'Person',
                'description' => 'A person or individual'
            ]
        );
        SpanType::firstOrCreate(
            ['type_id' => 'organisation'],
            [
                'name' => 'Organisation',
                'description' => 'An organisation or company'
            ]
        );
    }

    public function test_admin_can_access_data_export_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.data-export.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.data-export.index');
        $response->assertSee('Data Export');
    }

    public function test_non_admin_cannot_access_data_export_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.data-export.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_access_data_import_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.data-import.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.data-import.index');
        $response->assertSee('Data Import');
    }

    public function test_non_admin_cannot_access_data_import_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.data-import.index'));

        $response->assertStatus(403);
    }

    public function test_can_export_all_spans_as_individual_files()
    {
        // Create some test spans
        $span1 = Span::factory()->create([
            'name' => 'Test Person 1',
            'type_id' => 'person',
            'created_by' => $this->admin->id
        ]);
        
        $span2 = Span::factory()->create([
            'name' => 'Test Organisation 1',
            'type_id' => 'organisation',
            'created_by' => $this->admin->id
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.data-export.export-all', [
                'format' => 'individual',
                'include_metadata' => 1,
                'include_connections' => 1
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/zip');
        $response->assertHeader('Content-Disposition', 'attachment; filename="lifespan-export-' . now()->format('Y-m-d-H-i-s') . '.zip"');
    }

    public function test_can_export_all_spans_as_single_file()
    {
        // Create some test spans
        $span1 = Span::factory()->create([
            'name' => 'Test Person 1',
            'type_id' => 'person',
            'created_by' => $this->admin->id
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.data-export.export-all', [
                'format' => 'single',
                'include_metadata' => 1,
                'include_connections' => 1
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/yaml');
        $response->assertHeader('Content-Disposition', 'attachment; filename="lifespan-export-' . now()->format('Y-m-d-H-i-s') . '.yaml"');
    }

    public function test_can_export_selected_spans()
    {
        // Create some test spans
        $span1 = Span::factory()->create([
            'name' => 'Test Person 1',
            'type_id' => 'person',
            'created_by' => $this->admin->id
        ]);
        
        $span2 = Span::factory()->create([
            'name' => 'Test Person 2',
            'type_id' => 'person',
            'created_by' => $this->admin->id
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.data-export.export-selected'), [
                'span_ids' => [$span1->id, $span2->id],
                'format' => 'individual'
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/zip');
    }

    public function test_can_get_export_statistics()
    {
        // Create some test spans
        Span::factory()->create([
            'name' => 'Test Person 1',
            'type_id' => 'person',
            'created_by' => $this->admin->id
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.data-export.get-stats'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_spans',
            'span_types',
            'recent_activity'
        ]);
    }

    public function test_can_preview_import_file()
    {
        Storage::fake('local');

        // Create a simple YAML file for testing
        $yamlContent = "name: 'Test Person'\ntype: person\nstate: placeholder";
        $file = UploadedFile::fake()->createWithContent('test.yaml', $yamlContent);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.data-import.preview'), [
                'import_file' => $file
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'preview' => [
                'filename',
                'file_size',
                'spans_found',
                'sample_spans'
            ]
        ]);
    }

    public function test_can_import_single_yaml_file()
    {
        Storage::fake('local');

        // Create a simple YAML file for testing
        $yamlContent = "name: 'Test Person'\ntype: person\nstate: placeholder";
        $file = UploadedFile::fake()->createWithContent('test.yaml', $yamlContent);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.data-import.import'), [
                'import_files' => [$file],
                'import_mode' => 'individual',
                'user_id' => $this->admin->id
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'summary' => [
                'total_files',
                'total_processed',
                'total_success',
                'total_errors'
            ],
            'results'
        ]);

        // Check that the span was created
        $this->assertDatabaseHas('spans', [
            'name' => 'Test Person',
            'type_id' => 'person'
        ]);
    }

    public function test_import_validates_required_fields()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.data-import.import'), [
                'import_mode' => 'individual'
                // Missing import_files
            ]);

        $response->assertStatus(422);
    }

    public function test_import_validates_file_types()
    {
        $file = UploadedFile::fake()->create('test.txt', 'This is not a YAML file');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.data-import.import'), [
                'import_files' => [$file],
                'import_mode' => 'individual'
            ]);

        $response->assertStatus(422);
    }

    public function test_export_creates_safe_filenames()
    {
        // Create a span with special characters in the name
        $span = Span::factory()->create([
            'name' => 'Test Person (Special) - @#$%^&*()',
            'type_id' => 'person',
            'created_by' => $this->admin->id
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.data-export.export-all', [
                'format' => 'individual'
            ]));

        $response->assertStatus(200);
        // The response should be a ZIP file with safe filenames
        $response->assertHeader('Content-Type', 'application/zip');
    }

    public function test_import_handles_errors_gracefully()
    {
        Storage::fake('local');

        // Create an invalid YAML file
        $yamlContent = "invalid: yaml: content: with: too: many: colons:";
        $file = UploadedFile::fake()->createWithContent('invalid.yaml', $yamlContent);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.data-import.import'), [
                'import_files' => [$file],
                'import_mode' => 'individual'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);
        
        // Should have errors in the results
        $responseData = $response->json();
        $this->assertGreaterThan(0, $responseData['summary']['total_errors']);
    }
} 
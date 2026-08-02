<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Span;
use App\Models\SpanType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DataExportImportTest extends TestCase
{

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
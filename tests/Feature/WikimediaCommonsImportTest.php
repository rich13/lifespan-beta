<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Span;
use App\Models\SpanType;
use App\Models\ConnectionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class WikimediaCommonsImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->adminUser = User::factory()->create(['is_admin' => true]);
        
        // Create required span types
        SpanType::firstOrCreate(['type_id' => 'thing'], ['name' => 'Thing', 'description' => 'A thing']);
        SpanType::firstOrCreate(['type_id' => 'connection'], ['name' => 'Connection', 'description' => 'A connection']);
        
        // Create features connection type
        ConnectionType::firstOrCreate(['type' => 'features'], [
            'forward_predicate' => 'features',
            'forward_description' => 'Subject of',
            'inverse_predicate' => 'is subject of',
            'inverse_description' => 'Is subject of',
            'constraint_type' => 'single'
        ]);

        // Mock the WikimediaCommonsApiService
        $this->mock(\App\Services\WikimediaCommonsApiService::class, function ($mock) {
            // Mock searchImages method
            $mock->shouldReceive('searchImages')
                ->andReturn([
                    'data' => [
                        [
                            'id' => '12345',
                            'title' => 'Test Image',
                            'snippet' => 'Test image description',
                            'timestamp' => '2020-01-01T00:00:00Z'
                        ]
                    ],
                    'meta' => [
                        'total' => 1,
                        'current_page' => 1,
                        'per_page' => 20,
                        'last_page' => 1
                    ]
                ]);

            // Mock searchImagesByYear method
            $mock->shouldReceive('searchImagesByYear')
                ->andReturn([
                    'data' => [
                        [
                            'id' => '12345',
                            'title' => 'Test Image 2020',
                            'snippet' => 'Test image from 2020',
                            'timestamp' => '2020-01-01T00:00:00Z'
                        ]
                    ],
                    'meta' => [
                        'total' => 1,
                        'current_page' => 1,
                        'per_page' => 20,
                        'last_page' => 1
                    ]
                ]);

            // Mock getImage method
            $mock->shouldReceive('getImage')
                ->with('12345')
                ->andReturn([
                    'id' => '12345',
                    'title' => 'Test Image',
                    'url' => 'https://example.com/test-image.jpg',
                    'description_url' => 'https://commons.wikimedia.org/wiki/File:Test_Image.jpg',
                    'width' => 800,
                    'height' => 600,
                    'size' => 1024000,
                    'mime' => 'image/jpeg',
                    'timestamp' => '2020-01-01T00:00:00Z',
                    'user' => 'TestUser',
                    'comment' => 'Test image upload',
                    'description' => 'A test image for Wikimedia Commons',
                    'metadata' => [
                        'date' => '2020-01-01',
                        'license' => 'CC BY-SA 4.0',
                        'categories' => ['Test Category'],
                        'depicts' => ['Test Subject'],
                        'location' => 'Test Location'
                    ]
                ]);

            // Mock clearCache method
            $mock->shouldReceive('clearCache')
                ->andReturn(true);
        });
    }

    /** @test */
    public function it_can_access_the_import_interface()
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/admin/import/wikimedia-commons');

        $response->assertStatus(200);
        $response->assertViewIs('admin.import.wikimedia-commons.index');
    }

    /** @test */
    public function it_can_search_for_images()
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/search', [
                'query' => 'test'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'images',
                'total',
                'page',
                'per_page'
            ]
        ]);
    }

    /** @test */
    public function it_can_search_for_images_by_year()
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/search-by-year', [
                'query' => 'test',
                'year' => 2020
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'images',
                'total',
                'page',
                'per_page'
            ]
        ]);
    }

    /** @test */
    public function it_can_get_image_data()
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/get-image-data', [
                'image_id' => '12345'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data'
        ]);
    }

    /** @test */
    public function it_can_preview_import()
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/preview-import', [
                'image_id' => '12345'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'image',
                'existing_image',
                'potential_spans',
                'will_create_image',
                'import_plan'
            ]
        ]);
    }

    /** @test */
    public function it_can_import_image_without_connection()
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/import-image', [
                'image_id' => '12345'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'image_span' => [
                    'id',
                    'name'
                ],
                'target_span'
            ]
        ]);
    }

    /** @test */
    public function it_can_import_image_with_connection()
    {
        // Create a target span
        $targetSpan = Span::create([
            'name' => 'Test Person',
            'type_id' => 'person',
            'owner_id' => $this->adminUser->id,
            'updater_id' => $this->adminUser->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 1990
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/import-image', [
                'image_id' => '12345',
                'target_span_id' => $targetSpan->id
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'image_span' => [
                    'id',
                    'name'
                ],
                'target_span' => [
                    'id',
                    'name'
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_clear_cache()
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/clear-cache');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message'
        ]);
    }

    /** @test */
    public function it_prevents_duplicate_imports()
    {
        // First import
        $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/import-image', [
                'image_id' => '12345'
            ]);

        // Second import of same image
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/import-image', [
                'image_id' => '12345'
            ]);

        $response->assertStatus(200);
        
        // Should not create duplicate spans
        $imageSpans = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->whereJsonContains('metadata->wikimedia_id', '12345')
            ->get();
            
        $this->assertEquals(1, $imageSpans->count());
    }

    /** @test */
    public function it_prevents_duplicate_connections()
    {
        // Create a target span
        $targetSpan = Span::create([
            'name' => 'Test Person',
            'type_id' => 'person',
            'owner_id' => $this->adminUser->id,
            'updater_id' => $this->adminUser->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 1990
        ]);

        // First import with connection
        $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/import-image', [
                'image_id' => '12345',
                'target_span_id' => $targetSpan->id
            ]);

        // Second import with same connection
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/import/wikimedia-commons/import-image', [
                'image_id' => '12345',
                'target_span_id' => $targetSpan->id
            ]);

        $response->assertStatus(200);
        
        // Should not create duplicate connections
        $connections = \App\Models\Connection::where('type_id', 'features')
            ->where('child_id', $targetSpan->id)
            ->get();
            
        $this->assertEquals(1, $connections->count());
    }

    /** @test */
    public function it_cleans_mediawiki_markup_from_descriptions()
    {
        $controller = new \App\Http\Controllers\Admin\WikimediaCommonsImportController(
            app(\App\Services\WikimediaCommonsApiService::class)
        );
        
        // Test various MediaWiki markup patterns
        $testCases = [
            // Language tags
            '{{en|1=English band [[The Beatles]] wave to fans after arriving at Kennedy Airport.}}' => 'English band The Beatles wave to fans after arriving at Kennedy Airport.',
            '{{en|Simple description}}' => 'Simple description',
            '{{en}}' => '',
            
            // Wiki links
            '[[The Beatles]]' => 'The Beatles',
            '[[The Beatles|Beatles]]' => 'Beatles',
            'Text with [[link]] and more' => 'Text with link and more',
            
            // Bold and italic
            '\'\'\'Bold text\'\'\'' => 'Bold text',
            '\'\'Italic text\'\'' => 'Italic text',
            '\'\'\'Bold\'\'\' and \'\'italic\'\'' => 'Bold and italic',
            
            // Complex example
            '{{en|1=English band [[The Beatles]] wave to \'\'\'fans\'\'\' after arriving at Kennedy Airport.}}' => 'English band The Beatles wave to fans after arriving at Kennedy Airport.',
            
            // Empty or null
            '' => '',
            null => '',
        ];
        
        foreach ($testCases as $input => $expected) {
            $result = $controller->cleanDescription($input);
            $this->assertEquals($expected, $result, "Failed for input: " . var_export($input, true));
        }
    }
}

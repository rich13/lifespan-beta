<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Span;
use App\Models\SpanType;
use App\Models\ConnectionType;
use App\Models\User;
use App\Models\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class SpreadsheetEditorTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $span;
    protected $personType;
    protected $educationConnectionType;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Get existing span types (created by migrations)
        $this->personType = SpanType::where('type_id', 'person')->first();
        $this->assertNotNull($this->personType, 'Person span type should exist from migrations');
        
        // Get or create connection types
        $this->educationConnectionType = ConnectionType::firstOrCreate([
            'type' => 'education'
        ], [
            'name' => 'Education',
            'description' => 'Educational connections',
            'forward_predicate' => 'studied at',
            'inverse_predicate' => 'educated'
        ]);
        
        // Create a test span with unique slug
        $this->span = Span::create([
            'name' => 'John Doe',
            'slug' => 'john-doe-' . uniqid(),
            'type_id' => 'person',
            'state' => 'draft',
            'access_level' => 'private',
            'description' => 'A test person',
            'notes' => 'Test notes',
            'start_year' => 1990,
            'start_month' => 5,
            'start_day' => 15,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'metadata' => [
                'subtype' => 'private_individual',
                'occupation' => 'Developer'
            ],
            'owner_id' => $this->user->id
        ]);
        
        // Create Harvard University span for connection tests
        $this->harvardSpan = Span::create([
            'name' => 'Harvard University',
            'slug' => 'harvard-university-' . uniqid(),
            'type_id' => 'organisation',
            'state' => 'complete',
            'access_level' => 'public',
            'description' => 'A prestigious university',
            'start_year' => 1636,
            'start_month' => 1,
            'start_day' => 1,
            'metadata' => [
                'subtype' => 'university'
            ],
            'owner_id' => $this->user->id
        ]);
    }

    /** @test */
    public function it_loads_spreadsheet_editor_page()
    {
        $response = $this->actingAs($this->user)
            ->get("/spans/{$this->span->id}/spanner");

        $response->assertStatus(200);
        $response->assertViewIs('spans.spreadsheet-editor');
        $response->assertViewHas('span', $this->span);
    }

    /** @test */
    public function it_returns_correct_span_data_for_spreadsheet()
    {
        $response = $this->actingAs($this->user)
            ->get("/spans/{$this->span->id}/spanner");

        $response->assertStatus(200);
        
        // Check that the view has the expected data structure
        $viewData = $response->viewData('spanData');
        
        // Core fields - use actual values from the created span
        $this->assertEquals('John Doe', $viewData['name']);
        $this->assertEquals($this->span->slug, $viewData['slug']);
        $this->assertEquals('person', $viewData['type']);
        $this->assertEquals('draft', $viewData['state']);
        $this->assertEquals('private', $viewData['access_level']);
        $this->assertEquals('A test person', $viewData['description']);
        $this->assertEquals('Test notes', $viewData['notes']);
        
        // Date fields
        $this->assertEquals(1990, $viewData['start_year']);
        $this->assertEquals(5, $viewData['start_month']);
        $this->assertEquals(15, $viewData['start_day']);
        $this->assertNull($viewData['end_year']);
        $this->assertNull($viewData['end_month']);
        $this->assertNull($viewData['end_day']);
        
        // Metadata
        $this->assertEquals('private_individual', $viewData['subtype']);
        $this->assertEquals('Developer', $viewData['metadata']['occupation']);
        
        // System info
        $this->assertEquals($this->user->name, $viewData['owner']);
        $this->assertEquals($this->span->id, $viewData['id']);
    }

    /** @test */
    public function it_validates_spreadsheet_data_correctly()
    {
        $spreadsheetData = [
            'name' => 'Jane Doe',
            'slug' => 'jane-doe-' . uniqid(), // Use unique slug
            'type' => 'person',
            'state' => 'complete',
            'access_level' => 'public',
            'description' => 'Updated description',
            'notes' => 'Updated notes',
            'start_year' => 1995,
            'start_month' => 6,
            'start_day' => 20,
            'end_year' => 2020,
            'end_month' => 12,
            'end_day' => 31,
            'subtype' => 'public_figure',
            'metadata' => [
                'occupation' => 'Artist'
            ],
            'connections' => [
                [
                    'subject' => 'Jane Doe',
                    'predicate' => 'education',
                    'object' => 'Harvard University',
                    'start_year' => 2010,
                    'start_month' => 9,
                    'start_day' => 1,
                    'end_year' => 2014,
                    'end_month' => 6,
                    'end_day' => 15,
                    'metadata' => ['degree' => 'Bachelor of Arts']
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/validate", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function it_validates_connection_rows_individually()
    {
        $connectionData = [
            'subject' => 'John Doe',
            'predicate' => 'education',
            'object' => 'MIT',
            'start_year' => 2015,
            'start_month' => 9,
            'start_day' => 1,
            'end_year' => 2019,
            'end_month' => 6,
            'end_day' => 15,
            'metadata' => ['degree' => 'PhD']
        ];

        $response = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/validate-connection", [
                'connection' => $connectionData
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function it_generates_preview_of_changes()
    {
        $spreadsheetData = [
            'name' => 'Jane Doe',
            'slug' => 'jane-doe-' . uniqid(), // Use unique slug
            'type' => 'person',
            'state' => 'complete',
            'access_level' => 'public',
            'description' => 'Updated description',
            'notes' => 'Updated notes',
            'start_year' => 1995,
            'start_month' => 6,
            'start_day' => 20,
            'subtype' => 'public_figure',
            'metadata' => [
                'occupation' => 'Artist'
            ],
            'connections' => [
                [
                    'subject' => 'Jane Doe',
                    'predicate' => 'education',
                    'object' => 'Harvard University',
                    'start_year' => 2010,
                    'start_month' => 9,
                    'start_day' => 1,
                    'end_year' => 2014,
                    'end_month' => 6,
                    'end_day' => 15
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/preview", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $data = $response->json();
        
        // Check that diff is generated
        $this->assertArrayHasKey('diff', $data);
        $this->assertArrayHasKey('basic_fields', $data['diff']);
        $this->assertArrayHasKey('metadata', $data['diff']);
        $this->assertArrayHasKey('connections', $data['diff']);
        
        // Check that basic field changes are detected
        $nameChange = collect($data['diff']['basic_fields'])->firstWhere('field', 'name');
        $this->assertNotNull($nameChange);
        $this->assertEquals('John Doe', $nameChange['current']);
        $this->assertEquals('Jane Doe', $nameChange['new']);
        
        // Check that metadata changes are detected
        $subtypeChange = collect($data['diff']['metadata'])->firstWhere('key', 'subtype');
        $this->assertNotNull($subtypeChange);
        $this->assertEquals('private_individual', $subtypeChange['current']);
        $this->assertEquals('public_figure', $subtypeChange['new']);
    }

    /** @test */
    public function it_only_reports_description_change_when_only_description_is_edited()
    {
        // This test simulates the user's scenario - only editing the description field
        $spreadsheetData = [
            'name' => 'John Doe', // Same as original
            'slug' => $this->span->slug, // Same as original
            'type' => 'person', // Same as original
            'state' => 'draft', // Same as original
            'access_level' => 'private', // Same as original
            'description' => 'This is a new description', // Only this is changed
            'notes' => 'Test notes', // Same as original
            'start_year' => 1990, // Same as original
            'start_month' => 5, // Same as original
            'start_day' => 15, // Same as original
            'subtype' => 'private_individual', // Same as original
            'metadata' => [
                'occupation' => 'Developer' // Same as original
            ]
            // No connections field - should not report connection changes
        ];

        $response = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/preview", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $data = $response->json();
        
        // Check that diff is generated
        $this->assertArrayHasKey('diff', $data);
        $this->assertArrayHasKey('basic_fields', $data['diff']);
        $this->assertArrayHasKey('metadata', $data['diff']);
        
        // Should only have description change in basic fields
        $this->assertCount(1, $data['diff']['basic_fields']);
        $descriptionChange = collect($data['diff']['basic_fields'])->firstWhere('field', 'description');
        $this->assertNotNull($descriptionChange);
        $this->assertEquals('A test person', $descriptionChange['current']);
        $this->assertEquals('This is a new description', $descriptionChange['new']);
        
        // Should have no metadata changes
        $this->assertEmpty($data['diff']['metadata']);
        
        // Should have no connection changes
        $this->assertEmpty($data['diff']['connections']);
    }

    /** @test */
    public function it_reports_no_changes_when_previewing_without_modifications()
    {
        // This test simulates the user's scenario - loading a span and pressing preview without changes
        $spreadsheetData = [
            'name' => 'John Doe', // Same as original
            'slug' => $this->span->slug, // Same as original
            'type' => 'person', // Same as original
            'state' => 'draft', // Same as original
            'access_level' => 'private', // Same as original
            'description' => 'A test person', // Same as original
            'notes' => 'Test notes', // Same as original
            'start_year' => 1990, // Same as original
            'start_month' => 5, // Same as original
            'start_day' => 15, // Same as original
            'subtype' => 'private_individual', // Same as original
            'metadata' => [
                'occupation' => 'Developer' // Same as original
            ]
            // No connections field - the span doesn't have any connections
        ];

        $response = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/preview", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $data = $response->json();
        
        // Check that diff is generated
        $this->assertArrayHasKey('diff', $data);
        $this->assertArrayHasKey('basic_fields', $data['diff']);
        $this->assertArrayHasKey('metadata', $data['diff']);
        $this->assertArrayHasKey('connections', $data['diff']);
        

        
        // Should have no changes at all
        $this->assertEmpty($data['diff']['basic_fields']);
        $this->assertEmpty($data['diff']['metadata']);
        $this->assertEmpty($data['diff']['connections']);
    }

    /** @test */
    public function it_reports_no_changes_when_previewing_span_with_existing_connections()
    {
        // Create a connection span for the education connection
        $connectionSpan = Span::create([
            'name' => 'John Doe education Harvard University',
            'type_id' => 'connection',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $this->user->id,
            'start_year' => 2010,
            'start_month' => 9,
            'start_day' => 1,
            'end_year' => 2014,
            'end_month' => 6,
            'end_day' => 15,
            'metadata' => ['degree' => 'Bachelor of Arts']
        ]);

        // Create the connection
        $connection = Connection::create([
            'parent_id' => $this->span->id,
            'child_id' => $this->harvardSpan->id,
            'type_id' => 'education',
            'connection_span_id' => $connectionSpan->id
        ]);

        // This test simulates the user's scenario - loading a span with connections and pressing preview without changes
        $spreadsheetData = [
            'name' => 'John Doe', // Same as original
            'slug' => $this->span->slug, // Same as original
            'type' => 'person', // Same as original
            'state' => 'draft', // Same as original
            'access_level' => 'private', // Same as original
            'description' => 'A test person', // Same as original
            'notes' => 'Test notes', // Same as original
            'start_year' => 1990, // Same as original
            'start_month' => 5, // Same as original
            'start_day' => 15, // Same as original
            'subtype' => 'private_individual', // Same as original
            'metadata' => [
                'occupation' => 'Developer' // Same as original
            ],
            // Include the original connections data exactly as it should be
            'connections' => [
                [
                    'subject' => 'John Doe',
                    'subject_id' => $this->span->id,
                    'predicate' => 'education',
                    'object' => 'Harvard University',
                    'object_id' => $this->harvardSpan->id,
                    'direction' => 'outgoing',
                    'start_year' => 2010,
                    'start_month' => 9,
                    'start_day' => 1,
                    'end_year' => 2014,
                    'end_month' => 6,
                    'end_day' => 15,
                    'metadata' => ['degree' => 'Bachelor of Arts']
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/preview", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $data = $response->json();
        
        // Check that diff is generated
        $this->assertArrayHasKey('diff', $data);
        $this->assertArrayHasKey('basic_fields', $data['diff']);
        $this->assertArrayHasKey('metadata', $data['diff']);
        $this->assertArrayHasKey('connections', $data['diff']);

        // Should have no changes at all
        $this->assertEmpty($data['diff']['basic_fields']);
        $this->assertEmpty($data['diff']['metadata']);
        $this->assertEmpty($data['diff']['connections']);
    }

    /** @test */
    public function it_saves_spreadsheet_data_correctly()
    {
        $newSlug = 'jane-doe-' . uniqid();
        $spreadsheetData = [
            'name' => 'Jane Doe',
            'slug' => $newSlug,
            'type' => 'person',
            'state' => 'complete',
            'access_level' => 'public',
            'description' => 'Updated description',
            'notes' => 'Updated notes',
            'start_year' => 1995,
            'start_month' => 6,
            'start_day' => 20,
            'end_year' => 2020,
            'end_month' => 12,
            'end_day' => 31,
            'subtype' => 'public_figure',
            'metadata' => [
                'occupation' => 'Artist'
            ],
            'connections' => [
                [
                    'subject' => 'John Doe',
                    'subject_id' => $this->span->id,
                    'predicate' => 'education',
                    'object' => 'Harvard University',
                    'object_id' => $this->harvardSpan->id,
                    'start_year' => 2010,
                    'start_month' => 9,
                    'start_day' => 1,
                    'end_year' => 2014,
                    'end_month' => 6,
                    'end_day' => 15,
                    'metadata' => ['degree' => 'Bachelor of Arts']
                ]
            ]
        ];
        


        $response = $this->actingAs($this->user)
            ->put("/spans/{$this->span->id}/spanner", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        // Refresh the span from database
        $this->span->refresh();
        
        // Check that core fields were updated
        $this->assertEquals('Jane Doe', $this->span->name);
        $this->assertEquals($newSlug, $this->span->slug);
        $this->assertEquals('complete', $this->span->state);
        $this->assertEquals('public', $this->span->access_level);
        $this->assertEquals('Updated description', $this->span->description);
        $this->assertEquals('Updated notes', $this->span->notes);
        
        // Check that dates were updated
        $this->assertEquals(1995, $this->span->start_year);
        $this->assertEquals(6, $this->span->start_month);
        $this->assertEquals(20, $this->span->start_day);
        $this->assertEquals(2020, $this->span->end_year);
        $this->assertEquals(12, $this->span->end_month);
        $this->assertEquals(31, $this->span->end_day);
        
        // Check that metadata was updated
        $this->assertEquals('public_figure', $this->span->getMeta('subtype'));
        $this->assertEquals('Artist', $this->span->getMeta('occupation'));
        

        
        // Check that connections were created
        $this->assertCount(1, $this->span->connectionsAsSubject);
        $connection = $this->span->connectionsAsSubject->first();
        $this->assertEquals('education', $connection->type_id);
        $this->assertEquals('Harvard University', $connection->object->name);
        $this->assertEquals(2010, $connection->connectionSpan->start_year);
        $this->assertEquals(9, $connection->connectionSpan->start_month);
        $this->assertEquals(1, $connection->connectionSpan->start_day);
        $this->assertEquals(2014, $connection->connectionSpan->end_year);
        $this->assertEquals(6, $connection->connectionSpan->end_month);
        $this->assertEquals(15, $connection->connectionSpan->end_day);
        $this->assertEquals(['degree' => 'Bachelor of Arts'], $connection->connectionSpan->metadata);
    }

    /** @test */
    public function it_handles_empty_dates_correctly()
    {
        $spreadsheetData = [
            'name' => 'John Doe',
            'slug' => $this->span->slug, // Use existing slug
            'type' => 'person',
            'state' => 'placeholder', // Use placeholder state to avoid date requirements
            'access_level' => 'private',
            'description' => 'A test person',
            'notes' => 'Test notes',
            // No date fields - should clear existing dates
            'subtype' => 'private_individual',
            'metadata' => [
                'occupation' => 'Developer'
            ],
            'connections' => []
        ];

        $response = $this->actingAs($this->user)
            ->put("/spans/{$this->span->id}/spanner", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        // Refresh the span from database
        $this->span->refresh();
        
        // Check that dates were cleared
        $this->assertNull($this->span->start_year);
        $this->assertNull($this->span->start_month);
        $this->assertNull($this->span->start_day);
        $this->assertNull($this->span->end_year);
        $this->assertNull($this->span->end_month);
        $this->assertNull($this->span->end_day);
    }

    /** @test */
    public function it_validates_date_ranges_correctly()
    {
        // Test invalid date range (end before start)
        $spreadsheetData = [
            'name' => 'John Doe',
            'slug' => $this->span->slug, // Use existing slug
            'type' => 'person',
            'state' => 'draft',
            'access_level' => 'private',
            'description' => 'A test person',
            'notes' => 'Test notes',
            'start_year' => 2020,
            'start_month' => 6,
            'start_day' => 15,
            'end_year' => 2019, // End year before start year
            'end_month' => 12,
            'end_day' => 31,
            'subtype' => 'private_individual',
            'metadata' => [
                'occupation' => 'Developer'
            ],
            'connections' => []
        ];

        $response = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/validate", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => false]);
        
        $data = $response->json();
        // Check that there are validation errors related to date ranges
        $this->assertNotEmpty($data['errors']);
        $this->assertTrue(collect($data['errors'])->some(function($error) {
            return str_contains($error, 'cannot be before') || str_contains($error, 'End year') || str_contains($error, 'start year') || str_contains($error, 'Start date must be before end date');
        }));
    }

    /** @test */
    public function it_validates_connection_date_ranges()
    {
        $spreadsheetData = [
            'name' => 'John Doe',
            'slug' => $this->span->slug, // Use existing slug
            'type' => 'person',
            'state' => 'draft',
            'access_level' => 'private',
            'description' => 'A test person',
            'notes' => 'Test notes',
            'subtype' => 'private_individual',
            'metadata' => [
                'occupation' => 'Developer'
            ],
            'connections' => [
                [
                    'subject' => 'John Doe',
                    'predicate' => 'education',
                    'object' => 'Harvard University',
                    'start_year' => 2015,
                    'start_month' => 9,
                    'start_day' => 1,
                    'end_year' => 2014, // End year before start year
                    'end_month' => 6,
                    'end_day' => 15
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/validate", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => false]);
        
        $data = $response->json();
        // Check that there are validation errors related to connection date ranges
        $this->assertNotEmpty($data['errors']);
        $this->assertTrue(collect($data['errors'])->some(function($error) {
            return str_contains($error, 'cannot be before') || str_contains($error, 'End year') || str_contains($error, 'start year') || str_contains($error, 'Start date must be before end date');
        }));
    }

    /** @test */
    public function it_handles_partial_dates_correctly()
    {
        $spreadsheetData = [
            'name' => 'John Doe',
            'slug' => $this->span->slug, // Use existing slug
            'type' => 'person',
            'state' => 'draft',
            'access_level' => 'private',
            'description' => 'A test person',
            'notes' => 'Test notes',
            'start_year' => 1990, // Only year
            'start_month' => null,
            'start_day' => null,
            'end_year' => 2020,
            'end_month' => 6, // Year and month
            'end_day' => null,
            'subtype' => 'private_individual',
            'metadata' => [
                'occupation' => 'Developer'
            ],
            'connections' => []
        ];

        $response = $this->actingAs($this->user)
            ->put("/spans/{$this->span->id}/spanner", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        // Refresh the span from database
        $this->span->refresh();
        
        // Check that partial dates were saved correctly
        $this->assertEquals(1990, $this->span->start_year);
        $this->assertNull($this->span->start_month);
        $this->assertNull($this->span->start_day);
        $this->assertEquals(2020, $this->span->end_year);
        $this->assertEquals(6, $this->span->end_month);
        $this->assertNull($this->span->end_day);
    }

    /** @test */
    public function it_validates_required_metadata_fields()
    {
        // Test missing required subtype field
        $spreadsheetData = [
            'name' => 'John Doe',
            'slug' => $this->span->slug, // Use existing slug
            'type' => 'person',
            'state' => 'placeholder', // Use placeholder to avoid date requirements
            'access_level' => 'private',
            'description' => 'A test person',
            'notes' => 'Test notes',
            // Missing subtype field - this should cause validation to fail
            'metadata' => [
                'occupation' => 'Developer'
            ],
            'connections' => []
        ];


        
        $response = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/validate", $spreadsheetData);

        $response->assertStatus(200);
        
        $data = $response->json();
        

        
        // Check that validation failed due to missing subtype
        $this->assertFalse($data['success']);
        $this->assertNotEmpty($data['errors']);
        $this->assertTrue(collect($data['errors'])->some(function($error) {
            return str_contains($error, 'subtype') || str_contains($error, 'Required metadata');
        }));
    }

    /** @test */
    public function it_handles_connection_metadata_correctly()
    {
        $spreadsheetData = [
            'name' => 'John Doe',
            'slug' => $this->span->slug, // Use existing slug
            'type' => 'person',
            'state' => 'placeholder', // Use placeholder to avoid date requirements
            'access_level' => 'private',
            'description' => 'A test person',
            'notes' => 'Test notes',
            'subtype' => 'private_individual',
            'metadata' => [
                'occupation' => 'Developer'
            ],
            'connections' => [
                [
                    'subject' => 'John Doe',
                    'subject_id' => $this->span->id,
                    'predicate' => 'education',
                    'object' => 'Harvard University',
                    'object_id' => $this->harvardSpan->id,
                    'start_year' => 2010,
                    'start_month' => 9,
                    'start_day' => 1,
                    'end_year' => 2014,
                    'end_month' => 6,
                    'end_day' => 15,
                    'metadata' => [
                        'degree' => 'Bachelor of Science',
                        'major' => 'Computer Science',
                        'gpa' => 3.8
                    ]
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->put("/spans/{$this->span->id}/spanner", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        // Refresh the span from database
        $this->span->refresh();
        
        // Check that connection metadata was saved
        $connection = $this->span->connectionsAsSubject->first();
        $this->assertNotNull($connection, 'Connection should be created');
        $this->assertEquals([
            'degree' => 'Bachelor of Science',
            'major' => 'Computer Science',
            'gpa' => 3.8
        ], $connection->connectionSpan->metadata);
    }

    /** @test */
    public function it_updates_existing_connections()
    {
        // First, create an existing connection using the correct format
        $oldUniversity = Span::create([
            'name' => 'Old University',
            'slug' => 'old-university-' . uniqid(),
            'type_id' => 'organisation',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $this->user->id,
            'start_year' => 1900,
            'start_month' => 1,
            'start_day' => 1
        ]);

        // Create New University span for the test
        $newUniversity = Span::create([
            'name' => 'New University',
            'slug' => 'new-university-' . uniqid(),
            'type_id' => 'organisation',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $this->user->id,
            'start_year' => 1950,
            'start_month' => 1,
            'start_day' => 1
        ]);

        $connectionSpan = Span::create([
            'name' => 'John Doe education Old University',
            'type_id' => 'connection',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $this->user->id,
            'start_year' => 2005,
            'start_month' => 9,
            'start_day' => 1,
            'end_year' => 2009,
            'end_month' => 6,
            'end_day' => 15,
            'metadata' => ['degree' => 'Bachelor']
        ]);

        $existingConnection = Connection::create([
            'parent_id' => $this->span->id,
            'child_id' => $oldUniversity->id,
            'type_id' => 'education',
            'connection_span_id' => $connectionSpan->id
        ]);

        $spreadsheetData = [
            'name' => 'John Doe',
            'slug' => $this->span->slug, // Use existing slug
            'type' => 'person',
            'state' => 'placeholder', // Use placeholder to avoid date requirements
            'access_level' => 'private',
            'description' => 'A test person',
            'notes' => 'Test notes',
            'subtype' => 'private_individual',
            'metadata' => [
                'occupation' => 'Developer'
            ],
            'connections' => [
                [
                    'subject' => 'John Doe',
                    'subject_id' => $this->span->id,
                    'predicate' => 'education',
                    'object' => 'New University',
                    'object_id' => $newUniversity->id,
                    'start_year' => 2010,
                    'start_month' => 9,
                    'start_day' => 1,
                    'end_year' => 2014,
                    'end_month' => 6,
                    'end_day' => 15,
                    'metadata' => ['degree' => 'Master']
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->put("/spans/{$this->span->id}/spanner", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        // Refresh the span from database
        $this->span->refresh();
        
        // Check that old connection was replaced with new one
        $this->assertCount(1, $this->span->connectionsAsSubject);
        $connection = $this->span->connectionsAsSubject->first();
        $this->assertEquals('New University', $connection->object->name);
        $this->assertEquals(2010, $connection->connectionSpan->start_year);
        $this->assertEquals(['degree' => 'Master'], $connection->connectionSpan->metadata);
        
        // Check that old connection was deleted
        $this->assertDatabaseMissing('connections', ['id' => $existingConnection->id]);
    }

    /** @test */
    public function it_handles_type_changes_with_metadata()
    {
        // Get existing organisation type (created by migrations)
        $organisationType = SpanType::where('type_id', 'organisation')->first();
        $this->assertNotNull($organisationType, 'Organisation span type should exist from migrations');

        $spreadsheetData = [
            'name' => 'Tech Corp',
            'slug' => 'tech-corp-' . uniqid(), // Use unique slug
            'type' => 'organisation', // Changed from person to organisation
            'state' => 'complete',
            'access_level' => 'public',
            'description' => 'A technology company',
            'notes' => 'Company notes',
            'start_year' => 2010, // Add required start year
            'start_month' => 1,
            'start_day' => 1,
            'subtype' => 'corporation', // Add required subtype for organisation
            'metadata' => [
                'founded_year' => 2010
            ],
            'connections' => []
        ];

        $response = $this->actingAs($this->user)
            ->put("/spans/{$this->span->id}/spanner", $spreadsheetData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        // Refresh the span from database
        $this->span->refresh();
        
        // Check that type was changed
        $this->assertEquals('organisation', $this->span->type_id);
        
        // Check that new metadata was saved
        $this->assertEquals('corporation', $this->span->getMeta('subtype'));
        $this->assertEquals(2010, $this->span->getMeta('founded_year'));
        
        // Check that old person-specific metadata was removed
        $this->assertNull($this->span->getMeta('occupation'));
    }

    /** @test */
    public function it_validates_and_previews_all_spreadsheet_data_consistently()
    {
        // Create a span with existing data
        $this->span->update([
            'name' => 'Original Name',
            'description' => 'Original description',
            'metadata' => ['subtype' => 'private_individual', 'occupation' => 'Original job'],
            'start_year' => 1990,
            'start_month' => 1,
            'start_day' => 1
        ]);

        // Create a connection with existing data
        $connectionSpan = Span::create([
            'name' => 'Education Connection',
            'slug' => 'education-connection-' . uniqid(),
            'type_id' => 'connection',
            'state' => 'complete',
            'access_level' => 'private',
            'start_year' => 2010,
            'start_month' => 9,
            'start_day' => 1,
            'end_year' => 2014,
            'end_month' => 6,
            'end_day' => 15,
            'metadata' => ['degree' => 'Bachelor'],
            'owner_id' => $this->user->id
        ]);

        $connection = Connection::create([
            'parent_id' => $this->span->id,
            'child_id' => $this->harvardSpan->id,
            'type_id' => 'education',
            'connection_span_id' => $connectionSpan->id
        ]);

        // Test data with changes to ALL spreadsheet sections
        $spreadsheetData = [
            // Core fields changes
            'name' => 'Updated Name', // Changed
            'slug' => $this->span->slug,
            'type' => 'person',
            'state' => 'draft',
            'access_level' => 'public', // Changed
            'description' => 'Updated description', // Changed
            'notes' => 'Updated notes', // Changed
            'start_year' => 1991, // Changed
            'start_month' => 2, // Changed
            'start_day' => 15, // Changed
            
            // Metadata changes
            'subtype' => 'public_figure', // Changed
            'metadata' => [
                'occupation' => 'Updated job' // Changed
            ],
            
            // Connection changes
            'connections' => [
                [
                    'subject' => $this->span->name,
                    'subject_id' => $this->span->id,
                    'predicate' => 'education',
                    'object' => $this->harvardSpan->name,
                    'object_id' => $this->harvardSpan->id,
                    'start_year' => 2011, // Changed
                    'start_month' => 8, // Changed
                    'start_day' => 15, // Changed
                    'end_year' => 2015, // Changed
                    'end_month' => 5, // Changed
                    'end_day' => 20, // Changed
                    'metadata' => ['degree' => 'Master'] // Changed
                ]
            ]
        ];

        // Test 1: Validation should pass for all data
        $validationResponse = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/validate", $spreadsheetData);

        $validationResponse->assertStatus(200);
        $validationData = $validationResponse->json();
        $this->assertTrue($validationData['success'], 'Validation should pass for all data types');
        $this->assertEmpty($validationData['errors'], 'No validation errors should occur');

        // Test 2: Preview should detect changes in all sections
        $previewResponse = $this->actingAs($this->user)
            ->post("/spans/{$this->span->id}/spanner/preview", $spreadsheetData);

        $previewResponse->assertStatus(200);
        $previewData = $previewResponse->json();
        $this->assertTrue($previewData['success'], 'Preview should generate successfully');
        
        $diff = $previewData['diff'];
        
        // Check that core field changes are detected
        $this->assertNotEmpty($diff['basic_fields'], 'Core field changes should be detected');
        $coreFieldChanges = collect($diff['basic_fields'])->pluck('field')->toArray();
        $this->assertContains('name', $coreFieldChanges);
        $this->assertContains('access_level', $coreFieldChanges);
        $this->assertContains('description', $coreFieldChanges);
        $this->assertContains('notes', $coreFieldChanges);
        $this->assertContains('start_year', $coreFieldChanges);
        $this->assertContains('start_month', $coreFieldChanges);
        $this->assertContains('start_day', $coreFieldChanges);
        
        // Check that metadata changes are detected
        $this->assertNotEmpty($diff['metadata'], 'Metadata changes should be detected');
        $metadataChanges = collect($diff['metadata'])->pluck('key')->toArray();
        $this->assertContains('subtype', $metadataChanges);
        $this->assertContains('occupation', $metadataChanges);
        
        // Check that connection changes are detected
        $this->assertNotEmpty($diff['connections'], 'Connection changes should be detected');
        $connectionDiffs = $diff['connections'];
        $educationDiff = collect($connectionDiffs)->firstWhere('type', 'education');
        $this->assertNotNull($educationDiff, 'Education connection diff should be found');
        $this->assertNotEmpty($educationDiff['modified'], 'Connection modifications should be detected');
        
        // Test 3: Save should work for all data
        $saveResponse = $this->actingAs($this->user)
            ->put("/spans/{$this->span->id}/spanner", $spreadsheetData);

        $saveResponse->assertStatus(200);
        $saveData = $saveResponse->json();
        $this->assertTrue($saveData['success'], 'Save should succeed for all data types');
        
        // Test 4: Verify all changes were actually saved
        $this->span->refresh();
        
        // Check core fields were saved
        $this->assertEquals('Updated Name', $this->span->name);
        $this->assertEquals('public', $this->span->access_level);
        $this->assertEquals('Updated description', $this->span->description);
        $this->assertEquals('Updated notes', $this->span->notes);
        $this->assertEquals(1991, $this->span->start_year);
        $this->assertEquals(2, $this->span->start_month);
        $this->assertEquals(15, $this->span->start_day);
        
        // Check metadata was saved
        $this->assertEquals('public_figure', $this->span->getMeta('subtype'));
        $this->assertEquals('Updated job', $this->span->getMeta('occupation'));
        
        // Check connections were saved
        $this->assertCount(1, $this->span->connectionsAsSubject);
        $savedConnection = $this->span->connectionsAsSubject->first();
        $this->assertEquals($this->harvardSpan->name, $savedConnection->object->name, 'Connection should link to Harvard University');
        $this->assertEquals(2011, $savedConnection->connectionSpan->start_year);
        $this->assertEquals(8, $savedConnection->connectionSpan->start_month);
        $this->assertEquals(15, $savedConnection->connectionSpan->start_day);
        $this->assertEquals(2015, $savedConnection->connectionSpan->end_year);
        $this->assertEquals(5, $savedConnection->connectionSpan->end_month);
        $this->assertEquals(20, $savedConnection->connectionSpan->end_day);
        $this->assertEquals(['degree' => 'Master'], $savedConnection->connectionSpan->metadata);
    }
}

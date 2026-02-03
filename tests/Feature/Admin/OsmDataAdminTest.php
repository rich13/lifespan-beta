<?php

namespace Tests\Feature\Admin;

use App\Models\Span;
use App\Models\SpanType;
use App\Models\User;
use App\Services\OSMGeocodingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OsmDataAdminTest extends TestCase
{
    protected User $admin;

    protected User $user;

    /** @var string Path under storage/app used for test fixture (set in setUp when needed). */
    protected ?string $testOsmPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user = User::factory()->create(['is_admin' => false]);

        SpanType::firstOrCreate(
            ['type_id' => 'place'],
            ['name' => 'Place', 'description' => 'A place or location']
        );
    }

    protected function tearDown(): void
    {
        if ($this->testOsmPath !== null) {
            $path = storage_path('app/' . $this->testOsmPath);
            if (File::isFile($path)) {
                File::delete($path);
            }
            $dir = dirname($path);
            if (File::isDirectory($dir) && count(File::files($dir)) === 0) {
                File::deleteDirectory($dir);
            }
            Config::set('services.osm_import_data_path', 'osm/london-major-locations.json');
        }
        parent::tearDown();
    }

    /** @test */
    public function admin_can_access_osmdata_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.osmdata.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.osmdata.index');
        $response->assertViewHas('summary');
    }

    /** @test */
    public function non_admin_cannot_access_osmdata_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.osmdata.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function osmdata_index_shows_summary_when_file_missing()
    {
        Config::set('services.osm_import_data_path', 'osm/nonexistent-file.json');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.osmdata.index'));

        $response->assertStatus(200);
        $summary = $response->viewData('summary');
        $this->assertFalse($summary['exists']);
        $this->assertSame(0, $summary['total']);
        $this->assertSame([], $summary['categories']);
    }

    /** @test */
    public function preview_returns_400_when_file_not_available()
    {
        Config::set('services.osm_import_data_path', 'osm/nonexistent-file.json');

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.osmdata.preview'), [
                'offset' => 0,
                'limit' => 10,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'OSM data file is not available. Please generate it from the PBF first.',
        ]);
    }

    /** @test */
    public function preview_returns_200_with_data_when_fixture_present()
    {
        $this->createTestOsmFixture([
            ['name' => 'Test Borough A', 'category' => 'borough', 'latitude' => 51.5, 'longitude' => -0.1],
            ['name' => 'Test Station B', 'category' => 'station'],
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.osmdata.preview'), [
                'offset' => 0,
                'limit' => 10,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $data = $response->json('data');
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['items']);
        $this->assertSame('Test Borough A', $data['items'][0]['name']);
        $this->assertSame('borough', $data['items'][0]['category']);
        $this->assertSame('Test Station B', $data['items'][1]['name']);
    }

    /** @test */
    public function import_returns_400_when_file_not_available()
    {
        Config::set('services.osm_import_data_path', 'osm/nonexistent-file.json');

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.osmdata.import'), [
                'dry_run' => true,
                'limit' => 10,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'OSM data file is not available. Please generate it from the PBF first.',
        ]);
    }

    /** @test */
    public function import_dry_run_returns_200_and_creates_no_spans()
    {
        $this->createTestOsmFixture([
            ['name' => 'Dry Run Place One', 'category' => 'borough', 'latitude' => 51.5, 'longitude' => -0.1],
        ]);

        $this->mock(OSMGeocodingService::class, function ($mock) {
            $mock->shouldReceive('geocode')
                ->once()
                ->with('Dry Run Place One', 51.5, -0.1)
                ->andReturn($this->minimalOsmData('Dry Run Place One', 51.5, -0.1));
        });

        $initialCount = Span::where('type_id', 'place')->count();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.osmdata.import'), [
                'dry_run' => true,
                'limit' => 5,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $data = $response->json('data');
        $this->assertTrue($data['dry_run']);
        $this->assertGreaterThanOrEqual(1, $data['processed']);
        $this->assertGreaterThanOrEqual(1, $data['created'] + $data['updated'] + $data['skipped']);

        $this->assertSame($initialCount, Span::where('type_id', 'place')->count());
    }

    /** @test */
    public function import_creates_place_span_when_geocoding_succeeds()
    {
        $this->createTestOsmFixture([
            ['name' => 'Imported Place Test', 'category' => 'borough', 'latitude' => 51.5, 'longitude' => -0.1],
        ]);

        $this->mock(OSMGeocodingService::class, function ($mock) {
            $mock->shouldReceive('geocode')
                ->once()
                ->with('Imported Place Test', 51.5, -0.1)
                ->andReturn($this->minimalOsmData('Imported Place Test', 51.5, -0.1));
        });

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.osmdata.import'), [
                'dry_run' => false,
                'limit' => 5,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $data = $response->json('data');
        $this->assertFalse($data['dry_run']);
        // Accept either created or updated (location/name match may find existing span)
        $this->assertGreaterThanOrEqual(1, $data['created'] + $data['updated']);

        $span = Span::where('type_id', 'place')->where('name', 'Imported Place Test')->first();
        $this->assertNotNull($span);
        $this->assertNotNull($span->getOsmData());
        $this->assertSame('public', $span->access_level);
        $this->assertSame('complete', $span->state);
    }

    /**
     * Create a test JSON fixture at a unique path and set config to use it.
     *
     * @param array<int, array<string, mixed>> $features
     */
    protected function createTestOsmFixture(array $features): void
    {
        $this->testOsmPath = 'osm/test-london-major-' . uniqid() . '.json';
        $path = storage_path('app/' . $this->testOsmPath);
        $dir = dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        File::put($path, json_encode($features, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Config::set('services.osm_import_data_path', $this->testOsmPath);
    }

    /**
     * Minimal OSM data structure required by GeospatialCapability::setOsmData.
     */
    protected function minimalOsmData(string $name, float $lat, float $lon): array
    {
        return [
            'place_id' => 12345,
            'osm_type' => 'relation',
            'osm_id' => 67890,
            'canonical_name' => $name,
            'place_type' => 'administrative',
            'coordinates' => ['latitude' => $lat, 'longitude' => $lon],
        ];
    }
}

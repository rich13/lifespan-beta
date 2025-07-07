<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Services\Import\SpanImporterFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\DB;

class PrimeMinisterImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $testYamlPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();

        // Create required span types if they don't exist
        if (!DB::table('span_types')->where('type_id', 'person')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        if (!DB::table('span_types')->where('type_id', 'organisation')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'organisation',
                'name' => 'Organisation',
                'description' => 'An organization or institution',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        if (!DB::table('span_types')->where('type_id', 'place')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'place',
                'name' => 'Place',
                'description' => 'A place or location',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create required connection types
        $connectionTypes = [
            'family', 'education', 'employment', 'membership', 'residence'
        ];

        foreach ($connectionTypes as $type) {
            if (!DB::table('connection_types')->where('type', $type)->exists()) {
                DB::table('connection_types')->insert([
                    'type' => $type,
                    'name' => ucfirst($type),
                    'description' => ucfirst($type) . ' connection',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // Create test YAML file
        $this->testYamlPath = storage_path('app/test_prime_minister.yaml');
    }

    protected function tearDown(): void
    {
        // Clean up test file
        if (file_exists($this->testYamlPath)) {
            unlink($this->testYamlPath);
        }

        parent::tearDown();
    }

    public function test_imports_prime_minister_yaml_file_successfully(): void
    {
        // Create test YAML data
        $yamlData = [
            'name' => 'Test Prime Minister',
            'type' => 'prime_minister',
            'parliament_id' => 12345,
            'party' => 'Conservative',
            'constituency' => 'Test Constituency',
            'description' => 'A test Prime Minister',
            'start' => '1950-01-01',
            'metadata' => [
                'gender' => 'male',
                'is_prime_minister' => true
            ],
            'prime_ministerships' => [
                [
                    'start_date' => '1990-01-01',
                    'end_date' => '1995-01-01',
                    'party' => 'Conservative'
                ]
            ]
        ];

        file_put_contents($this->testYamlPath, Yaml::dump($yamlData, 4));

        // Create importer and import the test file
        $importer = SpanImporterFactory::create($this->testYamlPath, $this->user);
        $result = $importer->import($this->testYamlPath);

        // Check import was successful
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['errors']);

        // Check main span was created
        $mainSpan = Span::where('name', 'Test Prime Minister')->first();
        $this->assertNotNull($mainSpan);
        $this->assertEquals('person', $mainSpan->type_id);
        $this->assertEquals(1950, $mainSpan->start_year);
        $this->assertTrue($mainSpan->metadata['is_prime_minister'] ?? false);
        $this->assertEquals(12345, $mainSpan->metadata['parliament_id'] ?? null);
        $this->assertEquals('Conservative', $mainSpan->metadata['political_party'] ?? null);
        $this->assertEquals('Test Constituency', $mainSpan->metadata['constituency'] ?? null);

        // Check UK Government organisation was created
        $ukGovernment = Span::where('name', 'UK Government')->first();
        $this->assertNotNull($ukGovernment);
        $this->assertEquals('organisation', $ukGovernment->type_id);
        $this->assertEquals('government', $ukGovernment->metadata['type'] ?? null);

        // Check employment connection was created
        $employmentConnection = Connection::where('parent_id', $mainSpan->id)
            ->where('child_id', $ukGovernment->id)
            ->where('type_id', 'employment')
            ->first();
        $this->assertNotNull($employmentConnection);
        $this->assertEquals('Prime Minister', $employmentConnection->metadata['role'] ?? null);
        $this->assertEquals(1990, $employmentConnection->start_year);
        $this->assertEquals(1995, $employmentConnection->end_year);

        // Check political party was created
        $party = Span::where('name', 'Conservative')->first();
        $this->assertNotNull($party);
        $this->assertEquals('organisation', $party->type_id);
        $this->assertEquals('political_party', $party->metadata['type'] ?? null);

        // Check party membership connection was created
        $membershipConnection = Connection::where('parent_id', $mainSpan->id)
            ->where('child_id', $party->id)
            ->where('type_id', 'membership')
            ->first();
        $this->assertNotNull($membershipConnection);

        // Check constituency was created
        $constituency = Span::where('name', 'Test Constituency')->first();
        $this->assertNotNull($constituency);
        $this->assertEquals('place', $constituency->type_id);
        $this->assertEquals('constituency', $constituency->metadata['type'] ?? null);

        // Check constituency residence connection was created
        $residenceConnection = Connection::where('parent_id', $mainSpan->id)
            ->where('child_id', $constituency->id)
            ->where('type_id', 'residence')
            ->first();
        $this->assertNotNull($residenceConnection);
    }

    public function test_handles_multiple_prime_ministerships(): void
    {
        // Create test YAML data with multiple Prime Ministerships
        $yamlData = [
            'name' => 'Test Prime Minister',
            'type' => 'prime_minister',
            'parliament_id' => 12345,
            'party' => 'Conservative',
            'constituency' => 'Test Constituency',
            'description' => 'A test Prime Minister',
            'start' => '1950-01-01',
            'prime_ministerships' => [
                [
                    'start_date' => '1990-01-01',
                    'end_date' => '1995-01-01',
                    'party' => 'Conservative'
                ],
                [
                    'start_date' => '2000-01-01',
                    'end_date' => '2005-01-01',
                    'party' => 'Labour'
                ]
            ]
        ];

        file_put_contents($this->testYamlPath, Yaml::dump($yamlData, 4));

        // Create importer and import the test file
        $importer = SpanImporterFactory::create($this->testYamlPath, $this->user);
        $result = $importer->import($this->testYamlPath);

        // Check import was successful
        $this->assertTrue($result['success']);

        // Check main span was created
        $mainSpan = Span::where('name', 'Test Prime Minister')->first();
        $this->assertNotNull($mainSpan);

        // Check UK Government organisation was created
        $ukGovernment = Span::where('name', 'UK Government')->first();
        $this->assertNotNull($ukGovernment);

        // Check both employment connections were created
        $employmentConnections = Connection::where('parent_id', $mainSpan->id)
            ->where('child_id', $ukGovernment->id)
            ->where('type_id', 'employment')
            ->get();
        
        $this->assertEquals(2, $employmentConnections->count());

        // Check first period
        $firstConnection = $employmentConnections->where('start_year', 1990)->first();
        $this->assertNotNull($firstConnection);
        $this->assertEquals(1995, $firstConnection->end_year);
        $this->assertEquals('Conservative', $firstConnection->metadata['party'] ?? null);

        // Check second period
        $secondConnection = $employmentConnections->where('start_year', 2000)->first();
        $this->assertNotNull($secondConnection);
        $this->assertEquals(2005, $secondConnection->end_year);
        $this->assertEquals('Labour', $secondConnection->metadata['party'] ?? null);
    }

    public function test_handles_ongoing_prime_ministership(): void
    {
        // Create test YAML data with ongoing Prime Ministership
        $yamlData = [
            'name' => 'Test Prime Minister',
            'type' => 'prime_minister',
            'parliament_id' => 12345,
            'party' => 'Conservative',
            'constituency' => 'Test Constituency',
            'description' => 'A test Prime Minister',
            'start' => '1950-01-01',
            'prime_ministerships' => [
                [
                    'start_date' => '2020-01-01',
                    'ongoing' => true,
                    'party' => 'Conservative'
                ]
            ]
        ];

        file_put_contents($this->testYamlPath, Yaml::dump($yamlData, 4));

        // Create importer and import the test file
        $importer = SpanImporterFactory::create($this->testYamlPath, $this->user);
        $result = $importer->import($this->testYamlPath);

        // Check import was successful
        $this->assertTrue($result['success']);

        // Check main span was created
        $mainSpan = Span::where('name', 'Test Prime Minister')->first();
        $this->assertNotNull($mainSpan);

        // Check UK Government organisation was created
        $ukGovernment = Span::where('name', 'UK Government')->first();
        $this->assertNotNull($ukGovernment);

        // Check employment connection was created with no end date
        $employmentConnection = Connection::where('parent_id', $mainSpan->id)
            ->where('child_id', $ukGovernment->id)
            ->where('type_id', 'employment')
            ->first();
        
        $this->assertNotNull($employmentConnection);
        $this->assertEquals(2020, $employmentConnection->start_year);
        $this->assertNull($employmentConnection->end_year);
    }

    public function test_validates_required_fields(): void
    {
        // Create invalid YAML data (missing required fields)
        $yamlData = [
            'name' => 'Test Prime Minister',
            'type' => 'prime_minister',
            // Missing parliament_id and prime_ministerships
        ];

        file_put_contents($this->testYamlPath, Yaml::dump($yamlData, 4));

        // Try to import - this should fail validation
        $importer = SpanImporterFactory::create($this->testYamlPath, $this->user);
        $result = $importer->import($this->testYamlPath);

        // Check import failed
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validates_prime_ministership_dates(): void
    {
        // Create invalid YAML data (invalid dates)
        $yamlData = [
            'name' => 'Test Prime Minister',
            'type' => 'prime_minister',
            'parliament_id' => 12345,
            'party' => 'Conservative',
            'constituency' => 'Test Constituency',
            'prime_ministerships' => [
                [
                    'start_date' => '1995-01-01',
                    'end_date' => '1990-01-01', // End before start
                    'party' => 'Conservative'
                ]
            ]
        ];

        file_put_contents($this->testYamlPath, Yaml::dump($yamlData, 4));

        // Try to import - this should fail validation
        $importer = SpanImporterFactory::create($this->testYamlPath, $this->user);
        $result = $importer->import($this->testYamlPath);

        // Check import failed
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
    }
} 
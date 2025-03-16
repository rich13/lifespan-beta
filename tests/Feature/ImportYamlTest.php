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

class ImportYamlTest extends TestCase
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

        if (!DB::table('span_types')->where('type_id', 'band')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'band',
                'name' => 'Band',
                'description' => 'A musical band',
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

        if (!DB::table('span_types')->where('type_id', 'connection')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'connection',
                'name' => 'Connection',
                'description' => 'A temporal connection between spans',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        if (!DB::table('span_types')->where('type_id', 'place')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'place',
                'name' => 'Place',
                'description' => 'A location or place',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create required connection types if they don't exist
        $connectionTypes = [
            [
                'type' => 'membership',
                'forward_predicate' => 'is member of',
                'forward_description' => 'Is a member of',
                'inverse_predicate' => 'has member',
                'inverse_description' => 'Has as a member',
                'allowed_span_types' => json_encode([
                    'parent' => ['band', 'organisation'],
                    'child' => ['person']
                ]),
                'constraint_type' => 'non_overlapping'
            ],
            [
                'type' => 'education',
                'forward_predicate' => 'studied at',
                'forward_description' => 'Studied at',
                'inverse_predicate' => 'educated',
                'inverse_description' => 'Educated',
                'allowed_span_types' => json_encode([
                    'parent' => ['person'],
                    'child' => ['organisation']
                ]),
                'constraint_type' => 'non_overlapping'
            ],
            [
                'type' => 'employment',
                'forward_predicate' => 'worked at',
                'forward_description' => 'Worked at',
                'inverse_predicate' => 'employed',
                'inverse_description' => 'Employed',
                'allowed_span_types' => json_encode([
                    'parent' => ['person'],
                    'child' => ['organisation']
                ]),
                'constraint_type' => 'non_overlapping'
            ],
            [
                'type' => 'residence',
                'forward_predicate' => 'lived in',
                'forward_description' => 'Lived in',
                'inverse_predicate' => 'was home to',
                'inverse_description' => 'Was home to',
                'allowed_span_types' => json_encode([
                    'parent' => ['person'],
                    'child' => ['place']
                ]),
                'constraint_type' => 'non_overlapping'
            ],
            [
                'type' => 'relationship',
                'forward_predicate' => 'has relationship with',
                'forward_description' => 'Has a relationship with',
                'inverse_predicate' => 'has relationship with',
                'inverse_description' => 'Has a relationship with',
                'allowed_span_types' => json_encode([
                    'parent' => ['person'],
                    'child' => ['person']
                ]),
                'constraint_type' => 'single'
            ]
        ];

        foreach ($connectionTypes as $type) {
            if (!DB::table('connection_types')->where('type', $type['type'])->exists()) {
                DB::table('connection_types')->insert(array_merge($type, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
            }
        }

        // Create a test YAML file
        $this->testYamlPath = storage_path('app/test_import.yaml');
        $yaml = [
            'name' => 'Test Person',
            'type' => 'person',
            'start' => '1990-01-01',
            'education' => [
                [
                    'type' => 'education',
                    'institution' => 'Test University',
                    'start' => '2010',
                    'end' => '2014'
                ]
            ],
            'work' => [
                [
                    'type' => 'work',
                    'employer' => 'Test Company',
                    'start' => '2015',
                    'end' => null
                ]
            ],
            'places' => [
                [
                    'type' => 'residence',
                    'location' => 'Test City',
                    'start' => '1990',
                    'end' => null
                ]
            ],
            'relationships' => [
                [
                    'type' => 'relationship',
                    'person' => 'Test Friend',
                    'relationshipType' => 'friendship',
                    'start' => '2000',
                    'end' => null
                ]
            ]
        ];
        file_put_contents($this->testYamlPath, Yaml::dump($yaml, 4));  // Use depth of 4 for nested arrays
    }

    protected function tearDown(): void
    {
        // Clean up test file
        if (file_exists($this->testYamlPath)) {
            unlink($this->testYamlPath);
        }

        parent::tearDown();
    }

    public function test_imports_yaml_file_successfully(): void
    {
        // Create importer and import the test file
        $importer = SpanImporterFactory::create($this->testYamlPath, $this->user);
        $result = $importer->import($this->testYamlPath);

        // Check import was successful
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['errors']);

        // Check main span was created
        $mainSpan = Span::where('name', 'Test Person')->first();
        $this->assertNotNull($mainSpan);
        $this->assertEquals('person', $mainSpan->type_id);
        $this->assertEquals(1990, $mainSpan->start_year);

        // Check education connection was created
        $university = Span::where('name', 'Test University')->first();
        $this->assertNotNull($university);
        $this->assertEquals('organisation', $university->type_id);

        $educationConnection = Connection::where('parent_id', $mainSpan->id)
            ->where('child_id', $university->id)
            ->where('type_id', 'education')
            ->first();
        $this->assertNotNull($educationConnection);

        // Check work connection was created
        $company = Span::where('name', 'Test Company')->first();
        $this->assertNotNull($company);
        $this->assertEquals('organisation', $company->type_id);

        $workConnection = Connection::where('parent_id', $mainSpan->id)
            ->where('child_id', $company->id)
            ->where('type_id', 'employment')
            ->first();
        $this->assertNotNull($workConnection);

        // Check residence connection was created
        $place = Span::where('name', 'Test City')->first();
        $this->assertNotNull($place);
        $this->assertEquals('place', $place->type_id);

        $residenceConnection = Connection::where('parent_id', $mainSpan->id)
            ->where('child_id', $place->id)
            ->where('type_id', 'residence')
            ->first();
        $this->assertNotNull($residenceConnection);

        // Check relationship connection was created
        $friend = Span::where('name', 'Test Friend')->first();
        $this->assertNotNull($friend);
        $this->assertEquals('person', $friend->type_id);

        $relationshipConnection = Connection::where('parent_id', $mainSpan->id)
            ->where('child_id', $friend->id)
            ->where('type_id', 'relationship')
            ->first();
        $this->assertNotNull($relationshipConnection);
    }

    public function test_handles_invalid_yaml_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported span type: invalid_type');

        // Create an invalid YAML file
        $invalidYaml = [
            'name' => 'Test Person',
            'type' => 'invalid_type'
        ];
        file_put_contents($this->testYamlPath, Yaml::dump($invalidYaml));

        // Try to import - this should throw an exception
        $importer = SpanImporterFactory::create($this->testYamlPath, $this->user);
        $importer->import($this->testYamlPath);
    }

    public function test_handles_missing_required_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('YAML file must specify a type');

        // Create YAML without required fields
        $incompleteYaml = [
            'name' => 'Test Person'
            // Missing type field
        ];
        file_put_contents($this->testYamlPath, Yaml::dump($incompleteYaml));

        // Try to import - this should throw an exception
        $importer = SpanImporterFactory::create($this->testYamlPath, $this->user);
        $importer->import($this->testYamlPath);
    }

    public function test_updates_existing_span(): void
    {
        // First import
        $importer = SpanImporterFactory::create($this->testYamlPath, $this->user);
        $result = $importer->import($this->testYamlPath);
        $this->assertTrue($result['success']);

        // Modify YAML with updated information
        $yaml = Yaml::parseFile($this->testYamlPath);
        $yaml['education'][] = [
            'type' => 'education',
            'institution' => 'Another University',
            'start' => '2016',
            'end' => '2018'
        ];
        file_put_contents($this->testYamlPath, Yaml::dump($yaml, 4));

        // Second import
        $result = $importer->import($this->testYamlPath);
        
        // Check import was successful
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['errors']);

        // Check new education connection was added
        $mainSpan = Span::where('name', 'Test Person')->first();
        $anotherUniversity = Span::where('name', 'Another University')->first();
        $this->assertNotNull($anotherUniversity);

        $newEducationConnection = Connection::where('parent_id', $mainSpan->id)
            ->where('child_id', $anotherUniversity->id)
            ->where('type_id', 'education')
            ->first();
        $this->assertNotNull($newEducationConnection);
    }

    public function test_imports_band_yaml_file_successfully(): void
    {
        // Create a test band YAML file
        $bandYaml = [
            'name' => 'The Beatles',
            'type' => 'band',
            'start' => '1960-01-01',
            'end' => '1970-12-31',
            'metadata' => [
                'formation_location' => 'Liverpool, England'
            ],
            'members' => [
                [
                    'name' => 'John Lennon',
                    'role' => 'guitar, vocals',
                    'start' => '1960-01-01',
                    'end' => '1970-12-31'
                ],
                [
                    'name' => 'Paul McCartney',
                    'role' => 'bass, vocals',
                    'start' => '1960-01-01',
                    'end' => '1970-12-31'
                ],
                [
                    'name' => 'George Harrison',
                    'role' => 'guitar, vocals',
                    'start' => '1960-01-01',
                    'end' => '1970-12-31'
                ],
                [
                    'name' => 'Ringo Starr',
                    'role' => 'drums, vocals',
                    'start' => '1962-08-16',
                    'end' => '1970-12-31'
                ]
            ]
        ];

        $bandYamlPath = storage_path('app/test_band_import.yaml');
        file_put_contents($bandYamlPath, Yaml::dump($bandYaml, 4));

        try {
            // Create importer and import the band file
            $importer = SpanImporterFactory::create($bandYamlPath, $this->user);
            $result = $importer->import($bandYamlPath);

            // Print errors if any
            if (!empty($result['errors'])) {
                print_r($result['errors']);
            }

            // Check import was successful
            $this->assertTrue($result['success']);
            $this->assertEmpty($result['errors']);

            // Check band span was created
            $bandSpan = Span::where('name', 'The Beatles')->first();
            $this->assertNotNull($bandSpan);
            $this->assertEquals('band', $bandSpan->type_id);
            $this->assertEquals(1960, $bandSpan->start_year);
            $this->assertEquals(1970, $bandSpan->end_year);
            print_r(['band_span' => $bandSpan->toArray()]);

            // Check member spans were created
            $members = [
                'John Lennon',
                'Paul McCartney',
                'George Harrison',
                'Ringo Starr'
            ];

            foreach ($members as $memberName) {
                $memberSpan = Span::where('name', $memberName)->first();
                $this->assertNotNull($memberSpan);
                $this->assertEquals('person', $memberSpan->type_id);
                print_r(['member_span' => $memberSpan->toArray()]);

                // Check member connections were created
                $connection = Connection::where('parent_id', $memberSpan->id)
                    ->where('child_id', $bandSpan->id)
                    ->where('type_id', 'membership')
                    ->first();
                print_r(['connection' => $connection ? $connection->toArray() : null]);
                $this->assertNotNull($connection);
            }

            // Check Ringo's specific dates
            $ringoConnection = Connection::with('connectionSpan')
                ->where('parent_id', Span::where('name', 'Ringo Starr')->first()->id)
                ->where('child_id', $bandSpan->id)
                ->where('type_id', 'membership')
                ->first();
            $this->assertNotNull($ringoConnection);
            print_r(['ringo_connection' => $ringoConnection->toArray()]);
            print_r(['ringo_connection_span' => $ringoConnection->connectionSpan ? $ringoConnection->connectionSpan->toArray() : null]);
            $this->assertNotNull($ringoConnection->connectionSpan);
            $this->assertEquals(1962, $ringoConnection->connectionSpan->start_year);
            $this->assertEquals(8, $ringoConnection->connectionSpan->start_month);
            $this->assertEquals(16, $ringoConnection->connectionSpan->start_day);
            $this->assertEquals(1970, $ringoConnection->connectionSpan->end_year);
            $this->assertEquals(12, $ringoConnection->connectionSpan->end_month);
            $this->assertEquals(31, $ringoConnection->connectionSpan->end_day);

        } finally {
            // Clean up test file
            if (file_exists($bandYamlPath)) {
                unlink($bandYamlPath);
            }
        }
    }
}
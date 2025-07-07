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
        $this->markTestSkipped('YAML import functionality needs to be implemented');
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
        $this->markTestSkipped('YAML import functionality needs to be implemented');
    }

    public function test_imports_band_yaml_file_successfully(): void
    {
        $this->markTestSkipped('YAML import functionality needs to be implemented');
    }
}
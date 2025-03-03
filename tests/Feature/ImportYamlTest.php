<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Services\Import\SpanImporterFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

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
}
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\YamlValidationService;
use Tests\TestCase;

class YamlValidationTest extends TestCase
{

    public function test_virtual_incoming_connection_fields_are_ignored()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create validation service
        $validator = new YamlValidationService();
        
        // Test YAML with virtual incoming connection fields
        $yaml = [
            'name' => 'John Doe',
            'type' => 'person',
            'state' => 'complete',
            'start' => '1990-01-01',
            'connections' => [
                'relationship_incoming' => [
                    [
                        'name' => 'Jane Smith',
                        'type' => 'person',
                        'id' => '550e8400-e29b-41d4-a716-446655440001'
                    ]
                ],
                'family_incoming' => [
                    [
                        'name' => 'Bob Smith',
                        'type' => 'person',
                        'id' => '550e8400-e29b-41d4-a716-446655440002'
                    ]
                ],
                'employment' => [
                    [
                        'name' => 'Test Company',
                        'type' => 'organisation',
                        'id' => '550e8400-e29b-41d4-a716-446655440003'
                    ]
                ]
            ]
        ];
        
        // Convert to YAML string
        $yamlString = \Symfony\Component\Yaml\Yaml::dump($yaml, 10, 2, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        
        // Parse YAML back to array
        $parsedData = \Symfony\Component\Yaml\Yaml::parse($yamlString);
        
        // Validate the YAML
        $errors = $validator->validateSchema($parsedData);
        
        // Should be valid - virtual incoming fields should be ignored
        $this->assertEmpty($errors, 'YAML with virtual incoming connection fields should be valid: ' . implode(', ', $errors));
    }

    public function test_invalid_connection_types_are_rejected()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create validation service
        $validator = new YamlValidationService();
        
        // Test YAML with invalid connection type
        $yaml = [
            'name' => 'John Doe',
            'type' => 'person',
            'state' => 'complete',
            'start' => '1990-01-01',
            'connections' => [
                'invalid_connection_type' => [
                    [
                        'name' => 'Jane Smith',
                        'type' => 'person',
                        'id' => '550e8400-e29b-41d4-a716-446655440001'
                    ]
                ]
            ]
        ];
        
        // Convert to YAML string
        $yamlString = \Symfony\Component\Yaml\Yaml::dump($yaml, 10, 2, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        
        // Parse YAML back to array
        $parsedData = \Symfony\Component\Yaml\Yaml::parse($yamlString);
        
        // Validate the YAML
        $errors = $validator->validateSchema($parsedData);
        
        // Should be invalid
        $this->assertNotEmpty($errors, 'YAML with invalid connection type should be invalid');
        
        // Check that the error message mentions the invalid type
        $errorMessages = implode(' ', $errors);
        $this->assertStringContainsString('invalid_connection_type', $errorMessages);
    }
} 
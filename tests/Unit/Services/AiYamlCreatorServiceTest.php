<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AiYamlCreatorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class AiYamlCreatorServiceTest extends TestCase
{
    public function test_service_requires_openai_api_key()
    {
        // Clear the OpenAI API key
        Config::set('services.openai.api_key', null);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API key not configured');
        
        new AiYamlCreatorService();
    }

    public function test_validate_yaml_with_valid_yaml()
    {
        Config::set('services.openai.api_key', 'test-key');
        
        $service = new AiYamlCreatorService();
        
        $validYaml = "name: Test Person\ntype: person\nstate: placeholder";
        $result = $service->validateYaml($validYaml);
        
        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('parsed', $result);
    }

    public function test_validate_yaml_with_invalid_yaml()
    {
        Config::set('services.openai.api_key', 'test-key');
        
        $service = new AiYamlCreatorService();
        
        $invalidYaml = "name: Test Person\n  invalid: indentation";
        $result = $service->validateYaml($invalidYaml);
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_cache_key_generation()
    {
        Config::set('services.openai.api_key', 'test-key');
        
        $service = new AiYamlCreatorService();
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);
        
        $key1 = $method->invoke($service, 'John Doe', null);
        $key2 = $method->invoke($service, 'John Doe', 'the actor');
        $key3 = $method->invoke($service, 'john doe', null);
        
        // Same name should generate same key (case insensitive)
        $this->assertEquals($key1, $key3);
        
        // Different disambiguation should generate different key
        $this->assertNotEquals($key1, $key2);
    }

    public function test_clean_yaml_response()
    {
        Config::set('services.openai.api_key', 'test-key');
        
        $service = new AiYamlCreatorService();
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('cleanYamlResponse');
        $method->setAccessible(true);
        
        $responseWithMarkdown = "```yaml\nname: Test Person\ntype: person\n```";
        $cleaned = $method->invoke($service, $responseWithMarkdown);
        
        $this->assertEquals("name: Test Person\ntype: person", $cleaned);
        
        $responseWithText = "Here's the YAML:\nname: Test Person\ntype: person\nThat's it!";
        $cleaned = $method->invoke($service, $responseWithText);
        
        $this->assertEquals("name: Test Person\ntype: person", $cleaned);
    }

    public function test_fix_yaml_quoting()
    {
        Config::set('services.openai.api_key', 'test-key');
        
        $service = new AiYamlCreatorService();
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('fixYamlQuoting');
        $method->setAccessible(true);
        
        // Test YAML with special characters that need quoting
        $yamlWithSpecialChars = "name: The Smashing Pumpkins\ntype: band\nmetadata:\n  genres:\n    - Alternative rock\n    - Grunge\n  formation_location: Chicago, Illinois, United States\nsources:\n  - \"https://en.wikipedia.org/wiki/The_Smashing_Pumpkins\"\nconnections:\n  created:\n    - name: Shiny and Oh So Bright, Vol. 1 / LP: No Past. No Future. No Sun.\n      type: album\n      start: '2018'\n      end: '2018'";
        
        $fixed = $method->invoke($service, $yamlWithSpecialChars);
        
        // Check that values with special characters are properly quoted
        $this->assertStringContainsString('formation_location: "Chicago, Illinois, United States"', $fixed);
        $this->assertStringContainsString('name: "Shiny and Oh So Bright, Vol. 1 / LP: No Past. No Future. No Sun."', $fixed);
        
        // Check that simple values are not quoted
        $this->assertStringContainsString('name: The Smashing Pumpkins', $fixed);
        $this->assertStringContainsString('type: band', $fixed);
        $this->assertStringContainsString('start: \'2018\'', $fixed);
        
        // Test that already quoted values are preserved (including URLs with colons)
        $this->assertStringContainsString('- "https://en.wikipedia.org/wiki/The_Smashing_Pumpkins"', $fixed);
        
        // Test that already quoted values are preserved
        $yamlWithQuotes = "name: \"Already Quoted\"\ntype: band";
        $fixed = $method->invoke($service, $yamlWithQuotes);
        $this->assertEquals($yamlWithQuotes, $fixed);
    }
} 
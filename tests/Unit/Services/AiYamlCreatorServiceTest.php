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
} 
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Span;

class Basic extends TestCase
{
    /**
     * A basic test that doesn't require storage or complex database operations.
     */
    public function test_yaml_samples_exist(): void
    {
        // Simple assertion that doesn't require logging
        $yamlSamplesExist = file_exists(base_path('yaml-samples'));
        $this->assertTrue($yamlSamplesExist);
        
        // Count YAML files in the directory
        if ($yamlSamplesExist) {
            $yamlFiles = glob(base_path('yaml-samples') . '/*.yaml');
            $this->assertGreaterThan(0, count($yamlFiles));
            
            // Output the count (will show in test results)
            print("\nFound " . count($yamlFiles) . " YAML sample files\n");
        }
    }
}

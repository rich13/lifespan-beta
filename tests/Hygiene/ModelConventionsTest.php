<?php

namespace Tests\Hygiene;

use Tests\TestCase;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

class ModelConventionsTest extends TestCase
{
    private function getModelClasses(): array
    {
        $modelFiles = File::glob(app_path('Models/*.php'));
        $models = [];

        foreach ($modelFiles as $file) {
            $className = 'App\\Models\\' . pathinfo($file, PATHINFO_FILENAME);
            if (class_exists($className)) {
                $models[] = $className;
            }
        }

        return $models;
    }

    /**
     * Test that models using UUIDs declare it properly
     */
    public function test_uuid_models_declare_properly(): void
    {
        $violations = [];
        
        foreach ($this->getModelClasses() as $modelClass) {
            $reflection = new ReflectionClass($modelClass);
            
            // Skip models that don't use UUIDs
            if (!in_array('Illuminate\Database\Eloquent\Concerns\HasUuids', array_keys($reflection->getTraits()))) {
                continue;
            }

            // UUID models must declare both properties
            if (!$reflection->hasProperty('incrementing')) {
                $violations[] = "{$modelClass} uses UUIDs but doesn't declare \$incrementing = false";
            }
            if (!$reflection->hasProperty('keyType')) {
                $violations[] = "{$modelClass} uses UUIDs but doesn't declare \$keyType = 'string'";
            }
        }

        $this->assertEmpty($violations, implode(PHP_EOL, $violations));
    }

    /**
     * Test that all models protect against mass assignment
     */
    public function test_models_define_mass_assignment_protection(): void
    {
        $violations = [];

        foreach ($this->getModelClasses() as $modelClass) {
            $reflection = new ReflectionClass($modelClass);
            
            if (!$reflection->hasProperty('fillable') && !$reflection->hasProperty('guarded')) {
                $violations[] = "{$modelClass} should define either fillable or guarded properties";
            }
        }

        $this->assertEmpty($violations, implode(PHP_EOL, $violations));
    }

    /**
     * Test that all models have property documentation
     */
    public function test_models_define_property_types(): void
    {
        $violations = [];

        foreach ($this->getModelClasses() as $modelClass) {
            $reflection = new ReflectionClass($modelClass);
            $docComment = $reflection->getDocComment();

            if (!$docComment || !str_contains($docComment, '@property')) {
                $violations[] = "{$modelClass} should define property types in docblock";
            }
        }

        $this->assertEmpty($violations, implode(PHP_EOL, $violations));
    }

    /**
     * Test that models with JSON/boolean fields declare casts
     */
    public function test_models_declare_required_casts(): void
    {
        $violations = [];
        $requiresCasts = [
            'Span' => [
                'metadata' => 'array',
                'permissions' => 'integer',
                'start_precision' => 'integer',
                'end_precision' => 'integer',
                'permission_mode' => 'string'
            ],
            'User' => ['is_admin' => 'boolean'],
            'Connection' => ['metadata' => 'array'],
        ];

        foreach ($requiresCasts as $model => $expectedCasts) {
            $className = "App\\Models\\{$model}";
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if (!$reflection->hasProperty('casts')) {
                $violations[] = "{$className} should declare casts for: " . implode(', ', array_keys($expectedCasts));
                continue;
            }

            $instance = new $className;
            $actualCasts = $instance->getCasts();
            foreach ($expectedCasts as $field => $type) {
                if (!isset($actualCasts[$field])) {
                    $violations[] = "{$className} should cast {$field} as {$type}";
                }
            }
        }

        $this->assertEmpty($violations, implode(PHP_EOL, $violations));
    }
} 
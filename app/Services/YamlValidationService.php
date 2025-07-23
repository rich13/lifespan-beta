<?php

namespace App\Services;

use App\Models\SpanType;
use App\Models\ConnectionType;
use Illuminate\Support\Facades\Log;

class YamlValidationService
{
    /**
     * Validate YAML structure against database schema
     */
    public function validateSchema(array $data, ?string $currentSlug = null, ?\App\Models\Span $span = null): array
    {
        $errors = [];
        
        // Get base schema from database
        $baseSchema = $this->getBaseSchema();
        
        // Check for unknown fields
        foreach ($data as $field => $value) {
            if (!isset($baseSchema[$field])) {
                $errors[] = "Unknown field '{$field}'. Valid fields are: " . implode(', ', array_keys($baseSchema));
            }
        }
        
        // Check required fields
        foreach ($baseSchema as $field => $config) {
            if ($config['required'] && !isset($data[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }
        
        // Check data types
        foreach ($data as $field => $value) {
            if (isset($baseSchema[$field])) {
                $expectedType = $baseSchema[$field]['type'];
                $actualType = $this->getValueType($value);
                
                if (!$this->isTypeCompatible($actualType, $expectedType)) {
                    $errors[] = "Field '{$field}' should be of type '{$expectedType}', got '{$actualType}'";
                }
            }
        }
        
        // Validate metadata structure if present
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $metadataErrors = $this->validateMetadataStructure($data['metadata'], $data['type'] ?? null);
            $errors = array_merge($errors, $metadataErrors);
        }
        
        // Validate connections structure if present
        if (isset($data['connections']) && is_array($data['connections'])) {
            $connectionErrors = $this->validateConnectionSchema($data['connections']);
            $errors = array_merge($errors, $connectionErrors);
        }
        
        // After schema validation, enforce start date rules
        $state = $data['state'] ?? 'complete';
        
        // Validate start field only if not timeless
        $isTimeless = false;
        if (isset($data['type'])) {
            $spanTypeModel = \App\Models\SpanType::where('type_id', $data['type'])->first();
            $isTimeless = $spanTypeModel && ($spanTypeModel->metadata['timeless'] ?? false);
        }
        if ($state !== 'placeholder' && !$isTimeless) {
            if (!isset($data['start']) || $data['start'] === null || $data['start'] === '') {
                $errors[] = "Field 'start' is required unless state is 'placeholder' (current state: '{$state}')";
            } elseif (!in_array($this->getValueType($data['start']), ['string', 'integer'])) {
                $errors[] = "Field 'start' must be a string or integer (e.g. '1990' or 1990 or '1990-05-01')";
            }
        }
        
        // Validate slug uniqueness if provided and different from current slug
        if (isset($data['slug']) && !empty($data['slug'])) {
            $newSlug = $data['slug'];
            // Only validate uniqueness if the slug is actually changing
            if ($currentSlug === null || $newSlug !== $currentSlug) {
                // Use span ID if available, otherwise fall back to data['id']
                $currentSpanId = $span ? $span->id : ($data['id'] ?? null);
                $slugErrors = $this->validateSlugUniqueness($newSlug, $currentSpanId);
                $errors = array_merge($errors, $slugErrors);
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate YAML structure against database schema (for preview purposes - skips slug validation)
     */
    public function validateSchemaForPreview(array $data): array
    {
        $errors = [];
        
        // Get base schema from database
        $baseSchema = $this->getBaseSchema();
        
        // Check for unknown fields
        foreach ($data as $field => $value) {
            if (!isset($baseSchema[$field])) {
                $errors[] = "Unknown field '{$field}'. Valid fields are: " . implode(', ', array_keys($baseSchema));
            }
        }
        
        // Check required fields
        foreach ($baseSchema as $field => $config) {
            if ($config['required'] && !isset($data[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }
        
        // Check data types
        foreach ($data as $field => $value) {
            if (isset($baseSchema[$field])) {
                $expectedType = $baseSchema[$field]['type'];
                $actualType = $this->getValueType($value);
                
                if (!$this->isTypeCompatible($actualType, $expectedType)) {
                    $errors[] = "Field '{$field}' should be of type '{$expectedType}', got '{$actualType}'";
                }
            }
        }
        
        // Validate metadata structure if present
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $metadataErrors = $this->validateMetadataStructure($data['metadata'], $data['type'] ?? null);
            $errors = array_merge($errors, $metadataErrors);
        }
        
        // Validate connections structure if present
        if (isset($data['connections']) && is_array($data['connections'])) {
            $connectionErrors = $this->validateConnectionSchema($data['connections']);
            $errors = array_merge($errors, $connectionErrors);
        }
        
        // After schema validation, enforce start date rules
        $state = $data['state'] ?? 'complete';
        
        if ($state !== 'placeholder') {
            if (!isset($data['start']) || $data['start'] === null || $data['start'] === '') {
                $errors[] = "Field 'start' is required unless state is 'placeholder' (current state: '{$state}')";
            } elseif (!in_array($this->getValueType($data['start']), ['string', 'integer'])) {
                $errors[] = "Field 'start' must be a string or integer (e.g. '1990' or 1990 or '1990-05-01')";
            }
        }
        
        // Note: We skip slug validation for preview purposes since we're not creating a new span
        
        return $errors;
    }
    
    /**
     * Get base schema from database configuration
     */
    private function getBaseSchema(): array
    {
        return [
            'id' => ['type' => 'string', 'required' => false],
            'name' => ['type' => 'string', 'required' => true],
            'slug' => ['type' => 'string', 'required' => false],
            'type' => ['type' => 'string', 'required' => true],
            'connection_type' => ['type' => 'string', 'required' => false], // For connection spans
            'state' => ['type' => 'string', 'required' => false],
            'start' => ['type' => 'string|integer', 'required' => false],
            'end' => ['type' => 'string|integer|null', 'required' => false],
            'description' => ['type' => 'string|null', 'required' => false],
            'notes' => ['type' => 'string|null', 'required' => false],
            'metadata' => ['type' => 'array', 'required' => false],
            'sources' => ['type' => 'array|null', 'required' => false],
            'access_level' => ['type' => 'string', 'required' => false],
            'connections' => ['type' => 'array', 'required' => false],
        ];
    }
    
    /**
     * Validate metadata structure based on span type from database
     */
    private function validateMetadataStructure(array $metadata, ?string $spanType): array
    {
        $errors = [];
        
        if (!$spanType) {
            return $errors;
        }
        
        // Get span type from database
        $spanTypeModel = SpanType::where('type_id', $spanType)->first();
        if (!$spanTypeModel) {
            $errors[] = "Unknown span type '{$spanType}'";
            return $errors;
        }
        
        // Get metadata schema from database
        $metadataSchema = $spanTypeModel->metadata['schema'] ?? [];
        
        // Check for unknown metadata fields (warn instead of error)
        $warnings = [];
        foreach ($metadata as $field => $value) {
            if (!isset($metadataSchema[$field])) {
                $validFields = array_keys($metadataSchema);
                $warnings[] = "Unknown metadata field '{$field}' for span type '{$spanType}'. Valid fields are: " . implode(', ', $validFields);
            }
        }
        
        // Log warnings for debugging but don't fail validation
        if (!empty($warnings)) {
            \Log::info('YAML metadata warnings: ' . implode('; ', $warnings));
        }
        
        // Check data types and required fields
        foreach ($metadataSchema as $field => $config) {
            if (isset($metadata[$field])) {
                $expectedType = $this->mapSchemaTypeToValidationType($config['type'] ?? 'text');
                $actualType = $this->getValueType($metadata[$field]);
                
                if (!$this->isTypeCompatible($actualType, $expectedType)) {
                    $errors[] = "Metadata field '{$field}' should be of type '{$expectedType}', got '{$actualType}'";
                }
            } elseif ($config['required'] ?? false) {
                $errors[] = "Required metadata field '{$field}' is missing for span type '{$spanType}'";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate connection schema structure using database connection types
     */
    private function validateConnectionSchema(array $connections): array
    {
        $errors = [];
        
        // Get valid connection types from database
        $validConnectionTypes = ConnectionType::pluck('type')->toArray();
        $validConnectionTypes = array_merge($validConnectionTypes, ['parents', 'children']); // Special family handling
        
        // Define connection item schema
        $connectionItemSchema = [
            'name' => ['type' => 'string', 'required' => true],
            'id' => ['type' => 'string', 'required' => false],
            'type' => ['type' => 'string', 'required' => true],
            'connection_type' => ['type' => 'string', 'required' => false], // For connection spans
            'connection_id' => ['type' => 'string', 'required' => false],
            'start' => ['type' => 'string|integer|null', 'required' => false],
            'end' => ['type' => 'string|integer|null', 'required' => false],
            'state' => ['type' => 'string', 'required' => false],
            'metadata' => ['type' => 'array', 'required' => false],
            'nested_connections' => ['type' => 'array', 'required' => false],
        ];
        
        foreach ($connections as $connectionType => $connectionList) {
            // Handle virtual connection fields (incoming connections)
            if (str_ends_with($connectionType, '_incoming')) {
                // These are virtual fields that represent incoming connections
                // They should be read-only and not validated as regular connection types
                continue;
            }
            
            // Validate connection type
            if (!in_array($connectionType, $validConnectionTypes)) {
                $errors[] = "Unknown connection type '{$connectionType}'. Valid types are: " . implode(', ', $validConnectionTypes);
                continue;
            }
            
            if (!is_array($connectionList)) {
                $errors[] = "Connection type '{$connectionType}' must be an array";
                continue;
            }
            
            foreach ($connectionList as $index => $connection) {
                if (!is_array($connection)) {
                    $errors[] = "Connection {$index} in '{$connectionType}' must be an array";
                    continue;
                }
                
                // Check for unknown fields
                foreach ($connection as $field => $value) {
                    if (!isset($connectionItemSchema[$field])) {
                        $errors[] = "Unknown field '{$field}' in connection {$index} of type '{$connectionType}'. Valid fields are: " . implode(', ', array_keys($connectionItemSchema));
                    }
                }
                
                // Check required fields
                foreach ($connectionItemSchema as $field => $config) {
                    if ($config['required'] && !isset($connection[$field])) {
                        $errors[] = "Required field '{$field}' is missing in connection {$index} of type '{$connectionType}'";
                    }
                }
                
                // Check data types
                foreach ($connection as $field => $value) {
                    if (isset($connectionItemSchema[$field])) {
                        $expectedType = $connectionItemSchema[$field]['type'];
                        $actualType = $this->getValueType($value);
                        
                        if (!$this->isTypeCompatible($actualType, $expectedType)) {
                            $errors[] = "Field '{$field}' in connection {$index} of type '{$connectionType}' should be of type '{$expectedType}', got '{$actualType}'";
                        }
                    }
                }
                
                // Validate nested connections if present
                if (isset($connection['nested_connections']) && is_array($connection['nested_connections'])) {
                    $nestedErrors = $this->validateNestedConnectionSchema($connection['nested_connections'], $connectionType, $index);
                    $errors = array_merge($errors, $nestedErrors);
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate nested connection schema structure
     */
    private function validateNestedConnectionSchema(array $nestedConnections, string $connectionType, int $connectionIndex): array
    {
        $errors = [];
        
        $nestedSchema = [
            'type' => ['type' => 'string', 'required' => true],
            'direction' => ['type' => 'string', 'required' => false],
            'target_name' => ['type' => 'string', 'required' => true],
            'target_id' => ['type' => 'string', 'required' => false],
            'target_type' => ['type' => 'string', 'required' => true],
            'start_date' => ['type' => 'string|integer|null', 'required' => false],
            'end_date' => ['type' => 'string|integer|null', 'required' => false],
        ];
        
        foreach ($nestedConnections as $index => $nestedConnection) {
            if (!is_array($nestedConnection)) {
                $errors[] = "Nested connection {$index} in connection {$connectionIndex} of type '{$connectionType}' must be an array";
                continue;
            }
            
            // Check for unknown fields
            foreach ($nestedConnection as $field => $value) {
                if (!isset($nestedSchema[$field])) {
                    $errors[] = "Unknown field '{$field}' in nested connection {$index} of connection {$connectionIndex} in type '{$connectionType}'. Valid fields are: " . implode(', ', array_keys($nestedSchema));
                }
            }
            
            // Check required fields
            foreach ($nestedSchema as $field => $config) {
                if ($config['required'] && !isset($nestedConnection[$field])) {
                    $errors[] = "Required field '{$field}' is missing in nested connection {$index} of connection {$connectionIndex} in type '{$connectionType}'";
                }
            }
            
            // Check data types
            foreach ($nestedConnection as $field => $value) {
                if (isset($nestedSchema[$field])) {
                    $expectedType = $nestedSchema[$field]['type'];
                    $actualType = $this->getValueType($value);
                    
                    if (!$this->isTypeCompatible($actualType, $expectedType)) {
                        $errors[] = "Field '{$field}' in nested connection {$index} of connection {$connectionIndex} in type '{$connectionType}' should be of type '{$expectedType}', got '{$actualType}'";
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate that a slug is unique across the system
     */
    private function validateSlugUniqueness(string $slug, ?string $currentSpanId = null): array
    {
        $errors = [];
        
        // Check if slug is valid format (alphanumeric, hyphens, underscores only)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            $errors[] = "Slug '{$slug}' contains invalid characters. Only letters, numbers, hyphens, and underscores are allowed.";
            return $errors;
        }
        
        // Check for uniqueness in the database
        $query = \App\Models\Span::where('slug', $slug);
        
        // Exclude the current span if this is an update
        if ($currentSpanId) {
            $query->where('id', '!=', $currentSpanId);
        }
        
        $existingSpan = $query->first();
        
        if ($existingSpan) {
            $errors[] = "Slug '{$slug}' is already in use by span '{$existingSpan->name}' (ID: {$existingSpan->id})";
        }
        
        return $errors;
    }
    
    /**
     * Get the type of a value for validation
     */
    private function getValueType($value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_string($value)) {
            return 'string';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_array($value)) {
            return 'array';
        }
        return 'unknown';
    }
    
    /**
     * Check if actual type is compatible with expected type
     */
    private function isTypeCompatible(string $actualType, string $expectedType): bool
    {
        // Handle nullable types (e.g., 'string|null', 'array|null')
        if (str_contains($expectedType, '|')) {
            $allowedTypes = explode('|', $expectedType);
            return in_array($actualType, $allowedTypes);
        }
        
        return $actualType === $expectedType;
    }
    
    /**
     * Map database schema types to validation types
     */
    private function mapSchemaTypeToValidationType(string $schemaType): string
    {
        $typeMap = [
            'text' => 'string',
            'textarea' => 'string',
            'select' => 'string',
            'span' => 'string', // Span references are stored as UUIDs (strings)
            'array' => 'array',
            'boolean' => 'boolean',
            'integer' => 'integer',
            'float' => 'float',
        ];
        
        return $typeMap[$schemaType] ?? 'string';
    }
    
    /**
     * Validate span types against database
     */
    public function validateSpanType(string $spanType): bool
    {
        return SpanType::where('type_id', $spanType)->exists();
    }
    
    /**
     * Validate connection types against database
     */
    public function validateConnectionType(string $connectionType): bool
    {
        // Handle special family connection types
        if (in_array($connectionType, ['parents', 'children'])) {
            return true;
        }
        
        return ConnectionType::where('type', $connectionType)->exists();
    }
    
    /**
     * Get all valid span types from database
     */
    public function getValidSpanTypes(): array
    {
        return SpanType::pluck('type_id')->toArray();
    }
    
    /**
     * Get all valid connection types from database
     */
    public function getValidConnectionTypes(): array
    {
        $types = ConnectionType::pluck('type')->toArray();
        return array_merge($types, ['parents', 'children']); // Include special family types
    }
    
    /**
     * Get all valid connection types including virtual incoming types
     */
    public function getAllConnectionTypes(): array
    {
        $baseTypes = $this->getValidConnectionTypes();
        $incomingTypes = array_map(function($type) {
            return $type . '_incoming';
        }, $baseTypes);
        
        return array_merge($baseTypes, $incomingTypes);
    }
} 
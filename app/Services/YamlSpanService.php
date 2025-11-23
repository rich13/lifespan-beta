<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\SpanType;
use App\Services\Import\Connections\ConnectionImporter;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Service for converting spans to/from YAML format for the YAML editor
 * Handles comprehensive span data including all connections and metadata
 */
class YamlSpanService
{
    /**
     * Convert a span to YAML format
     */
    public function spanToYaml(Span $span): string
    {
        $data = $this->spanToArray($span);
        
        // Use a custom YAML dump that ensures dates are quoted
        return $this->dumpYamlWithQuotedDates($data, 4, 2);
    }

    /**
     * Custom YAML dump that ensures date strings are quoted
     */
    private function dumpYamlWithQuotedDates($data, $inline = 4, $indent = 2, $level = 0): string
    {
        if (is_array($data)) {
            $result = '';
            $isSequential = array_keys($data) === range(0, count($data) - 1);
            
            // Handle empty arrays - always output them
            if (empty($data)) {
                return '{}';
            }
            
            foreach ($data as $key => $value) {
                $indentation = str_repeat(' ', $level * $indent);
                
                if ($isSequential) {
                    $result .= $indentation . "- ";
                } else {
                    $result .= $indentation . $key . ": ";
                }
                
                if (is_array($value)) {
                    if (empty($value)) {
                        $result .= "{}\n";
                    } else {
                        $result .= "\n" . $this->dumpYamlWithQuotedDates($value, $inline, $indent, $level + 1);
                    }
                } else {
                    $result .= $this->formatValue($value) . "\n";
                }
            }
            
            return $result;
        }
        
        return $this->formatValue($data);
    }
    
    /**
     * Format a value for YAML output, ensuring dates are quoted
     */
    private function formatValue($value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        if (is_string($value)) {
            // If it looks like a date, quote it to prevent YAML math interpretation
            if ($this->looksLikeDate($value)) {
                return "'" . $value . "'";
            }
            
            // Quote strings that contain special characters or could be interpreted as other types
            if (preg_match('/[^a-zA-Z0-9\s\-_\.]/', $value) || 
                is_numeric($value) || 
                in_array(strtolower($value), ['true', 'false', 'null', 'yes', 'no', 'on', 'off'])) {
                return "'" . str_replace("'", "''", $value) . "'";
            }
            
            return $value;
        }
        
        return (string) $value;
    }

    /**
     * Convert a span to array format (for YAML conversion)
     */
    public function spanToArray(Span $span): array
    {
        try {
            // Load all necessary relationships including connection spans for dates
            // Note: We don't load 'type' relationship to avoid expanding type field to full object
            $span->load([
                'connectionsAsSubject.child.type',
                'connectionsAsObject.parent.type', 
                'connectionsAsSubject.type',
                'connectionsAsObject.type',
                'connectionsAsSubject.connectionSpan',
                'connectionsAsObject.connectionSpan',
                'owner'
            ]);

            // Special handling for connection spans
            if ($span->type_id === 'connection') {
                return $this->connectionSpanToArray($span);
            }

            $data = [
                'name' => $span->name,
                'slug' => $span->slug,
                'type' => $span->type_id, // Always use type_id, not the loaded relationship
                'state' => $span->state,
                'description' => $span->description,
                'notes' => $span->notes,
                'metadata' => $span->metadata ?? [],
                'sources' => $span->sources,
                'access_level' => $span->access_level,
            ];
            
            // Add start and end as strings if present
            $start = $span->getFormattedStartDateAttribute();
            if ($start) {
                $data['start'] = $start;
            }
            $end = $span->getFormattedEndDateAttribute();
            if ($end) {
                $data['end'] = $end;
            }

            // Add optional fields
            if ($span->description) {
                $data['description'] = $span->description;
            }
            if ($span->notes) {
                $data['notes'] = $span->notes;
            }

            // Add metadata (always include for consistency)
            $data['metadata'] = $span->metadata ?? [];
            
            // Ensure required metadata fields are present for place spans
            if ($span->type_id === 'place' && !isset($data['metadata']['subtype'])) {
                $data['metadata']['subtype'] = 'city_district'; // Default to a common place type
            }
            
            // Ensure required metadata fields are present for organisation spans
            if ($span->type_id === 'organisation' && !isset($data['metadata']['subtype'])) {
                $data['metadata']['subtype'] = 'corporation'; // Default to a common organisation type
            }
            
            // Ensure required metadata fields are present for person spans
            if ($span->type_id === 'person' && !isset($data['metadata']['subtype'])) {
                $data['metadata']['subtype'] = 'private_individual'; // Default to private individual
            }

            // Add sources if present
            if (!empty($span->sources)) {
                $data['sources'] = $span->sources;
            }

            // Add access control information
            $data['access_level'] = $span->access_level;

            // Group connections by type with special handling for family relationships
            $connections = [];
        
            // Process outgoing connections (where this span is the subject/parent)
            foreach ($span->connectionsAsSubject as $connection) {
                $connectionType = $connection->type_id;
                $targetSpan = $connection->child;
                
                $connectionData = [
                    'name' => $targetSpan->name,
                    'id' => $targetSpan->id,
                    'type' => $targetSpan->type_id, // Always use type_id, not the loaded relationship
                    'connection_id' => $connection->id,
                ];
                // Always include metadata from the connected span if present
                if (!empty($targetSpan->metadata)) {
                    $connectionData['metadata'] = $targetSpan->metadata;
                }
                // Add span dates and state
                $start = $targetSpan->getFormattedStartDateAttribute();
                if ($start) {
                    $connectionData['start'] = $start;
                }
                $end = $targetSpan->getFormattedEndDateAttribute();
                if ($end) {
                    $connectionData['end'] = $end;
                }
                if ($targetSpan->state) {
                    $connectionData['state'] = $targetSpan->state;
                }
                
                // Add connection dates from the connection span if available
                if ($connection->connectionSpan) {
                    $this->addConnectionDates($connectionData, $connection->connectionSpan);
                    
                    // Add nested connections from the connection span
                    $nestedConnections = $this->getNestedConnections($connection->connectionSpan);
                    if (!empty($nestedConnections)) {
                        $connectionData['nested_connections'] = $nestedConnections;
                    }
                    
                    // Special handling for has_role connections: extract dates from nested at_organisation connection span
                    if ($connectionType === 'has_role' && !isset($connectionData['start']) && !isset($connectionData['end'])) {
                        $this->extractDatesFromNestedConnections($connectionData, $connection->connectionSpan);
                    }
                }
                
                // Add connection metadata if present
                if (!empty($connection->metadata)) {
                    $connectionData['connection_metadata'] = $connection->metadata;
                }
                
                // Special handling for family connections
                if ($connectionType === 'family') {
                    // For outgoing family connections, these are children
                    if (!isset($connections['children'])) {
                        $connections['children'] = [];
                    }
                    $connections['children'][] = $connectionData;
                } else {
                    // Regular connection handling for non-family types
                    if (!isset($connections[$connectionType])) {
                        $connections[$connectionType] = [];
                    }
                    $connections[$connectionType][] = $connectionData;
                }
            }
        
            // Process incoming connections (where this span is the object/child)
            foreach ($span->connectionsAsObject as $connection) {
                $connectionType = $connection->type_id;
                $sourceSpan = $connection->parent;
                
                $connectionData = [
                    'name' => $sourceSpan->name,
                    'id' => $sourceSpan->id,
                    'type' => $sourceSpan->type_id,
                    'connection_id' => $connection->id,
                ];
                // Always include metadata from the connected span if present
                if (!empty($sourceSpan->metadata)) {
                    $connectionData['metadata'] = $sourceSpan->metadata;
                }
                // Add span dates and state
                $start = $sourceSpan->getFormattedStartDateAttribute();
                if ($start) {
                    $connectionData['start'] = $start;
                }
                $end = $sourceSpan->getFormattedEndDateAttribute();
                if ($end) {
                    $connectionData['end'] = $end;
                }
                if ($sourceSpan->state) {
                    $connectionData['state'] = $sourceSpan->state;
                }
                
                // Add connection dates from the connection span if available
                if ($connection->connectionSpan) {
                    $this->addConnectionDates($connectionData, $connection->connectionSpan);
                    
                    // Add nested connections from the connection span
                    $nestedConnections = $this->getNestedConnections($connection->connectionSpan);
                    if (!empty($nestedConnections)) {
                        $connectionData['nested_connections'] = $nestedConnections;
                    }
                    
                    // Special handling for has_role connections: extract dates from nested at_organisation connection span
                    if ($connectionType === 'has_role' && !isset($connectionData['start']) && !isset($connectionData['end'])) {
                        $this->extractDatesFromNestedConnections($connectionData, $connection->connectionSpan);
                    }
                }
                
                // Add connection metadata if present
                if (!empty($connection->metadata)) {
                    $connectionData['connection_metadata'] = $connection->metadata;
                }
                
                // Special handling for family connections
                if ($connectionType === 'family') {
                    // For incoming family connections, these are parents
                    if (!isset($connections['parents'])) {
                        $connections['parents'] = [];
                    }
                    $connections['parents'][] = $connectionData;
                } else {
                    // For non-family incoming connections, use the original approach
                    $incomingKey = $connectionType . '_incoming';
                    if (!isset($connections[$incomingKey])) {
                        $connections[$incomingKey] = [];
                    }
                    $connections[$incomingKey][] = $connectionData;
                }
            }

            // Add connections to data if any exist
            if (!empty($connections)) {
                $data['connections'] = $connections;
            }

            return $data;
            
        } catch (\Exception $e) {
            Log::error('Failed to convert span to array', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'span_type' => $span->type_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return a minimal safe representation
            return [
                'id' => $span->id,
                'name' => $span->name,
                'type' => $span->type_id,
                'state' => $span->state,
                'description' => $span->description,
                'notes' => $span->notes,
                'metadata' => $span->metadata ?? [],
                'sources' => $span->sources ?? [],
                'access_level' => $span->access_level,
                'error' => 'Serialization failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Convert a connection span to array format (for YAML conversion)
     */
    private function connectionSpanToArray(Span $span): array
    {
        // For connection spans, we need to find the related connection to get the connection type
        $connection = null;
        
        // Check if this span is a connection span for any connection
        $connection = \App\Models\Connection::where('connection_span_id', $span->id)->first();
        
        if (!$connection) {
            // If no connection found, treat as regular span but remove connection_type from metadata
            $metadata = $span->metadata ?? [];
            unset($metadata['connection_type']);
            
            $data = [
                'name' => $span->name,
                'slug' => $span->slug,
                'type' => $span->type_id,
                'state' => $span->state,
                'description' => $span->description,
                'notes' => $span->notes,
                'metadata' => $metadata,
                'sources' => $span->sources,
                'access_level' => $span->access_level,
            ];
            
            // Add start and end as strings if present
            $start = $span->getFormattedStartDateAttribute();
            if ($start) {
                $data['start'] = $start;
            }
            $end = $span->getFormattedEndDateAttribute();
            if ($end) {
                $data['end'] = $end;
            }
            
            return $data;
        }
        
        // For connection spans with a related connection, add connection_type as a root field
        $metadata = $span->metadata ?? [];
        unset($metadata['connection_type']); // Remove from metadata since it's now a root field
        
        $data = [
            'name' => $span->name,
            'slug' => $span->slug,
            'type' => $span->type_id,
            'connection_type' => $connection->type_id, // Add as root field
            'state' => $span->state,
            'description' => $span->description,
            'notes' => $span->notes,
            'metadata' => $metadata,
            'sources' => $span->sources,
            'access_level' => $span->access_level,
        ];
        
        // Add start and end as strings if present
        $start = $span->getFormattedStartDateAttribute();
        if ($start) {
            $data['start'] = $start;
        }
        $end = $span->getFormattedEndDateAttribute();
        if ($end) {
            $data['end'] = $end;
        }
        
        // Process connections for connection spans (they can have their own connections)
        $connections = [];
        
        // Process outgoing connections (where this span is the subject/parent)
        foreach ($span->connectionsAsSubject as $connection) {
            $connectionType = $connection->type_id;
            $targetSpan = $connection->child;
            
            $connectionData = [
                'name' => $targetSpan->name,
                'id' => $targetSpan->id,
                'type' => $targetSpan->type_id,
                'connection_id' => $connection->id,
            ];
            // Always include metadata from the connected span if present
            if (!empty($targetSpan->metadata)) {
                $connectionData['metadata'] = $targetSpan->metadata;
            }
            // Add span dates and state
            $start = $targetSpan->getFormattedStartDateAttribute();
            if ($start) {
                $connectionData['start'] = $start;
            }
            $end = $targetSpan->getFormattedEndDateAttribute();
            if ($end) {
                $connectionData['end'] = $end;
            }
            if ($targetSpan->state) {
                $connectionData['state'] = $targetSpan->state;
            }
            
            // Add connection dates from the connection span if available
            if ($connection->connectionSpan) {
                $this->addConnectionDates($connectionData, $connection->connectionSpan);
                
                // Add nested connections from the connection span
                $nestedConnections = $this->getNestedConnections($connection->connectionSpan);
                if (!empty($nestedConnections)) {
                    $connectionData['nested_connections'] = $nestedConnections;
                }
            }
            
            // Add connection metadata if present
            if (!empty($connection->metadata)) {
                $connectionData['connection_metadata'] = $connection->metadata;
            }
            
            if (!isset($connections[$connectionType])) {
                $connections[$connectionType] = [];
            }
            $connections[$connectionType][] = $connectionData;
        }
        
        // Process incoming connections (where this span is the object/child)
        foreach ($span->connectionsAsObject as $connection) {
            $connectionType = $connection->type_id;
            $sourceSpan = $connection->parent;
            
            $connectionData = [
                'name' => $sourceSpan->name,
                'id' => $sourceSpan->id,
                'type' => $sourceSpan->type_id,
                'connection_id' => $connection->id,
            ];
            // Always include metadata from the connected span if present
            if (!empty($sourceSpan->metadata)) {
                $connectionData['metadata'] = $sourceSpan->metadata;
            }
            // Add span dates and state
            $start = $sourceSpan->getFormattedStartDateAttribute();
            if ($start) {
                $connectionData['start'] = $start;
            }
            $end = $sourceSpan->getFormattedEndDateAttribute();
            if ($end) {
                $connectionData['end'] = $end;
            }
            if ($sourceSpan->state) {
                $connectionData['state'] = $sourceSpan->state;
            }
            
            // Add connection dates from the connection span if available
            if ($connection->connectionSpan) {
                $this->addConnectionDates($connectionData, $connection->connectionSpan);
                
                // Add nested connections from the connection span
                $nestedConnections = $this->getNestedConnections($connection->connectionSpan);
                if (!empty($nestedConnections)) {
                    $connectionData['nested_connections'] = $nestedConnections;
                }
            }
            
            // Add connection metadata if present
            if (!empty($connection->metadata)) {
                $connectionData['connection_metadata'] = $connection->metadata;
            }
            
            // For non-family incoming connections, use the _incoming suffix
            $incomingKey = $connectionType . '_incoming';
            if (!isset($connections[$incomingKey])) {
                $connections[$incomingKey] = [];
            }
            $connections[$incomingKey][] = $connectionData;
        }
        
        // Add connections to data if any exist
        if (!empty($connections)) {
            $data['connections'] = $connections;
        }
        
        return $data;
    }

    /**
     * Convert YAML string to span data (validation only, no database writes)
     */
    public function yamlToSpanData(string $yamlContent, ?string $currentSlug = null, ?Span $span = null): array
    {
        try {
            $data = Yaml::parse($yamlContent);
            
            if (!is_array($data)) {
                return [
                    'success' => false,
                    'errors' => ['YAML must parse to an array']
                ];
            }
            
            // Normalize AI-generated data (e.g., convert JSON strings to arrays)
            $this->normalizeAiGeneratedData($data);
            
            // Filter out unsupported connection types before validation
            $this->filterUnsupportedConnections($data);
            
            // Use the new validation service for schema validation
            $validationService = new YamlValidationService();
            $schemaErrors = $validationService->validateSchema($data, $currentSlug, $span);
            
            if (!empty($schemaErrors)) {
                return [
                    'success' => false,
                    'errors' => $schemaErrors
                ];
            }
            
            // Then validate required fields
            $this->validateRequiredFields($data);
            
            // Validate and normalize dates
            $this->validateAndNormalizeDates($data);
            
            // Validate connections
            $this->validateConnections($data);
            
            // Analyze the impacts if we have a span context
            $impacts = [];
            if ($span) {
                $impacts = $this->analyzeChangeImpacts($data, $span);
            }
            
            return [
                'success' => true,
                'data' => $data,
                'impacts' => $impacts
            ];
            
        } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
            return [
                'success' => false,
                'errors' => ['Invalid YAML syntax: ' . $e->getMessage()]
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'errors' => [ $e->getMessage() ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Unexpected error: ' . $e->getMessage()]
            ];
        }
    }
    
    /**
     * Validate YAML structure against schema
     */
    private function validateSchemaStructure(array $data): void
    {
        $errors = [];
        
        // Define the schema structure
        $schema = [
            'id' => ['type' => 'string', 'required' => false],
            'name' => ['type' => 'string', 'required' => true],
            'type' => ['type' => 'string', 'required' => true],
            'state' => ['type' => 'string', 'required' => true],
            'start' => ['type' => 'string', 'required' => false],
            'end' => ['type' => 'string|null', 'required' => false],
            'description' => ['type' => 'string', 'required' => false],
            'notes' => ['type' => 'string', 'required' => false],
            'metadata' => ['type' => 'array', 'required' => false],
            'sources' => ['type' => 'array', 'required' => false],
            'access_level' => ['type' => 'string', 'required' => false],
            'permissions' => ['type' => 'integer', 'required' => false],
            'permission_mode' => ['type' => 'string', 'required' => false],
            'connections' => ['type' => 'array', 'required' => false],
        ];
        
        // Check for unknown fields
        foreach ($data as $field => $value) {
            if (!isset($schema[$field])) {
                $errors[] = "Unknown field '{$field}'. Valid fields are: " . implode(', ', array_keys($schema));
            }
        }
        
        // Check required fields
        foreach ($schema as $field => $config) {
            if ($config['required'] && !isset($data[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }
        
        // Check data types
        foreach ($data as $field => $value) {
            if (isset($schema[$field])) {
                $expectedType = $schema[$field]['type'];
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
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException("Schema validation failed:\n" . implode("\n", $errors));
        }
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
        if ($expectedType === 'string|null') {
            return in_array($actualType, ['string', 'null']);
        }
        return $actualType === $expectedType;
    }
    
    /**
     * Validate metadata structure based on span type
     */
    private function validateMetadataStructure(array $metadata, ?string $spanType): array
    {
        $errors = [];
        $warnings = [];
        
        // Define metadata schemas for each span type
        $metadataSchemas = [
            'person' => [
                'gender' => ['type' => 'string', 'required' => false],
                'birth_name' => ['type' => 'string', 'required' => false],
                'nationality' => ['type' => 'string', 'required' => false],
                'occupation' => ['type' => 'string', 'required' => false],
            ],
            'organisation' => [
                'org_type' => ['type' => 'string', 'required' => false],
                'industry' => ['type' => 'string', 'required' => false],
                'size' => ['type' => 'string', 'required' => false],
            ],
            'place' => [
                'place_type' => ['type' => 'string', 'required' => false],
                'coordinates' => ['type' => 'string', 'required' => false],
                'country' => ['type' => 'string', 'required' => false],
            ],
            'event' => [
                'event_type' => ['type' => 'string', 'required' => false],
                'significance' => ['type' => 'string', 'required' => false],
                'location' => ['type' => 'string', 'required' => false],
            ],
            'thing' => [
                'creator' => ['type' => 'string', 'required' => false],
                'subtype' => ['type' => 'string', 'required' => false],
            ],
        ];
        
        if ($spanType && isset($metadataSchemas[$spanType])) {
            $schema = $metadataSchemas[$spanType];
            
            // Check for unknown metadata fields (warn instead of error)
            foreach ($metadata as $field => $value) {
                if (!isset($schema[$field])) {
                    $warnings[] = "Unknown metadata field '{$field}' for span type '{$spanType}'. Valid fields are: " . implode(', ', array_keys($schema));
                }
            }
            
            // Check data types for known fields
            foreach ($metadata as $field => $value) {
                if (isset($schema[$field])) {
                    $expectedType = $schema[$field]['type'];
                    $actualType = $this->getValueType($value);
                    
                    if (!$this->isTypeCompatible($actualType, $expectedType)) {
                        $errors[] = "Metadata field '{$field}' should be of type '{$expectedType}', got '{$actualType}'";
                    }
                }
            }
        }
        
        // Log warnings for debugging but don't fail validation
        if (!empty($warnings)) {
            \Log::info('YAML metadata warnings: ' . implode('; ', $warnings));
        }
        
        return $errors;
    }
    
    /**
     * Validate connection schema structure
     */
    private function validateConnectionSchema(array $connections): array
    {
        $errors = [];
        
        // Define connection item schema
        $connectionItemSchema = [
            'name' => ['type' => 'string', 'required' => true],
            'id' => ['type' => 'string', 'required' => false],
            'type' => ['type' => 'string', 'required' => true],
            'connection_type' => ['type' => 'string', 'required' => false], // For connection spans
            'connection_id' => ['type' => 'string', 'required' => false],
            'start_date' => ['type' => 'string', 'required' => false],
            'end_date' => ['type' => 'string', 'required' => false],
            'metadata' => ['type' => 'array', 'required' => false],
            'nested_connections' => ['type' => 'array', 'required' => false],
        ];
        
        foreach ($connections as $connectionType => $connectionList) {
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
                if (isset($connection['nested_connections'])) {
                    if (!is_array($connection['nested_connections'])) {
                        $errors[] = "Field 'nested_connections' in connection {$index} of type '{$connectionType}' must be an array";
                    } else {
                        $nestedErrors = $this->validateNestedConnectionSchema($connection['nested_connections'], $connectionType, $index);
                        $errors = array_merge($errors, $nestedErrors);
                    }
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
        
        // Debug: Log what we're validating
        \Log::debug('YamlSpanService validating nested connections', [
            'connection_type' => $connectionType,
            'connection_index' => $connectionIndex,
            'nested_connections_structure' => $nestedConnections,
            'nested_connections_count' => count($nestedConnections)
        ]);
        
        $nestedSchema = [
            'type' => ['type' => 'string', 'required' => true],
            'direction' => ['type' => 'string', 'required' => false],
            'target_name' => ['type' => 'string', 'required' => true],
            'target_id' => ['type' => 'string', 'required' => false],
            'target_type' => ['type' => 'string', 'required' => true],
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
                        // Special handling for nested connection fields that should be strings but are arrays
                        if ($actualType === 'array' && $expectedType === 'string') {
                            $errors[] = "Nested connection {$field} in connection {$connectionIndex} of type '{$connectionType}' should be a string, not an array. Check YAML structure - field values should be quoted strings, not arrays.";
                        } else {
                            $errors[] = "Field '{$field}' in nested connection {$index} of connection {$connectionIndex} in type '{$connectionType}' should be of type '{$expectedType}', got '{$actualType}'";
                        }
                    }
                }
            }
        }
        
        return $errors;
    }

    /**
     * Apply validated YAML data to a span (writes to database)
     */
    public function applyYamlToSpan(Span $span, array $validatedData): array
    {
        try {
            DB::beginTransaction();
            
            // Update basic span fields
            $span->update([
                'name' => $validatedData['name'],
                'slug' => $validatedData['slug'] ?? null,
                'type_id' => $validatedData['type'],
                'state' => $validatedData['state'] ?? 'complete',
                'description' => $validatedData['description'] ?? null,
                'notes' => $validatedData['notes'] ?? null,
                'metadata' => $validatedData['metadata'] ?? [],
                'sources' => $validatedData['sources'] ?? null,
                'start_year' => $validatedData['start_year'] ?? null,
                'start_month' => $validatedData['start_month'] ?? null,
                'start_day' => $validatedData['start_day'] ?? null,
                'end_year' => $validatedData['end_year'] ?? null,
                'end_month' => $validatedData['end_month'] ?? null,
                'end_day' => $validatedData['end_day'] ?? null,
                'access_level' => $validatedData['access_level'] ?? $span->access_level,
            ]);

            // Handle connections if present
            if (isset($validatedData['connections'])) {
                $this->updateConnections($span, $validatedData['connections']);
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Span updated successfully from YAML'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to apply YAML to span', [
                'span_id' => $span->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update span: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Add connection dates from a connection span to the connection data
     */
    private function addConnectionDates(array &$connectionData, \App\Models\Span $connectionSpan): void
    {
        // Add start date if available
        if ($connectionSpan->start_year) {
            $start = (string) $connectionSpan->start_year;
            if ($connectionSpan->start_month) {
                $start .= '-' . str_pad($connectionSpan->start_month, 2, '0', STR_PAD_LEFT);
                if ($connectionSpan->start_day) {
                    $start .= '-' . str_pad($connectionSpan->start_day, 2, '0', STR_PAD_LEFT);
                }
            }
            $connectionData['start'] = $start;
        }

        // Add end date if available
        if ($connectionSpan->end_year) {
            $end = (string) $connectionSpan->end_year;
            if ($connectionSpan->end_month) {
                $end .= '-' . str_pad($connectionSpan->end_month, 2, '0', STR_PAD_LEFT);
                if ($connectionSpan->end_day) {
                    $end .= '-' . str_pad($connectionSpan->end_day, 2, '0', STR_PAD_LEFT);
                }
            }
            $connectionData['end'] = $end;
        }

        // Add connection span metadata if present (like job titles, degrees, etc.)
        if (!empty($connectionSpan->metadata)) {
            $connectionData['metadata'] = $connectionSpan->metadata;
        }
    }

    /**
     * Extract dates from nested connection spans for sophisticated role descriptions
     */
    private function extractDatesFromNestedConnections(array &$connectionData, \App\Models\Span $connectionSpan): void
    {
        // Load nested connections with their connection spans
        $connectionSpan->load([
            'connectionsAsSubject.child.type',
            'connectionsAsSubject.type',
            'connectionsAsSubject.connectionSpan'
        ]);
        
        // Look for at_organisation connections with dates
        foreach ($connectionSpan->connectionsAsSubject as $nestedConnection) {
            if ($nestedConnection->type_id === 'at_organisation' && $nestedConnection->connectionSpan) {
                $nestedConnectionSpan = $nestedConnection->connectionSpan;
                
                // Add dates from the nested connection span
                if ($nestedConnectionSpan->start_year) {
                    $start = (string) $nestedConnectionSpan->start_year;
                    if ($nestedConnectionSpan->start_month) {
                        $start .= '-' . str_pad($nestedConnectionSpan->start_month, 2, '0', STR_PAD_LEFT);
                        if ($nestedConnectionSpan->start_day) {
                            $start .= '-' . str_pad($nestedConnectionSpan->start_day, 2, '0', STR_PAD_LEFT);
                        }
                    }
                    $connectionData['start'] = $start;
                }

                if ($nestedConnectionSpan->end_year) {
                    $end = (string) $nestedConnectionSpan->end_year;
                    if ($nestedConnectionSpan->end_month) {
                        $end .= '-' . str_pad($nestedConnectionSpan->end_month, 2, '0', STR_PAD_LEFT);
                        if ($nestedConnectionSpan->end_day) {
                            $end .= '-' . str_pad($nestedConnectionSpan->end_day, 2, '0', STR_PAD_LEFT);
                        }
                    }
                    $connectionData['end'] = $end;
                }
                
                // We found the at_organisation connection with dates, so break
                break;
            }
        }
    }

    /**
     * Get nested connections from a connection span (e.g., at_organisation connections)
     */
    private function getNestedConnections(\App\Models\Span $connectionSpan): array
    {
        $nestedConnections = [];
        
        // Load all connections from the connection span
        $connectionSpan->load([
            'connectionsAsSubject.child.type',
            'connectionsAsObject.parent.type', 
            'connectionsAsSubject.type',
            'connectionsAsObject.type'
        ]);
        
        // Process outgoing connections
        foreach ($connectionSpan->connectionsAsSubject as $connection) {
            $connectionType = $connection->type_id;
            $targetSpan = $connection->child;
            
            $nestedConnections[] = [
                'type' => $connectionType,
                'direction' => 'outgoing',
                'target_name' => $targetSpan->name,
                'target_id' => $targetSpan->id,
                'target_type' => $targetSpan->type_id,
            ];
        }
        
        // Process incoming connections
        foreach ($connectionSpan->connectionsAsObject as $connection) {
            $connectionType = $connection->type_id;
            $sourceSpan = $connection->parent;
            
            $nestedConnections[] = [
                'type' => $connectionType,
                'direction' => 'incoming',
                'target_name' => $sourceSpan->name,
                'target_id' => $sourceSpan->id,
                'target_type' => $sourceSpan->type_id,
            ];
        }
        
        return $nestedConnections;
    }

    /**
     * Find organisation information from nested connections (for sophisticated role descriptions)
     */
    private function findNestedOrganisation(array $connectionData): ?array
    {
        if (!isset($connectionData['nested_connections'])) {
            return null;
        }
        
        foreach ($connectionData['nested_connections'] as $nestedConnection) {
            // Look for outgoing at_organisation connections
            if ($nestedConnection['type'] === 'at_organisation' && 
                $nestedConnection['direction'] === 'outgoing' &&
                $nestedConnection['target_type'] === 'organisation') {
                return [
                    'name' => $nestedConnection['target_name'],
                    'type' => $nestedConnection['target_type'],
                    'id' => $nestedConnection['target_id'] ?? null
                ];
            }
        }
        
        return null;
    }

    /**
     * Validate required fields
     */
    private function validateRequiredFields(array $data): void
    {
        $required = ['name', 'type'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }
        
        // Validate span type exists
        $validSpanTypes = SpanType::pluck('type_id')->toArray();
        if (!in_array($data['type'], $validSpanTypes)) {
            throw new \InvalidArgumentException("Invalid span type '{$data['type']}'. Valid types are: " . implode(', ', $validSpanTypes));
        }
    }

    /**
     * Validate and normalize date fields
     */
    private function validateAndNormalizeDates(array &$data): void
    {
        // Parse start and end dates from YAML if present
        if (isset($data['start'])) {
            $parsedStart = $this->parseDate($data['start']);
            $data['start_year'] = $parsedStart['year'];
            $data['start_month'] = $parsedStart['month'];
            $data['start_day'] = $parsedStart['day'];
        } else {
            // Explicitly set to null when no start field is provided
            $data['start_year'] = null;
            $data['start_month'] = null;
            $data['start_day'] = null;
        }
        
        if (isset($data['end']) && $data['end'] !== null) {
            $parsedEnd = $this->parseDate($data['end']);
            $data['end_year'] = $parsedEnd['year'];
            $data['end_month'] = $parsedEnd['month'];
            $data['end_day'] = $parsedEnd['day'];
        } else {
            // Explicitly set to null when no end field is provided
            $data['end_year'] = null;
            $data['end_month'] = null;
            $data['end_day'] = null;
        }
    }

    /**
     * Parse a date string (YYYY, YYYY-MM, or YYYY-MM-DD)
     */
    private function parseDate($dateInput): array
    {
        // Handle different input types
        if (is_numeric($dateInput)) {
            // Handle timestamp or year-only integer
            $dateStr = (string) $dateInput;
            
            // If it's a very large number, it might be a timestamp
            if ($dateInput > 9999) {
                // Convert timestamp to date
                $date = new \DateTime();
                $date->setTimestamp($dateInput);
                return [
                    'year' => (int) $date->format('Y'),
                    'month' => (int) $date->format('n'),
                    'day' => (int) $date->format('j')
                ];
            }
            
            if (strlen($dateStr) === 4) {
                // Treat 4-digit number as year
                $year = (int) $dateStr;
                if ($year < 1 || $year > 9999) {
                    throw new \InvalidArgumentException("Invalid year: {$year}");
                }
                return ['year' => $year, 'month' => null, 'day' => null];
            }
        }
        
        $dateStr = (string) $dateInput;
        
        // Handle the case where YAML interpreted the date as a mathematical expression
        // e.g., "1976-02-13" becomes 1961 (1976 - 2 - 13)
        if (is_numeric($dateStr) && $dateStr < 1000) {
            throw new \InvalidArgumentException("Date appears to be a mathematical expression result: {$dateStr}. Please quote dates in YAML (e.g., '1976-02-13')");
        }
        
        $parts = explode('-', $dateStr);
        
        if (count($parts) < 1 || count($parts) > 3) {
            throw new \InvalidArgumentException("Invalid date format: {$dateStr}. Expected YYYY, YYYY-MM, or YYYY-MM-DD");
        }
        
        $year = (int) $parts[0];
        if ($year < 1 || $year > 9999) {
            throw new \InvalidArgumentException("Invalid year: {$year}");
        }
        
        $month = null;
        $day = null;
        
        if (count($parts) >= 2) {
            $month = (int) $parts[1];
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException("Invalid month: {$month}");
            }
        }
        
        if (count($parts) === 3) {
            $day = (int) $parts[2];
            if ($day < 1 || $day > 31) {
                throw new \InvalidArgumentException("Invalid day: {$day}");
            }
        }
        
        return ['year' => $year, 'month' => $month, 'day' => $day];
    }

    /**
     * Filter out unsupported connection types from the data
     * This prevents errors when AI generates connection types that aren't supported
     */
    /**
     * Normalize AI-generated data to fix common issues
     * 
     * AI sometimes returns fields in incorrect formats (e.g., JSON-encoded strings
     * instead of arrays). This method normalizes the data structure.
     */
    private function normalizeAiGeneratedData(array &$data): void
    {
        // Normalize sources - AI sometimes returns it as a JSON-encoded string
        if (isset($data['sources']) && is_string($data['sources'])) {
            $originalSources = $data['sources'];
            
            // Try to decode as JSON
            $decoded = json_decode($originalSources, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Successfully decoded JSON string to array
                $data['sources'] = $decoded;
                Log::info('Normalized sources from JSON string to array', [
                    'original' => $originalSources,
                    'normalized' => $decoded
                ]);
            } else {
                // Not valid JSON, treat the string as a single source URL
                Log::info('Converting non-JSON sources string to array', [
                    'original' => $originalSources
                ]);
                $data['sources'] = [$originalSources];
            }
        }
        
        // Normalize empty sources to null instead of empty array if needed
        if (isset($data['sources']) && is_array($data['sources']) && empty($data['sources'])) {
            $data['sources'] = null;
        }
        
        // Normalize connections - AI sometimes forgets the dash syntax for YAML lists
        if (isset($data['connections']) && is_array($data['connections'])) {
            foreach ($data['connections'] as $connectionType => &$connectionList) {
                if (is_array($connectionList) && !empty($connectionList)) {
                    // Check if this looks like a single connection object instead of a list
                    // It's a single object if it has keys like 'name', 'type', etc. and is NOT a sequential array
                    $keys = array_keys($connectionList);
                    $isSequential = $keys === range(0, count($keys) - 1);
                    
                    if (!$isSequential && isset($connectionList['name']) && isset($connectionList['type'])) {
                        // This is a single connection object, wrap it in an array to make it a list
                        Log::warning("Normalizing connection '{$connectionType}' - AI forgot dash syntax, converting single object to list", [
                            'connection_type' => $connectionType,
                            'original_keys' => $keys
                        ]);
                        $data['connections'][$connectionType] = [$connectionList];
                    }
                }
            }
        }
    }
    
    private function filterUnsupportedConnections(array &$data): void
    {
        if (!isset($data['connections']) || !is_array($data['connections'])) {
            return;
        }
        
        // Get all valid connection types from database, plus special family handling
        $connectionTypes = ConnectionType::pluck('type')->toArray();
        $validConnectionTypes = array_merge($connectionTypes, ['parents', 'children']);
        
        // Remove 'family' from valid types since we handle it specially as parents/children
        $validConnectionTypes = array_filter($validConnectionTypes, function($type) {
            return $type !== 'family';
        });
        
        $filteredConnections = [];
        $removedTypes = [];
        
        foreach ($data['connections'] as $connectionType => $connections) {
            // Handle incoming connections - these are now editable
            $isIncoming = str_ends_with($connectionType, '_incoming');
            $baseConnectionType = $isIncoming ? str_replace('_incoming', '', $connectionType) : $connectionType;
            
            // Skip unsupported connection types
            if (!in_array($baseConnectionType, $validConnectionTypes)) {
                $removedTypes[] = $baseConnectionType;
                Log::info('Filtering out unsupported connection type from AI improvement', [
                    'connection_type' => $baseConnectionType,
                    'full_key' => $connectionType,
                    'connections_count' => is_array($connections) ? count($connections) : 0
                ]);
                continue;
            }
            
            // Filter nested connections within each connection
            if (is_array($connections)) {
                $filteredConnectionsList = [];
                foreach ($connections as $connection) {
                    if (is_array($connection) && isset($connection['nested_connections'])) {
                        $filteredNested = $this->filterNestedConnections($connection['nested_connections'], $connectionTypes);
                        if (!empty($filteredNested)) {
                            $connection['nested_connections'] = $filteredNested;
                        } else {
                            unset($connection['nested_connections']);
                        }
                    }
                    $filteredConnectionsList[] = $connection;
                }
                $filteredConnections[$connectionType] = $filteredConnectionsList;
            } else {
                $filteredConnections[$connectionType] = $connections;
            }
        }
        
        // Update the data with filtered connections
        $data['connections'] = $filteredConnections;
        
        // Log if any types were removed
        if (!empty($removedTypes)) {
            Log::info('Removed unsupported connection types from AI improvement data', [
                'removed_types' => array_unique($removedTypes),
                'valid_types' => $validConnectionTypes
            ]);
        }
    }
    
    /**
     * Filter out unsupported nested connection types
     */
    private function filterNestedConnections(array $nestedConnections, array $validConnectionTypes): array
    {
        $filtered = [];
        
        foreach ($nestedConnections as $nestedConnection) {
            if (!is_array($nestedConnection) || !isset($nestedConnection['type'])) {
                continue;
            }
            
            // Only include if the nested connection type is valid
            if (in_array($nestedConnection['type'], $validConnectionTypes)) {
                $filtered[] = $nestedConnection;
            } else {
                Log::info('Filtering out unsupported nested connection type', [
                    'type' => $nestedConnection['type'],
                    'valid_types' => $validConnectionTypes
                ]);
            }
        }
        
        return $filtered;
    }
    
    /**
     * Validate connections structure
     */
    private function validateConnections(array $data): void
    {
        if (!isset($data['connections'])) {
            return;
        }
        
        if (!is_array($data['connections'])) {
            throw new \InvalidArgumentException('Connections must be an array');
        }
        
        // Get all valid connection types from database, plus special family handling
        $connectionTypes = ConnectionType::pluck('type')->toArray();
        $validConnectionTypes = array_merge($connectionTypes, ['parents', 'children']);
        
        // Remove 'family' from valid types since we handle it specially as parents/children
        $validConnectionTypes = array_filter($validConnectionTypes, function($type) {
            return $type !== 'family';
        });
        
        foreach ($data['connections'] as $connectionType => $connections) {
            // Handle incoming connections - these are now editable
            $isIncoming = str_ends_with($connectionType, '_incoming');
            $baseConnectionType = $isIncoming ? str_replace('_incoming', '', $connectionType) : $connectionType;
            
            // Validate connection type (use base type for incoming connections)
            if (!in_array($baseConnectionType, $validConnectionTypes)) {
                throw new \InvalidArgumentException("Unknown connection type '{$baseConnectionType}'. Valid types are: " . implode(', ', $validConnectionTypes));
            }
            
            if (!is_array($connections)) {
                throw new \InvalidArgumentException("Connection type '{$connectionType}' must be an array");
            }
            
            foreach ($connections as $index => $connection) {
                if (!is_array($connection)) {
                    throw new \InvalidArgumentException("Each connection in '{$connectionType}' must be an array");
                }
                
                if (!isset($connection['name'])) {
                    throw new \InvalidArgumentException("Connection {$index} in '{$connectionType}' must have a 'name' field");
                }
                
                // Validate required fields for connections
                $requiredFields = ['name', 'type'];
                foreach ($requiredFields as $field) {
                    if (!isset($connection[$field])) {
                        throw new \InvalidArgumentException("Connection '{$connection['name']}' in '{$connectionType}' is missing required field '{$field}'");
                    }
                }
                
                // Validate span type for connected span
                $validSpanTypes = SpanType::pluck('type_id')->toArray();
                if (!in_array($connection['type'], $validSpanTypes)) {
                    throw new \InvalidArgumentException("Invalid span type '{$connection['type']}' in connection. Valid types are: " . implode(', ', $validSpanTypes));
                }
                
                // ID is required for existing connections, but can be missing for new ones
                if (!isset($connection['id'])) {
                    // This is a new connection - we'll generate an ID during application
                    // For now, just validate that the name and type are present
                }
                
                // Validate date fields if present
                if (isset($connection['start'])) {
                    try {
                        $this->parseDate($connection['start']);
                    } catch (\InvalidArgumentException $e) {
                        throw new \InvalidArgumentException("Invalid start date for connection '{$connection['name']}' in '{$connectionType}': " . $e->getMessage());
                    }
                }
                
                if (isset($connection['end'])) {
                    try {
                        $this->parseDate($connection['end']);
                    } catch (\InvalidArgumentException $e) {
                        throw new \InvalidArgumentException("Invalid end date for connection '{$connection['name']}' in '{$connectionType}': " . $e->getMessage());
                    }
                }
                
                // Validate nested connections if present
                if (isset($connection['nested_connections'])) {
                    $this->validateNestedConnections($connection['nested_connections']);
                }
            }
        }
    }
    
    /**
     * Validate nested connections structure
     */
    private function validateNestedConnections(array $nestedConnections): void
    {
        if (!is_array($nestedConnections)) {
            throw new \InvalidArgumentException('Nested connections must be an array');
        }
        
        foreach ($nestedConnections as $nestedConnection) {
            if (!is_array($nestedConnection)) {
                throw new \InvalidArgumentException('Each nested connection must be an array');
            }
            
            if (!isset($nestedConnection['type'])) {
                throw new \InvalidArgumentException('Nested connection must have a "type" field');
            }
            
            if (!isset($nestedConnection['target_name'])) {
                throw new \InvalidArgumentException('Nested connection must have a "target_name" field');
            }
            
            if (!isset($nestedConnection['target_type'])) {
                throw new \InvalidArgumentException('Nested connection must have a "target_type" field');
            }
            
            // Validate nested connection type
            $connectionTypes = ConnectionType::pluck('type')->toArray();
            if (!in_array($nestedConnection['type'], $connectionTypes)) {
                throw new \InvalidArgumentException("Invalid nested connection type '{$nestedConnection['type']}'. Valid types are: " . implode(', ', $connectionTypes));
            }
            
            // Validate target span type
            $validSpanTypes = SpanType::pluck('type_id')->toArray();
            if (!in_array($nestedConnection['target_type'], $validSpanTypes)) {
                throw new \InvalidArgumentException("Invalid target span type '{$nestedConnection['target_type']}' in nested connection. Valid types are: " . implode(', ', $validSpanTypes));
            }
            
            // Validate direction if present
            if (isset($nestedConnection['direction'])) {
                if (!in_array($nestedConnection['direction'], ['incoming', 'outgoing'])) {
                    throw new \InvalidArgumentException("Invalid direction '{$nestedConnection['direction']}'. Must be 'incoming' or 'outgoing'");
                }
            }
        }
    }

    /**
     * Update connections for a span
     */
    public function updateConnections(Span $span, array $connectionsData): void
    {
        $connectionImporter = new ConnectionImporter($span->owner);
        
        foreach ($connectionsData as $connectionType => $connections) {
            if (!is_array($connections)) {
                continue;
            }
            
            foreach ($connections as $connectionData) {
                if (!is_array($connectionData) || !isset($connectionData['name'], $connectionData['type'])) {
                    continue;
                }
                
                try {
                    // Handle incoming connections
                    $isIncoming = str_ends_with($connectionType, '_incoming');
                    $baseConnectionType = $isIncoming ? str_replace('_incoming', '', $connectionType) : $connectionType;
                    
                    // Handle special family connection mapping
                    $actualConnectionType = $this->mapConnectionType($baseConnectionType);
                    
                    // Parse connection dates if present
                    $dates = null;
                    if (isset($connectionData['start'])) {
                        $dates = $connectionImporter->parseDatesFromStrings(
                            $connectionData['start'] ?? null,
                            $connectionData['end'] ?? null
                        );
                    }
                    
                    // Prepare metadata
                    $metadata = $connectionData['metadata'] ?? [];
                    if (isset($connectionData['connection_metadata'])) {
                        $metadata = array_merge($metadata, $connectionData['connection_metadata']);
                    }
                    
                    // Check if we have a connection_id to update an existing connection
                    if (isset($connectionData['connection_id'])) {
                        $existingConnection = Connection::find($connectionData['connection_id']);
                        if ($existingConnection) {
                            // Update existing connection
                            $this->updateExistingConnection($existingConnection, $dates, $metadata, $span->owner_id);
                            
                            // Process nested connections for existing connection
                            if (isset($connectionData['nested_connections'])) {
                                $this->processNestedConnections($existingConnection, $connectionData['nested_connections'], $connectionImporter);
                            }
                            continue;
                        }
                    }
                    
                    // For new connections, we need to find or create the connected span
                    $connectedSpan = null;
                    
                    if (isset($connectionData['id'])) {
                        // Try to find existing span by ID first
                        $connectedSpan = Span::find($connectionData['id']);
                        if (!$connectedSpan) {
                            // If ID not found, try to find by name and type instead
                            $connectedSpan = Span::where('name', $connectionData['name'])
                                ->where('type_id', $connectionData['type'])
                                ->first();
                            
                            if ($connectedSpan) {
                                Log::info('Found span by name instead of ID', [
                                    'provided_id' => $connectionData['id'],
                                    'found_id' => $connectedSpan->id,
                                    'name' => $connectionData['name'],
                                    'type' => $connectionData['type']
                                ]);
                            } else {
                                throw new \InvalidArgumentException("Span with ID '{$connectionData['id']}' not found, and no existing span with name '{$connectionData['name']}' and type '{$connectionData['type']}' exists");
                            }
                        }
                        // IMPORTANT: Don't update the connected span's dates here!
                        // The dates in the connection data are for the CONNECTION (e.g., when someone worked there),
                        // NOT for the span itself (e.g., when the organisation existed).
                        // Connection dates will be applied to the connection span below.
                        
                        // Only update state if present in YAML
                        $spanChanged = false;
                        if (isset($connectionData['state'])) {
                            $connectedSpan->state = $connectionData['state'];
                            $spanChanged = true;
                        }
                        if ($spanChanged) {
                            $connectedSpan->save();
                            // Explicitly clear timeline caches for the updated connected span
                            $connectedSpan->clearTimelineCaches();
                        }
                    } else {
                        // No ID provided - find or create by name and type
                        $connectedSpan = $connectionImporter->findOrCreateConnectedSpan(
                            $connectionData['name'],
                            $connectionData['type'],
                            null, // Don't pass connection dates as span dates
                            $metadata
                        );
                        // IMPORTANT: Don't update the connected span's dates here!
                        // The dates in the connection data are for the CONNECTION (e.g., when someone worked there),
                        // NOT for the span itself (e.g., when the organisation existed).
                        // Connection dates will be applied to the connection span below.
                        
                        // Only update state if present in YAML
                        $spanChanged = false;
                        if (isset($connectionData['state'])) {
                            $connectedSpan->state = $connectionData['state'];
                            $spanChanged = true;
                        }
                        if ($spanChanged) {
                            $connectedSpan->save();
                            // Explicitly clear timeline caches for the updated connected span
                            $connectedSpan->clearTimelineCaches();
                        }
                    }
                    
                    // Determine parent/child relationship based on connection type
                    [$parent, $child] = $this->determineConnectionDirection(
                        $span, 
                        $connectedSpan, 
                        $actualConnectionType, 
                        $baseConnectionType,
                        $isIncoming
                    );
                    
                    // Create new connection
                    $newConnection = $connectionImporter->createConnection(
                        $parent,
                        $child,
                        $actualConnectionType,
                        $dates,
                        $metadata
                    );
                    
                    // Process nested connections if present
                    if (isset($connectionData['nested_connections'])) {
                        $this->processNestedConnections($newConnection, $connectionData['nested_connections'], $connectionImporter);
                    }
                    
                    Log::info('YAML connection processed', [
                        'connection_type' => $actualConnectionType,
                        'parent' => $parent->name,
                        'child' => $child->name,
                        'connected_span_created' => $connectedSpan->wasRecentlyCreated ?? false,
                        'has_nested_connections' => isset($connectionData['nested_connections'])
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error('Failed to process YAML connection', [
                        'span_id' => $span->id,
                        'connection_type' => $connectionType,
                        'connection_data' => $connectionData,
                        'error' => $e->getMessage()
                    ]);
                    // Continue processing other connections instead of failing completely
                }
            }
        }
    }
    
    /**
     * Process nested connections for a given connection
     */
    private function processNestedConnections(Connection $connection, array $nestedConnections, ConnectionImporter $connectionImporter): void
    {
        $connectionSpan = $connection->connectionSpan;
        if (!$connectionSpan) {
            Log::warning('Connection has no connection span for nested connections', ['connection_id' => $connection->id]);
            return;
        }
        
        foreach ($nestedConnections as $nestedConnectionData) {
            if (!isset($nestedConnectionData['type'], $nestedConnectionData['target_name'], $nestedConnectionData['target_type'])) {
                Log::warning('Invalid nested connection data', ['nested_data' => $nestedConnectionData]);
                continue;
            }
            
            try {
                // Find or create the target span
                $targetSpan = null;
                if (isset($nestedConnectionData['target_id'])) {
                    $targetSpan = Span::find($nestedConnectionData['target_id']);
                    if (!$targetSpan) {
                        // If ID not found, try to find by name and type instead
                        $targetSpan = Span::where('name', $nestedConnectionData['target_name'])
                            ->where('type_id', $nestedConnectionData['target_type'])
                            ->first();
                        
                        if ($targetSpan) {
                            Log::info('Found nested target span by name instead of ID', [
                                'provided_id' => $nestedConnectionData['target_id'],
                                'found_id' => $targetSpan->id,
                                'name' => $nestedConnectionData['target_name'],
                                'type' => $nestedConnectionData['target_type']
                            ]);
                        } else {
                            throw new \InvalidArgumentException("Target span with ID '{$nestedConnectionData['target_id']}' not found, and no existing span with name '{$nestedConnectionData['target_name']}' and type '{$nestedConnectionData['target_type']}' exists");
                        }
                    }
                } else {
                    // No ID provided - find or create by name and type
                    $targetSpan = $connectionImporter->findOrCreateConnectedSpan(
                        $nestedConnectionData['target_name'],
                        $nestedConnectionData['target_type'],
                        null,
                        []
                    );
                }
                
                // Determine direction for nested connection
                $direction = $nestedConnectionData['direction'] ?? 'outgoing';
                if ($direction === 'outgoing') {
                    // Connection span -> target span
                    $parent = $connectionSpan;
                    $child = $targetSpan;
                } else {
                    // Target span -> connection span
                    $parent = $targetSpan;
                    $child = $connectionSpan;
                }
                
                // Create the nested connection
                $connectionImporter->createConnection(
                    $parent,
                    $child,
                    $nestedConnectionData['type'],
                    null, // No dates for nested connections by default
                    []
                );
                
                Log::info('Nested connection created', [
                    'parent_connection_id' => $connection->id,
                    'nested_type' => $nestedConnectionData['type'],
                    'target_span' => $targetSpan->name,
                    'direction' => $direction
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to process nested connection', [
                    'parent_connection_id' => $connection->id,
                    'nested_data' => $nestedConnectionData,
                    'error' => $e->getMessage()
                ]);
                // Continue processing other nested connections
            }
        }
    }
    
    /**
     * Update an existing connection with new dates and metadata
     */
    private function updateExistingConnection(Connection $connection, ?array $dates, ?array $metadata, string $ownerId): void
    {
        $connectionSpan = $connection->connectionSpan;
        if (!$connectionSpan) {
            Log::warning('Connection has no connection span', ['connection_id' => $connection->id]);
            return;
        }
        
        // Update the connection span with new dates and metadata
        $updateData = [
            'updater_id' => $ownerId,
        ];
        
        if ($dates) {
            $updateData = array_merge($updateData, [
                'start_year' => $dates['start_year'] ?? null,
                'start_month' => $dates['start_month'] ?? null,
                'start_day' => $dates['start_day'] ?? null,
                'end_year' => $dates['end_year'] ?? null,
                'end_month' => $dates['end_month'] ?? null,
                'end_day' => $dates['end_day'] ?? null,
            ]);
        }
        
        if ($metadata) {
            // Update metadata (connection_type is now handled as a root field, not in metadata)
            $updatedMetadata = array_merge($connectionSpan->metadata ?? [], $metadata);
            $updateData['metadata'] = $updatedMetadata;
        }
        
        $connectionSpan->update($updateData);
        
        Log::info('Updated existing connection', [
            'connection_id' => $connection->id,
            'connection_span_id' => $connectionSpan->id,
            'dates' => $dates,
            'metadata' => $metadata
        ]);
    }
    
    /**
     * Map YAML connection types to database connection types
     */
    private function mapConnectionType(string $yamlConnectionType): string
    {
        return match($yamlConnectionType) {
            'parents' => 'family',
            'children' => 'family',
            default => $yamlConnectionType
        };
    }
    
    /**
     * Determine parent/child relationship based on connection type and context
     */
    private function determineConnectionDirection(Span $mainSpan, Span $connectedSpan, string $connectionType, string $yamlConnectionType, bool $isIncoming = false): array
    {
        // For family connections, YAML structure determines direction
        if ($connectionType === 'family') {
            if ($yamlConnectionType === 'parents') {
                return [$connectedSpan, $mainSpan]; // Parent -> Child
            } elseif ($yamlConnectionType === 'children') {
                return [$mainSpan, $connectedSpan]; // Parent -> Child
            }
        }
        
        // For incoming connections, the connected span is the parent and main span is the child
        if ($isIncoming) {
            return [$connectedSpan, $mainSpan]; // Connected span -> Main span
        }
        
        // For outgoing connections, use the main span as parent by default
        // This matches the YAML export structure where outgoing connections are listed
        return [$mainSpan, $connectedSpan];
    }

    /**
     * Analyze impacts of YAML changes
     */
    public function analyzeChangeImpacts(array $data, Span $originalSpan): array
    {
        $impacts = [];
        
        // Check for name changes
        if (isset($data['name']) && $data['name'] !== $originalSpan->name) {
            $impacts[] = [
                'type' => 'info',
                'message' => "Name will change from '{$originalSpan->name}' to '{$data['name']}'"
            ];
        }
        
        // Check for date changes with normalized comparison
        $newStartDate = $data['start'] ?? null;
        $currentStartDate = $originalSpan->getFormattedStartDateAttribute();
        if ($this->normalizeDateForComparison($newStartDate) !== $this->normalizeDateForComparison($currentStartDate)) {
            $impacts[] = [
                'type' => 'info',
                'message' => "Start date will change from '{$currentStartDate}' to '{$newStartDate}'"
            ];
        }
        
        $newEndDate = $data['end'] ?? null;
        $currentEndDate = $originalSpan->getFormattedEndDateAttribute();
        if ($this->normalizeDateForComparison($newEndDate) !== $this->normalizeDateForComparison($currentEndDate)) {
            $impacts[] = [
                'type' => 'info',
                'message' => "End date will change from '{$currentEndDate}' to '{$newEndDate}'"
            ];
        }
        
        // Check for metadata changes
        $newMetadata = $data['metadata'] ?? [];
        $currentMetadata = $originalSpan->metadata ?? [];
        if ($newMetadata !== $currentMetadata) {
            $impacts[] = [
                'type' => 'info',
                'message' => "Metadata will be updated"
            ];
        }
        
        // Check for connection changes and their impacts
        if (isset($data['connections'])) {
            $connectionImpacts = $this->analyzeConnectionImpacts($originalSpan, $data['connections']);
            $impacts = array_merge($impacts, $connectionImpacts);
            
            // Check for referenced span name changes
            $nameChangeImpacts = $this->analyzeReferencedSpanNameChanges($data['connections']);
            $impacts = array_merge($impacts, $nameChangeImpacts);
        }
        
        return $impacts;
    }

    /**
     * Analyze impacts of connection changes
     */
    private function analyzeConnectionImpacts(Span $originalSpan, array $newConnections): array
    {
        $impacts = [];
        
        // Get current connections grouped by type
        $currentConnections = $this->spanToArray($originalSpan)['connections'] ?? [];
        
        // Check for new connections
        foreach ($newConnections as $type => $connections) {
            $currentCount = isset($currentConnections[$type]) ? count($currentConnections[$type]) : 0;
            $newCount = count($connections);
            
            if ($newCount > $currentCount) {
                $added = $newCount - $currentCount;
                $impacts[] = [
                    'type' => 'info',
                    'message' => "Will add {$added} new {$type} connection(s)"
                ];
                
                // Provide detailed information about each new connection
                $newConnectionsList = array_slice($connections, $currentCount);
                foreach ($newConnectionsList as $connection) {
                    $connectionName = $connection['name'] ?? 'unnamed';
                    $connectionType = $connection['type'] ?? 'unknown';
                    
                    // Check if the target span already exists
                    $targetSpanExists = Span::where('name', $connectionName)
                        ->where('type_id', $connectionType)
                        ->exists();
                    
                    $spanAction = $targetSpanExists ? 'link to existing' : 'create new';
                    $impacts[] = [
                        'type' => 'info',
                        'message' => "  → '{$connectionName}' ({$connectionType}) - will {$spanAction} span",
                        'indent' => true
                    ];
                }
            } elseif ($newCount < $currentCount) {
                $removed = $currentCount - $newCount;
                $impacts[] = [
                    'type' => 'warning',
                    'message' => "Will remove {$removed} {$type} connection(s)"
                ];
            }
            
            // Check for changes to existing connections and nested connections
            foreach ($connections as $connection) {
                $currentConnection = $this->findMatchingCurrentConnection($connection, $currentConnections);
                
                if ($currentConnection) {
                    // This is an existing connection - check for changes
                    $connectionChanges = $this->detectConnectionChanges($connection, $currentConnection);
                    if (!empty($connectionChanges)) {
                        $connectionName = $connection['name'] ?? 'unnamed';
                        $impacts[] = [
                            'type' => 'info',
                            'message' => "Will update '{$connectionName}' ({$type}) connection:"
                        ];
                        
                        foreach ($connectionChanges as $change) {
                            $impacts[] = [
                                'type' => 'info',
                                'message' => "  → {$change}",
                                'indent' => true
                            ];
                        }
                    }
                }
                
                // Check for nested connections in each connection
                if (isset($connection['nested_connections']) && is_array($connection['nested_connections'])) {
                    $newNestedConnections = $this->findNewNestedConnections($connection, $currentConnections);
                    
                    if (!empty($newNestedConnections)) {
                        $connectionName = $connection['name'] ?? 'unnamed';
                        $nestedCount = count($newNestedConnections);
                        
                        $impacts[] = [
                            'type' => 'info',
                            'message' => "Will create {$nestedCount} new nested connection(s) for '{$connectionName}' ({$type})"
                        ];
                        
                        // Provide more detailed information about each new nested connection
                        foreach ($newNestedConnections as $nestedConnection) {
                            $nestedType = $nestedConnection['type'] ?? 'unknown';
                            $targetName = $nestedConnection['target_name'] ?? 'unnamed';
                            $targetType = $nestedConnection['target_type'] ?? 'unknown';
                            $direction = $nestedConnection['direction'] ?? 'outgoing';
                            
                            // Check if the target span already exists
                            $targetSpanExists = Span::where('name', $targetName)
                                ->where('type_id', $targetType)
                                ->exists();
                            
                            $spanAction = $targetSpanExists ? 'link to existing' : 'create new';
                            $directionText = $direction === 'outgoing' ? 'from' : 'to';
                            $impacts[] = [
                                'type' => 'info',
                                'message' => "  → {$nestedType} connection {$directionText} '{$targetName}' ({$targetType}) - will {$spanAction} span",
                                'indent' => true
                            ];
                        }
                    }
                }
            }
        }
        
        // Check for completely removed connection types
        foreach ($currentConnections as $type => $connections) {
            if (!isset($newConnections[$type])) {
                $count = count($connections);
                $impacts[] = [
                    'type' => 'warning',
                    'message' => "Will remove all {$count} {$type} connection(s)"
                ];
            }
        }
        
        return $impacts;
    }
    
    /**
     * Find nested connections that don't already exist in the database
     */
    private function findNewNestedConnections(array $connection, array $currentConnections): array
    {
        $newNestedConnections = [];
        $nestedConnections = $connection['nested_connections'] ?? [];
        
        // Find the corresponding current connection to compare against
        $currentConnection = $this->findMatchingCurrentConnection($connection, $currentConnections);
        
        if (!$currentConnection) {
            // This is a completely new connection, so all nested connections are new
            return $nestedConnections;
        }
        
        $currentNestedConnections = $currentConnection['nested_connections'] ?? [];
        
        // Compare each nested connection to see if it's new
        foreach ($nestedConnections as $nestedConnection) {
            if (!$this->nestedConnectionExists($nestedConnection, $currentNestedConnections)) {
                $newNestedConnections[] = $nestedConnection;
            }
        }
        
        return $newNestedConnections;
    }
    
    /**
     * Find the matching current connection for comparison
     */
    private function findMatchingCurrentConnection(array $newConnection, array $currentConnections): ?array
    {
        $connectionId = $newConnection['connection_id'] ?? null;
        $connectionName = $newConnection['name'] ?? '';
        $connectionType = $newConnection['type'] ?? '';
        
        // First try to match by connection_id (most reliable)
        if ($connectionId) {
            foreach ($currentConnections as $type => $connections) {
                foreach ($connections as $currentConnection) {
                    if (isset($currentConnection['connection_id']) && $currentConnection['connection_id'] === $connectionId) {
                        return $currentConnection;
                    }
                }
            }
        }
        
        // Fall back to matching by name and type
        foreach ($currentConnections as $type => $connections) {
            foreach ($connections as $currentConnection) {
                if (isset($currentConnection['name']) && $currentConnection['name'] === $connectionName &&
                    isset($currentConnection['type']) && $currentConnection['type'] === $connectionType) {
                    return $currentConnection;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Detect changes between new and current connection data
     */
    private function detectConnectionChanges(array $newConnection, array $currentConnection): array
    {
        $changes = [];
        
        // Check for date changes with normalized comparison
        $newStartDate = $newConnection['start'] ?? null;
        $currentStartDate = $currentConnection['start'] ?? null;
        if ($this->normalizeDateForComparison($newStartDate) !== $this->normalizeDateForComparison($currentStartDate)) {
            $changes[] = "start date: '{$currentStartDate}' → '{$newStartDate}'";
        }
        
        $newEndDate = $newConnection['end'] ?? null;
        $currentEndDate = $currentConnection['end'] ?? null;
        if ($this->normalizeDateForComparison($newEndDate) !== $this->normalizeDateForComparison($currentEndDate)) {
            $changes[] = "end date: '{$currentEndDate}' → '{$newEndDate}'";
        }
        
        // Check for state changes
        $newState = $newConnection['state'] ?? null;
        $currentState = $currentConnection['state'] ?? null;
        if ($newState !== $currentState) {
            $changes[] = "state: '{$currentState}' → '{$newState}'";
        }
        
        // Check for metadata changes
        $newMetadata = $newConnection['metadata'] ?? [];
        $currentMetadata = $currentConnection['metadata'] ?? [];
        if ($newMetadata !== $currentMetadata) {
            $changes[] = "metadata updated";
        }
        
        return $changes;
    }
    
    /**
     * Normalize date for comparison (preserve month-level precision)
     */
    private function normalizeDateForComparison(?string $date): ?string
    {
        // Treat empty string the same as null
        if ($date === null || $date === '') {
            return null;
        }
        
        // If it's just a year (4 digits), return as is
        if (preg_match('/^\d{4}$/', $date)) {
            return $date;
        }
        
        // If it's a year-month format, return as is (preserve month precision)
        if (preg_match('/^\d{4}-\d{2}$/', $date)) {
            return $date;
        }
        
        // If it's a full date, extract year-month (preserve month precision)
        if (preg_match('/^(\d{4}-\d{2})-\d{2}$/', $date, $matches)) {
            return $matches[1];
        }
        
        // For any other format, return as is
        return $date;
    }
    
    /**
     * Check if a nested connection already exists in the current connections
     */
    private function nestedConnectionExists(array $nestedConnection, array $currentNestedConnections): bool
    {
        $nestedType = $nestedConnection['type'] ?? '';
        $targetName = $nestedConnection['target_name'] ?? '';
        $targetType = $nestedConnection['target_type'] ?? '';
        $direction = $nestedConnection['direction'] ?? 'outgoing';
        
        foreach ($currentNestedConnections as $currentNested) {
            $currentType = $currentNested['type'] ?? '';
            $currentTargetName = $currentNested['target_name'] ?? '';
            $currentTargetType = $currentNested['target_type'] ?? '';
            $currentDirection = $currentNested['direction'] ?? 'outgoing';
            
            if ($nestedType === $currentType &&
                $targetName === $currentTargetName &&
                $targetType === $currentTargetType &&
                $direction === $currentDirection) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Analyze potential impacts of changing referenced span names
     */
    private function analyzeReferencedSpanNameChanges(array $connections): array
    {
        $impacts = [];
        
        foreach ($connections as $type => $connectionList) {
            if (!is_array($connectionList)) {
                continue;
            }
            
            foreach ($connectionList as $connection) {
                if (!is_array($connection) || !isset($connection['id'], $connection['name'])) {
                    continue;
                }
                
                // Find the actual span in the database
                $referencedSpan = Span::find($connection['id']);
                if (!$referencedSpan) {
                    // Check if this is a new span that can be created
                    $connectionSpanType = $connection['type'] ?? 'unknown';
                    
                    // For certain span types (like roles), allow creation of new spans
                    if ($this->isCreatableSpanType($connectionSpanType, $type)) {
                        $impacts[] = [
                            'type' => 'info',
                            'message' => "Will create new {$connectionSpanType} span '{$connection['name']}' when applying changes"
                        ];
                    } else {
                        $impacts[] = [
                            'type' => 'danger',
                            'message' => "Referenced {$type} span '{$connection['name']}' not found in database (ID: {$connection['id']})"
                        ];
                    }
                    continue;
                }
                
                // Check if the name in YAML differs from database
                if ($connection['name'] !== $referencedSpan->name) {
                    // Count how many other connections reference this span
                    $otherReferences = $this->countOtherConnectionsToSpan($referencedSpan->id);
                    
                    $message = "YAML shows '{$connection['name']}' but database shows '{$referencedSpan->name}'";
                    if ($otherReferences > 0) {
                        $message .= " (affects {$otherReferences} other connection(s))";
                    }
                    
                    $impacts[] = [
                        'type' => 'warning',
                        'message' => $message,
                        'action_options' => [
                            'update_span' => "Update '{$referencedSpan->name}' to '{$connection['name']}' everywhere",
                            'keep_reference' => "Keep database name '{$referencedSpan->name}' in this connection",
                            'ignore' => "Apply YAML as-is (may cause inconsistency)"
                        ],
                        'span_id' => $referencedSpan->id,
                        'current_name' => $referencedSpan->name,
                        'yaml_name' => $connection['name']
                    ];
                }
            }
        }
        
        return $impacts;
    }

    /**
     * Count how many other spans have connections to the given span
     */
    private function countOtherConnectionsToSpan(string $spanId): int
    {
        $count = 0;
        
        // Count incoming connections (where this span is the child/object)
        $count += Connection::where('child_id', $spanId)->count();
        
        // Count outgoing connections (where this span is the parent/subject)
        $count += Connection::where('parent_id', $spanId)->count();
        
        return $count;
    }

    /**
     * Determine if a span type can be auto-created when referenced in connections
     */
    private function isCreatableSpanType(string $spanType, string $connectionType): bool
    {
        // Define which span types can be auto-created in which connection contexts
        $creatableTypes = [
            // People can be created when referenced in family, relationships, etc.
            'person' => ['family', 'relationship', 'employment', 'education', 'residence', 'membership', 'travel', 'participation'],
            
            // Organisations can be created when referenced in employment, membership, etc.
            'organisation' => ['employment', 'membership', 'ownership', 'participation'],
            
            // Places can be created when referenced in residence, travel, etc.
            'place' => ['residence', 'travel', 'participation'],
            
            // Things can be created when referenced in ownership, creation, etc.
            'thing' => ['ownership', 'created', 'contains'],
            
            // Events can be created when referenced in participation
            'event' => ['participation'],
            
            // Bands can be created when referenced in membership
            'band' => ['membership']
        ];
        
        return isset($creatableTypes[$spanType]) && 
               in_array($connectionType, $creatableTypes[$spanType]);
    }

    /**
     * Create a visual translation of YAML data into human-readable sentences
     */
    public function translateToVisual(array $data): array
    {
        $translations = [];
        
        // Basic span info with badges
        if (isset($data['name']) && isset($data['type'])) {
            $nameBadge = $this->createEntityBadge($data['name'], $data['type']);
            
            // Check if we have subtype information in metadata
            $subtype = $data['metadata']['subtype'] ?? null;
            
            if ($subtype) {
                // Create descriptive text with subtype
                $subtypeBadge = $this->createEntityBadge($subtype, 'subtype');
                $typeBadge = $this->createEntityBadge($data['type'], 'type');
                $article = in_array(strtolower($subtype[0] ?? ''), ['a', 'e', 'i', 'o', 'u']) ? 'an' : 'a';
                $translations[] = [
                    'section' => 'identity',
                    'text' => "{$nameBadge} is {$article} {$subtypeBadge} {$typeBadge}"
                ];
            } else {
                // Fallback to basic type description
                $article = in_array(strtolower($data['type'][0] ?? ''), ['a', 'e', 'i', 'o', 'u']) ? 'an' : 'a';
                $typeBadge = $this->createEntityBadge($data['type'], 'type');
                $translations[] = [
                    'section' => 'identity',
                    'text' => "{$nameBadge} is {$article} {$typeBadge}"
                ];
            }
        }
        
        // Date information with badges
        if (isset($data['start'])) {
            $dateText = $this->formatDateForTranslation($data['start']);
            $actionWord = $this->getActionWordForSpanType($data['type'] ?? 'thing');
            $nameBadge = $this->createEntityBadge($data['name'], $data['type'] ?? 'thing');
            $dateBadge = $this->createDateBadge($dateText);
            $translations[] = [
                'section' => 'dates',
                'text' => "{$nameBadge} <span class=\"badge bg-secondary me-1\">{$actionWord}</span> {$dateBadge}"
            ];
        }
        
        if (isset($data['end'])) {
            $endDateText = $this->formatDateForTranslation($data['end']);
            $endActionWord = $this->getEndActionWordForSpanType($data['type'] ?? 'thing');
            $nameBadge = $this->createEntityBadge($data['name'], $data['type'] ?? 'thing');
            $endDateBadge = $this->createDateBadge($endDateText);
            $translations[] = [
                'section' => 'dates',
                'text' => "{$nameBadge} <span class=\"badge bg-secondary me-1\">{$endActionWord}</span> {$endDateBadge}"
            ];
        }
        
        // Connection translations
        if (isset($data['connections'])) {
            $spanType = $data['type'] ?? 'thing';
            $connectionTranslations = $this->translateConnections($data['name'], $data['connections'], $spanType);
            $translations = array_merge($translations, $connectionTranslations);
        }
        
        // Sort connection translations by date (chronological order)
        $this->sortTranslationsByDate($translations);
        
        return $translations;
    }

    /**
     * Translate connections into human-readable sentences
     */
    private function translateConnections(string $spanName, array $connections, string $spanType = 'thing'): array
    {
        $translations = [];
        
        foreach ($connections as $type => $connectionList) {
            // Skip empty connection arrays
            if (empty($connectionList) || !is_array($connectionList)) {
                continue;
            }
            
            foreach ($connectionList as $connection) {
                // Skip invalid connection entries
                if (!is_array($connection) || empty($connection['name'])) {
                    continue;
                }
                
                $connectionName = $connection['name'];
                $connectionType = $connection['type'] ?? 'thing';
                
                $sentence = $this->buildInteractiveSentence($spanName, $type, $connectionName, $connectionType, $connection, $spanType);
                if ($sentence) {
                    $translation = [
                        'section' => 'connections',
                        'subsection' => $type,
                        'text' => $sentence
                    ];
                    
                    // Add sort date for chronological ordering
                    $sortDate = $this->extractSortDate($connection);
                    if ($sortDate) {
                        $translation['sort_date'] = $sortDate;
                    }
                    
                    $translations[] = $translation;
                }
            }
        }
        
        return $translations;
    }

    /**
     * Extract a sortable date from connection data for chronological ordering
     */
    private function extractSortDate(array $connectionData): ?string
    {
        // Use start_date if available, otherwise use end_date
        if (!empty($connectionData['start_date'])) {
            return $this->normalizeDateForSorting($connectionData['start_date']);
        } elseif (!empty($connectionData['end_date'])) {
            return $this->normalizeDateForSorting($connectionData['end_date']);
        }
        
        return null;
    }

    /**
     * Normalize a date string to a sortable format (YYYY-MM-DD)
     */
    private function normalizeDateForSorting(string $date): string
    {
        // Handle different date formats and convert to YYYY-MM-DD for sorting
        $parts = explode('-', $date);
        
        // Ensure we have at least year
        if (empty($parts[0])) {
            return '9999-12-31'; // Push undated items to the end
        }
        
        $year = str_pad($parts[0], 4, '0', STR_PAD_LEFT);
        $month = isset($parts[1]) && $parts[1] !== '' ? str_pad($parts[1], 2, '0', STR_PAD_LEFT) : '01';
        $day = isset($parts[2]) && $parts[2] !== '' ? str_pad($parts[2], 2, '0', STR_PAD_LEFT) : '01';
        
        return "{$year}-{$month}-{$day}";
    }

    /**
     * Sort translations by date while preserving the order of non-connection items
     */
    private function sortTranslationsByDate(array &$translations): void
    {
        // Separate identity/dates from connections
        $identityItems = [];
        $connectionItems = [];
        
        foreach ($translations as $translation) {
            if ($translation['section'] === 'connections') {
                $connectionItems[] = $translation;
            } else {
                $identityItems[] = $translation;
            }
        }
        
        // Sort connection items by date
        usort($connectionItems, function ($a, $b) {
            $dateA = $a['sort_date'] ?? '9999-12-31';
            $dateB = $b['sort_date'] ?? '9999-12-31';
            
            return strcmp($dateA, $dateB);
        });
        
        // Rebuild the translations array: identity first, then sorted connections
        $translations = array_merge($identityItems, $connectionItems);
    }

    /**
     * Build an interactive sentence using Bootstrap button groups for each part
     */
    private function buildInteractiveSentence(string $spanName, string $connectionType, string $connectionName, string $connectionSpanType, array $connectionData = [], string $spanType = 'thing'): ?string
    {
        // Handle _incoming variants by using inverse language
        $isIncoming = str_ends_with($connectionType, '_incoming');
        $baseType = $isIncoming ? str_replace('_incoming', '', $connectionType) : $connectionType;
        
        // Create interactive buttons for subject, object, and predicate
        $subjectButton = $this->createInteractiveEntityButton($spanName, $spanType, 'subject');
        $objectButton = $this->createInteractiveEntityButton($connectionName, $connectionSpanType, 'object');
        $predicateButton = $this->createInteractivePredicateButton($baseType, $isIncoming);
        
        // Build the base sentence buttons array
        $sentenceButtons = [];
        switch ($baseType) {
            case 'parents':
                $sentenceButtons = [
                    $subjectButton,
                    $this->createConnectorButton('is the child of', 'connector'),
                    $objectButton
                ];
                break;
            case 'children':
                $sentenceButtons = [
                    $subjectButton,
                    $this->createConnectorButton('is the parent of', 'connector'),
                    $objectButton
                ];
                break;
            case 'education':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $objectButton,
                        $predicateButton,
                        $subjectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $predicateButton,
                        $objectButton
                    ];
                }
                break;
            case 'employment':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $objectButton,
                        $predicateButton,
                        $subjectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $predicateButton,
                        $objectButton
                    ];
                }
                break;
            case 'residence':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $objectButton,
                        $this->createConnectorButton('was home to', 'connector'),
                        $subjectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $predicateButton,
                        $objectButton
                    ];
                }
                break;
            case 'membership':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $subjectButton,
                        $this->createConnectorButton('has member', 'connector'),
                        $objectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $predicateButton,
                        $objectButton
                    ];
                }
                break;
            case 'created':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $objectButton,
                        $this->createConnectorButton('was created by', 'connector'),
                        $subjectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $predicateButton,
                        $objectButton
                    ];
                }
                break;
            case 'friend':
                $sentenceButtons = [
                    $subjectButton,
                    $predicateButton,
                    $objectButton
                ];
                break;
            case 'relationship':
                $sentenceButtons = [
                    $subjectButton,
                    $predicateButton,
                    $objectButton
                ];
                break;
            case 'contains':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $objectButton,
                        $this->createConnectorButton('is contained in', 'connector'),
                        $subjectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $predicateButton,
                        $objectButton
                    ];
                }
                break;
            case 'travel':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $objectButton,
                        $this->createConnectorButton('was visited by', 'connector'),
                        $subjectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $predicateButton,
                        $objectButton
                    ];
                }
                break;
            case 'participation':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $objectButton,
                        $this->createConnectorButton('had participant', 'connector'),
                        $subjectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $predicateButton,
                        $objectButton
                    ];
                }
                break;
            case 'ownership':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $objectButton,
                        $this->createConnectorButton('was owned by', 'connector'),
                        $subjectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $predicateButton,
                        $objectButton
                    ];
                }
                break;
            case 'has_role':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $subjectButton,
                        $this->createConnectorButton('is held by', 'connector'),
                        $objectButton
                    ];
                } else {
                    // Check for nested at_organisation connections for sophisticated role descriptions
                    $organisationInfo = $this->findNestedOrganisation($connectionData);
                    if ($organisationInfo) {
                        $orgButton = $this->createInteractiveEntityButton($organisationInfo['name'], $organisationInfo['type'], 'organisation');
                        $sentenceButtons = [
                            $subjectButton,
                            $this->createConnectorButton('has role', 'connector'),
                            $objectButton,
                            $this->createConnectorButton('at', 'connector'),
                            $orgButton
                        ];
                    } else {
                        $sentenceButtons = [
                            $subjectButton,
                            $this->createConnectorButton('is a', 'connector'),
                            $objectButton
                        ];
                    }
                }
                break;
            case 'at_organisation':
                if ($isIncoming) {
                    $sentenceButtons = [
                        $objectButton,
                        $this->createConnectorButton('hosts', 'connector'),
                        $subjectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $this->createConnectorButton('is at', 'connector'),
                        $objectButton
                    ];
                }
                break;
            default:
                // For unknown types, provide a generic but informative sentence
                if ($isIncoming) {
                    $sentenceButtons = [
                        $objectButton,
                        $this->createConnectorButton('has', 'connector'),
                        $predicateButton,
                        $this->createConnectorButton('relationship with', 'connector'),
                        $subjectButton
                    ];
                } else {
                    $sentenceButtons = [
                        $subjectButton,
                        $this->createConnectorButton('is connected to', 'connector'),
                        $objectButton,
                        $this->createConnectorButton('via', 'connector'),
                        $predicateButton
                    ];
                }
        }
        
        // Add date information if available
        $dateButtons = $this->buildInteractiveDateButtons($connectionData);
        if ($dateButtons) {
            $sentenceButtons = array_merge($sentenceButtons, $dateButtons);
        }
        
        // Create the final button group with all elements
        return $this->createButtonGroup($sentenceButtons);
    }

    /**
     * Create a Bootstrap button group from an array of buttons
     */
    private function createButtonGroup(array $buttons): string
    {
        $buttonHtml = implode('', $buttons);
        return "<div class=\"btn-group\" role=\"group\">{$buttonHtml}</div>";
    }

    /**
     * Create an interactive button for connector words
     */
    private function createConnectorButton(string $text, string $type): string
    {
        return "<button type=\"button\" class=\"btn btn-outline-light text-dark btn-sm inactive\" disabled>
                    {$text}
                </button>";
    }

    /**
     * Build interactive date buttons for a connection
     */
    private function buildInteractiveDateButtons(array $connectionData): ?array
    {
        $dateButtons = [];
        
        // Check for start date
        if (!empty($connectionData['start_date'])) {
            $dateButtons[] = $this->createConnectorButton('from', 'connector');
            $dateButtons[] = $this->createInteractiveDateButton($connectionData['start_date'], 'start_date');
        }
        
        // Check for end date
        if (!empty($connectionData['end_date'])) {
            if (!empty($connectionData['start_date'])) {
                $dateButtons[] = $this->createConnectorButton('to', 'connector');
            } else {
                $dateButtons[] = $this->createConnectorButton('until', 'connector');
            }
            $dateButtons[] = $this->createInteractiveDateButton($connectionData['end_date'], 'end_date');
        }
        
        // Check for single date (no start/end range)
        if (empty($connectionData['start']) && empty($connectionData['end']) && !empty($connectionData['date'])) {
            $dateButtons[] = $this->createConnectorButton('on', 'connector');
            $dateButtons[] = $this->createInteractiveDateButton($connectionData['date'], 'date');
        }
        
        return !empty($dateButtons) ? $dateButtons : null;
    }

    /**
     * Create an interactive date button
     */
    private function createInteractiveDateButton(string $date, string $dateType): string
    {
        $formattedDate = $this->formatDateForTranslation($date);
        // Remove "on " or "in " prefixes for cleaner display
        $formattedDate = str_replace(['on ', 'in '], '', $formattedDate);
        
        return sprintf(
            '<button type="button" class="btn btn-outline-info btn-sm interactive-date" data-date="%s" data-date-type="%s" title="Edit date">%s</button>',
            htmlspecialchars($date),
            htmlspecialchars($dateType),
            htmlspecialchars($formattedDate)
        );
    }

    /**
     * Create an interactive button for entity names
     */
    private function createInteractiveEntityButton(string $name, string $type, string $role): string
    {
        $buttonClass = $this->getEntityButtonClass($type);
        $icon = $this->getEntityIcon($type);
        $dataAttributes = "data-entity-name=\"{$name}\" data-entity-type=\"{$type}\" data-role=\"{$role}\"";
        
        return "<button type=\"button\" class=\"btn {$buttonClass} btn-sm interactive-entity\" {$dataAttributes} data-bs-toggle=\"tooltip\" title=\"Click to edit {$name}\">
                    <i class=\"{$icon} me-1\"></i>{$name}
                </button>";
    }

    /**
     * Create an interactive button for relationship predicates
     */
    private function createInteractivePredicateButton(string $connectionType, bool $isIncoming = false): string
    {
        $predicate = $this->getPredicateText($connectionType, $isIncoming);
        $dataAttributes = "data-connection-type=\"{$connectionType}\" data-is-incoming=\"" . ($isIncoming ? 'true' : 'false') . "\"";
        
        return "<button type=\"button\" class=\"btn btn-outline-secondary btn-sm interactive-predicate\" {$dataAttributes} data-bs-toggle=\"tooltip\" title=\"Click to change relationship type\">
                    <i class=\"bi bi-arrow-left-right me-1\"></i>{$predicate}
                </button>";
    }

    /**
     * Create a color-coded badge for entity names based on their type
     */
    private function createEntityBadge(string $name, string $type): string
    {
        $badgeClass = $this->getEntityBadgeClass($type);
        return "<span class=\"badge {$badgeClass} me-1\">{$name}</span>";
    }

    /**
     * Create a badge for dates
     */
    private function createDateBadge(string $dateText): string
    {
        // Remove any "on" or "in" prefixes that might still be present
        $cleanDate = str_replace(['on ', 'in '], '', $dateText);
        return "<span class=\"badge bg-info text-dark me-1\">{$cleanDate}</span>";
    }

    /**
     * Get Bootstrap badge class for entity types
     */
    private function getEntityBadgeClass(string $type): string
    {
        switch ($type) {
            case 'person':
                return 'bg-person';
            case 'organisation':
                return 'bg-organisation';
            case 'place':
                return 'bg-place';
            case 'event':
                return 'bg-event';
            case 'thing':
                return 'bg-thing';
            case 'band':
                return 'bg-band';
            case 'role':
                return 'bg-secondary';
            case 'subtype':
                return 'bg-light text-dark';
            case 'type':
                return 'bg-secondary';
            default:
                return 'bg-secondary';
        }
    }

    /**
     * Get predicate text for connection types
     */
    private function getPredicateText(string $connectionType, bool $isIncoming = false): string
    {
        switch ($connectionType) {
            case 'education':
                return $isIncoming ? 'educated' : 'studied at';
            case 'employment':
                return $isIncoming ? 'employed' : 'worked at';
            case 'residence':
                return $isIncoming ? 'was home to' : 'lived in';
            case 'membership':
                return $isIncoming ? 'has member' : 'was a member of';
            case 'created':
                return $isIncoming ? 'was created by' : 'created';
            case 'friend':
                return 'is friends with';
            case 'relationship':
                return 'has a relationship with';
            case 'contains':
                return $isIncoming ? 'is contained in' : 'contains';
            case 'travel':
                return $isIncoming ? 'was visited by' : 'traveled to';
            case 'participation':
                return $isIncoming ? 'had participant' : 'participated in';
            case 'ownership':
                return $isIncoming ? 'was owned by' : 'owned';
            case 'has_role':
                return $isIncoming ? 'role is held by' : 'has role';
            case 'at_organisation':
                return $isIncoming ? 'hosts' : 'at';
            default:
                return $connectionType;
        }
    }

    /**
     * Format a date for natural language translation
     */
    private function formatDateForTranslation($date): string
    {
        if (is_string($date)) {
            $parts = explode('-', $date);
            if (count($parts) === 3) {
                return "on " . date('F j, Y', strtotime($date));
            } elseif (count($parts) === 2) {
                return "in " . date('F Y', strtotime($date . '-01'));
            } elseif (count($parts) === 1) {
                return "in {$date}";
            }
        }
        return "in {$date}";
    }

    /**
     * Get action word for span type (for start dates)
     */
    private function getActionWordForSpanType(string $type): string
    {
        switch ($type) {
            case 'person':
                return 'was born';
            case 'organisation':
                return 'was founded';
            case 'event':
                return 'began';
            case 'band':
                return 'was formed';
            default:
                return 'started';
        }
    }

    /**
     * Get end action word for span type (for end dates)
     */
    private function getEndActionWordForSpanType(string $type): string
    {
        switch ($type) {
            case 'person':
                return 'died';
            case 'organisation':
                return 'was dissolved';
            case 'event':
                return 'ended';
            case 'band':
                return 'disbanded';
            default:
                return 'ended';
        }
    }

    /**
     * Get button class for entity types
     */
    private function getEntityButtonClass(string $type): string
    {
        switch ($type) {
            case 'person':
                return 'btn-person';
            case 'organisation':
                return 'btn-organisation';
            case 'place':
                return 'btn-place';
            case 'event':
                return 'btn-event';
            case 'thing':
                return 'btn-thing';
            case 'band':
                return 'btn-band';
            case 'role':
                return 'btn-secondary';
            default:
                return 'btn-outline-secondary';
        }
    }

    /**
     * Get icon for entity types
     */
    private function getEntityIcon(string $type): string
    {
        switch ($type) {
            case 'person':
                return 'bi-person-fill';
            case 'organisation':
                return 'bi-building';
            case 'place':
                return 'bi-geo-alt-fill';
            case 'event':
                return 'bi-calendar-event-fill';
            case 'thing':
                return 'bi-box';
            case 'band':
                return 'bi-cassette';
            case 'role':
                return 'bi-person-badge';
            default:
                return 'bi-question-circle';
        }
    }

    /**
     * Check if a string looks like a date (YYYY, YYYY-MM, or YYYY-MM-DD)
     */
    private function looksLikeDate(string $value): bool
    {
        // Check for YYYY format
        if (preg_match('/^\d{4}$/', $value)) {
            $year = (int) $value;
            return $year >= 1 && $year <= 9999;
        }
        
        // Check for YYYY-MM format
        if (preg_match('/^\d{4}-\d{1,2}$/', $value)) {
            $parts = explode('-', $value);
            $year = (int) $parts[0];
            $month = (int) $parts[1];
            return $year >= 1 && $year <= 9999 && $month >= 1 && $month <= 12;
        }
        
        // Check for YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value)) {
            $parts = explode('-', $value);
            $year = (int) $parts[0];
            $month = (int) $parts[1];
            $day = (int) $parts[2];
            return $year >= 1 && $year <= 9999 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31;
        }
        
        return false;
    }

    /**
     * Create a new span from validated YAML data
     */
    public function createSpanFromYaml(array $data, ?string $ownerId = null): array
    {
        try {
            DB::beginTransaction();

            // Check for existing span by name and type_id
            $existingSpan = \App\Models\Span::where('name', $data['name'])
                ->where('type_id', $data['type'])
                ->first();
            if ($existingSpan) {
                DB::commit();
                return [
                    'success' => true,
                    'span' => $existingSpan,
                    'message' => 'Existing span found by name and type, no duplicate created.'
                ];
            }

            // Validate slug against reserved route names
            if (isset($data['slug']) && !empty($data['slug'])) {
                $reservedNames = $this->getReservedRouteNames();
                
                if (in_array(strtolower($data['slug']), array_map('strtolower', $reservedNames))) {
                    return [
                        'success' => false,
                        'message' => "The slug '{$data['slug']}' conflicts with a reserved route name. Please choose a different slug."
                    ];
                }
            }
            
            // Create the new span
            $span = Span::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
                'type_id' => $data['type'],
                'state' => $data['state'] ?? 'placeholder',
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'sources' => $data['sources'] ?? null,
                'start_year' => $data['start_year'] ?? null,
                'start_month' => $data['start_month'] ?? null,
                'start_day' => $data['start_day'] ?? null,
                'end_year' => $data['end_year'] ?? null,
                'end_month' => $data['end_month'] ?? null,
                'end_day' => $data['end_day'] ?? null,
                'access_level' => $data['access_level'] ?? 'private',
                'owner_id' => $ownerId ?? auth()->id(),
                'updater_id' => $ownerId ?? auth()->id(),
            ]);

            // Handle connections if present
            if (isset($data['connections'])) {
                $this->updateConnections($span, $data['connections']);
            }

            DB::commit();

            return [
                'success' => true,
                'span' => $span,
                'message' => 'New span created successfully from YAML'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create span from YAML', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to create span: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if a span with the given name and type already exists
     */
    public function findExistingSpan(string $name, string $type): ?Span
    {
        $span = Span::where('name', $name)
            ->where('type_id', $type)
            ->first();
            
        // Only return the span if the current user has permission to update it
        if ($span && auth()->check() && auth()->user()->can('update', $span)) {
            return $span;
        }
        
        return null;
    }

    /**
     * Merge YAML data with an existing span, preserving existing data while adding new information
     */
    public function mergeYamlWithExistingSpan(Span $existingSpan, array $newData): array
    {
        try {
            $mergedData = [];
            
            // Keep existing basic fields (don't overwrite name/type)
            $mergedData['name'] = $existingSpan->name;
            $mergedData['type_id'] = $existingSpan->type_id;
            $mergedData['slug'] = $existingSpan->slug;
            
            // Normalize and validate date fields before merging
            $dateFields = ['start_year', 'start_month', 'start_day', 'end_year', 'end_month', 'end_day'];
            foreach ($dateFields as $field) {
                $newValue = $newData[$field] ?? null;
                $existingValue = $existingSpan->$field ?? null;
                
                // Normalize the new value if present
                if ($newValue !== null) {
                    $normalizedValue = $this->normalizeFieldValue($field, $newValue);
                    // For date fields, ensure it's an integer or null
                    if ($normalizedValue !== null && !is_int($normalizedValue)) {
                        if (is_numeric($normalizedValue)) {
                            $normalizedValue = (int) $normalizedValue;
                        } else {
                            Log::warning("Invalid date field '{$field}' value, using existing value", [
                                'field' => $field,
                                'new_value' => $newValue,
                                'new_value_type' => gettype($newValue),
                                'normalized_value' => $normalizedValue,
                                'span_id' => $existingSpan->id
                            ]);
                            $normalizedValue = $existingValue;
                        }
                    }
                    $mergedData[$field] = $normalizedValue;
                } else {
                    $mergedData[$field] = $existingValue;
                }
            }
            
            // Reconstruct start and end strings if date parts are present
            if (!empty($mergedData['start_year'])) {
                try {
                    $startYear = (int) $mergedData['start_year'];
                    $start = (string) $startYear;
                    if (!empty($mergedData['start_month'])) {
                        $startMonth = (int) $mergedData['start_month'];
                        $start .= '-' . str_pad($startMonth, 2, '0', STR_PAD_LEFT);
                        if (!empty($mergedData['start_day'])) {
                            $startDay = (int) $mergedData['start_day'];
                            $start .= '-' . str_pad($startDay, 2, '0', STR_PAD_LEFT);
                        }
                    }
                    $mergedData['start'] = $start;
                } catch (\Exception $e) {
                    Log::error('Failed to construct start date string', [
                        'start_year' => $mergedData['start_year'],
                        'start_month' => $mergedData['start_month'],
                        'start_day' => $mergedData['start_day'],
                        'error' => $e->getMessage(),
                        'span_id' => $existingSpan->id
                    ]);
                }
            }
            
            if (!empty($mergedData['end_year'])) {
                try {
                    $endYear = (int) $mergedData['end_year'];
                    $end = (string) $endYear;
                    if (!empty($mergedData['end_month'])) {
                        $endMonth = (int) $mergedData['end_month'];
                        $end .= '-' . str_pad($endMonth, 2, '0', STR_PAD_LEFT);
                        if (!empty($mergedData['end_day'])) {
                            $endDay = (int) $mergedData['end_day'];
                            $end .= '-' . str_pad($endDay, 2, '0', STR_PAD_LEFT);
                        }
                    }
                    $mergedData['end'] = $end;
                } catch (\Exception $e) {
                    Log::error('Failed to construct end date string', [
                        'end_year' => $mergedData['end_year'],
                        'end_month' => $mergedData['end_month'],
                        'end_day' => $mergedData['end_day'],
                        'error' => $e->getMessage(),
                        'span_id' => $existingSpan->id
                    ]);
                }
            }
            
            // Normalize and merge description and notes
            $mergedData['description'] = $this->normalizeFieldValue('description', $newData['description'] ?? $existingSpan->description);
            $mergedData['notes'] = $this->normalizeFieldValue('notes', $newData['notes'] ?? $existingSpan->notes);
            
            // Merge metadata (combine both, new data takes precedence for overlapping keys)
            $existingMetadata = $existingSpan->metadata ?? [];
            $newMetadata = $newData['metadata'] ?? [];
            
            // Ensure both are arrays
            if (!is_array($existingMetadata)) {
                Log::warning("Existing metadata is not an array", [
                    'span_id' => $existingSpan->id,
                    'metadata_type' => gettype($existingMetadata),
                    'metadata_value' => $existingMetadata
                ]);
                $existingMetadata = [];
            }
            if (!is_array($newMetadata)) {
                Log::warning("New metadata is not an array, normalizing", [
                    'span_id' => $existingSpan->id,
                    'metadata_type' => gettype($newMetadata),
                    'metadata_value' => $newMetadata
                ]);
                $newMetadata = $this->normalizeFieldValue('metadata', $newMetadata);
                if (!is_array($newMetadata)) {
                    $newMetadata = [];
                }
            }
            $mergedData['metadata'] = array_merge($existingMetadata, $newMetadata);
            
            // Merge sources (combine and deduplicate)
            $existingSources = $existingSpan->sources ?? [];
            $newSources = $newData['sources'] ?? [];
            
            // Ensure both are arrays
            if (!is_array($existingSources)) {
                Log::warning("Existing sources is not an array", [
                    'span_id' => $existingSpan->id,
                    'sources_type' => gettype($existingSources),
                    'sources_value' => $existingSources
                ]);
                $existingSources = [];
            }
            if (!is_array($newSources)) {
                Log::warning("New sources is not an array, normalizing", [
                    'span_id' => $existingSpan->id,
                    'sources_type' => gettype($newSources),
                    'sources_value' => $newSources
                ]);
                $newSources = $this->normalizeFieldValue('sources', $newSources);
                if (!is_array($newSources)) {
                    $newSources = [];
                }
            }
            
            // Merge and deduplicate sources
            try {
                $mergedSources = array_merge($existingSources, $newSources);
                $mergedData['sources'] = array_values(array_unique($mergedSources, SORT_REGULAR));
            } catch (\Exception $e) {
                Log::error('Failed to merge sources', [
                    'span_id' => $existingSpan->id,
                    'existing_sources' => $existingSources,
                    'existing_sources_type' => gettype($existingSources),
                    'new_sources' => $newSources,
                    'new_sources_type' => gettype($newSources),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Use existing sources as fallback
                $mergedData['sources'] = is_array($existingSources) ? $existingSources : [];
            }
            
            // Keep existing access level
            $mergedData['access_level'] = $existingSpan->access_level;
            
            // Determine state (upgrade to complete if we now have dates)
            $hasDates = (!empty($mergedData['start_year']) || !empty($mergedData['end_year']));
            if ($hasDates && $existingSpan->state === 'placeholder') {
                $mergedData['state'] = 'complete';
            } else {
                $mergedData['state'] = $existingSpan->state ?? 'complete';
            }
            
            // Merge connections (add new ones, preserve existing ones)
            try {
                $mergedData['connections'] = $this->mergeConnections($existingSpan, $newData['connections'] ?? []);
            } catch (\Exception $e) {
                Log::error('Failed to merge connections', [
                    'span_id' => $existingSpan->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Use empty connections as fallback
                $mergedData['connections'] = [];
            }
            
            return $mergedData;
            
        } catch (\Exception $e) {
            // Log detailed error information
            Log::error('Failed to merge YAML with existing span', [
                'span_id' => $existingSpan->id,
                'span_name' => $existingSpan->name,
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'new_data_keys' => array_keys($newData),
                'new_data_sample' => array_map(function($v) {
                    if (is_array($v)) {
                        return '[array with ' . count($v) . ' elements]';
                    }
                    if (is_string($v) && strlen($v) > 200) {
                        return substr($v, 0, 200) . '...';
                    }
                    return $v;
                }, $newData)
            ]);
            
            // Re-throw with more context
            throw new \InvalidArgumentException(
                "Failed to merge YAML data with existing span: {$e->getMessage()}. " .
                "This is likely a data type conversion error. Check that all fields have the correct data types. " .
                "Error at: " . basename($e->getFile()) . ":" . $e->getLine(),
                0,
                $e
            );
        }
    }

    /**
     * Merge connections from new YAML with existing connections
     */
    private function mergeConnections(Span $existingSpan, array $newConnections): array
    {
        // Load existing connections
        $existingSpan->load([
            'connectionsAsSubject.child',
            'connectionsAsSubject.type',
            'connectionsAsObject.parent',
            'connectionsAsObject.type'
        ]);
        
        $mergedConnections = [];
        
        // Process new connections
        foreach ($newConnections as $connectionType => $connections) {
            // Skip virtual/readonly connection types
            if (str_ends_with($connectionType, '_incoming')) {
                continue;
            }
            if (!isset($mergedConnections[$connectionType])) {
                $mergedConnections[$connectionType] = [];
            }
            
            foreach ($connections as $connection) {
                $targetName = $connection['name'] ?? '';
                $targetId = $connection['id'] ?? null;
                
                // Check if this connection already exists
                $existingConnection = $this->findExistingConnection($existingSpan, $connectionType, $targetName, $targetId);
                
                if ($existingConnection) {
                    // Merge existing connection with new data
                    $mergedConnection = $this->mergeConnectionData($existingConnection, $connection);
                    $mergedConnections[$connectionType][] = $mergedConnection;
                } else {
                    // Add new connection
                    $mergedConnections[$connectionType][] = $connection;
                }
            }
        }
        
        // Add existing connections that weren't in the new YAML
        $this->addRemainingExistingConnections($existingSpan, $mergedConnections);
        
        return $mergedConnections;
    }

    /**
     * Find an existing connection by type and target
     */
    private function findExistingConnection(Span $span, string $connectionType, string $targetName, ?string $targetId): ?array
    {
        // Check outgoing connections
        foreach ($span->connectionsAsSubject as $connection) {
            if ($connection->type_id === $connectionType) {
                $child = $connection->child;
                if (($targetId && $child->id === $targetId) || 
                    (!$targetId && $child->name === $targetName)) {
                    return $this->connectionToArray($connection, 'outgoing');
                }
            }
        }
        
        // Check incoming connections
        foreach ($span->connectionsAsObject as $connection) {
            if ($connection->type_id === $connectionType) {
                $parent = $connection->parent;
                if (($targetId && $parent->id === $targetId) || 
                    (!$targetId && $parent->name === $targetName)) {
                    return $this->connectionToArray($connection, 'incoming');
                }
            }
        }
        
        return null;
    }

    /**
     * Convert a connection model to array format
     */
    private function connectionToArray($connection, string $direction): array
    {
        $targetSpan = $direction === 'outgoing' ? $connection->child : $connection->parent;
        
        $connectionData = [
            'name' => $targetSpan->name,
            'id' => $targetSpan->id,
            'type' => $targetSpan->type_id,
            'connection_id' => $connection->id,
        ];
        
        // Add connection dates if available
        if ($connection->connectionSpan) {
            $this->addConnectionDates($connectionData, $connection->connectionSpan);
        }
        
        return $connectionData;
    }

    /**
     * Merge connection data, preserving existing dates/metadata while adding new ones
     */
    private function mergeConnectionData(array $existingConnection, array $newConnection): array
    {
        $merged = $existingConnection;
        
        // Merge dates (only add if not already present)
        if (!isset($merged['start']) && isset($newConnection['start'])) {
            $merged['start'] = $newConnection['start'];
        }
        if (!isset($merged['end']) && isset($newConnection['end'])) {
            $merged['end'] = $newConnection['end'];
        }
        
        // Merge metadata
        $existingMetadata = $merged['metadata'] ?? [];
        $newMetadata = $newConnection['metadata'] ?? [];
        $merged['metadata'] = array_merge($existingMetadata, $newMetadata);
        
        return $merged;
    }

    /**
     * Add existing connections that weren't in the new YAML
     */
    private function addRemainingExistingConnections(Span $existingSpan, array &$mergedConnections): void
    {
        // Track which connections we've already processed
        $processedConnections = [];
        foreach ($mergedConnections as $type => $connections) {
            foreach ($connections as $connection) {
                $key = $type . ':' . ($connection['id'] ?? $connection['name']);
                $processedConnections[$key] = true;
            }
        }
        
        // Add outgoing connections that weren't processed
        foreach ($existingSpan->connectionsAsSubject as $connection) {
            $key = $connection->type_id . ':' . $connection->child->id;
            if (!isset($processedConnections[$key])) {
                if (!isset($mergedConnections[$connection->type_id])) {
                    $mergedConnections[$connection->type_id] = [];
                }
                $mergedConnections[$connection->type_id][] = $this->connectionToArray($connection, 'outgoing');
            }
        }
        
        // Add incoming connections that weren't processed
        foreach ($existingSpan->connectionsAsObject as $connection) {
            $key = $connection->type_id . ':' . $connection->parent->id;
            if (!isset($processedConnections[$key])) {
                if (!isset($mergedConnections[$connection->type_id])) {
                    $mergedConnections[$connection->type_id] = [];
                }
                $mergedConnections[$connection->type_id][] = $this->connectionToArray($connection, 'incoming');
            }
        }
    }

    /**
     * Normalize field value to expected type for Span model
     */
    private function normalizeFieldValue(string $fieldName, $value)
    {
        // Handle null values
        if ($value === null) {
            return null;
        }

        // Field type mappings based on Span model casts and fillable
        $fieldTypes = [
            'name' => 'string',
            'slug' => 'string',
            'type_id' => 'string',
            'state' => 'string',
            'description' => 'string',
            'notes' => 'string',
            'access_level' => 'string',
            'start_year' => 'integer',
            'start_month' => 'integer',
            'start_day' => 'integer',
            'end_year' => 'integer',
            'end_month' => 'integer',
            'end_day' => 'integer',
            'metadata' => 'array',
            'sources' => 'array',
        ];

        $expectedType = $fieldTypes[$fieldName] ?? null;
        
        if ($expectedType === null) {
            return $value; // Unknown field, return as-is
        }

        // Get actual type
        $actualType = is_array($value) ? 'array' : gettype($value);

        // If types match, return as-is
        if ($actualType === $expectedType) {
            return $value;
        }

        // Normalize based on expected type
        try {
            switch ($expectedType) {
                case 'string':
                    if (is_array($value)) {
                        // If it's an array, try to convert to JSON string or take first element
                        if (empty($value)) {
                            return '';
                        }
                        // If array has string keys, it's likely metadata that should be kept as array
                        if ($fieldName === 'metadata' || $fieldName === 'sources') {
                            return $value; // These should be arrays, not strings
                        }
                        // For other fields, try to serialize or take first value
                        Log::warning("Array to string conversion for field '{$fieldName}'", [
                            'field' => $fieldName,
                            'value' => $value,
                            'value_type' => gettype($value)
                        ]);
                        return is_array($value) && !empty($value) ? (string) reset($value) : '';
                    }
                    return (string) $value;
                
                case 'integer':
                    if (is_array($value)) {
                        Log::warning("Array to integer conversion for field '{$fieldName}'", [
                            'field' => $fieldName,
                            'value' => $value,
                            'value_type' => gettype($value)
                        ]);
                        return null; // Can't convert array to integer
                    }
                    if (is_string($value) && is_numeric($value)) {
                        return (int) $value;
                    }
                    if (is_numeric($value)) {
                        return (int) $value;
                    }
                    return null;
                
                case 'array':
                    if (is_string($value)) {
                        // Try to decode JSON string
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return $decoded;
                        }
                        // If not JSON, return as single-element array
                        return [$value];
                    }
                    if (is_object($value)) {
                        return (array) $value;
                    }
                    return is_array($value) ? $value : [$value];
                
                default:
                    return $value;
            }
        } catch (\Exception $e) {
            Log::error("Failed to normalize field '{$fieldName}'", [
                'field' => $fieldName,
                'expected_type' => $expectedType,
                'actual_type' => $actualType,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            throw new \InvalidArgumentException(
                "Cannot normalize field '{$fieldName}': expected {$expectedType}, got {$actualType}. " .
                "Value: " . (is_array($value) ? json_encode($value) : (string) $value) . ". " .
                "Error: " . $e->getMessage()
            );
        }
    }

    /**
     * Apply merged YAML data to an existing span
     */
    public function applyMergedYamlToSpan(Span $span, array $mergedData): array
    {
        try {
            DB::beginTransaction();
            
            // Normalize and validate all fields before update
            $updateData = [];
            $fieldErrors = [];
            
            $fieldsToUpdate = [
                'name', 'slug', 'type_id', 'state', 'description', 'notes',
                'metadata', 'sources', 'start_year', 'start_month', 'start_day',
                'end_year', 'end_month', 'end_day', 'access_level'
            ];
            
            foreach ($fieldsToUpdate as $field) {
                try {
                    $value = $mergedData[$field] ?? null;
                    
                    // Apply defaults for certain fields
                    if ($field === 'state' && $value === null) {
                        $value = 'complete';
                    }
                    if ($field === 'access_level' && $value === null) {
                        $value = $span->access_level;
                    }
                    if ($field === 'metadata' && $value === null) {
                        $value = [];
                    }
                    
                    // Normalize the value
                    $normalizedValue = $this->normalizeFieldValue($field, $value);
                    
                    // Additional validation for required fields
                    if ($field === 'name' && (empty($normalizedValue) || !is_string($normalizedValue))) {
                        throw new \InvalidArgumentException("Field 'name' must be a non-empty string, got: " . gettype($normalizedValue));
                    }
                    if ($field === 'type_id' && (empty($normalizedValue) || !is_string($normalizedValue))) {
                        throw new \InvalidArgumentException("Field 'type_id' must be a non-empty string, got: " . gettype($normalizedValue));
                    }
                    
                    $updateData[$field] = $normalizedValue;
                    
                } catch (\Exception $e) {
                    $fieldErrors[$field] = [
                        'error' => $e->getMessage(),
                        'value' => $mergedData[$field] ?? null,
                        'value_type' => gettype($mergedData[$field] ?? null)
                    ];
                    Log::error("Failed to normalize field '{$field}' in applyMergedYamlToSpan", [
                        'field' => $field,
                        'error' => $e->getMessage(),
                        'value' => $mergedData[$field] ?? null,
                        'value_type' => gettype($mergedData[$field] ?? null),
                        'span_id' => $span->id
                    ]);
                }
            }
            
            // If we have field errors, return detailed error message
            if (!empty($fieldErrors)) {
                $errorDetails = [];
                foreach ($fieldErrors as $field => $errorInfo) {
                    $errorDetails[] = "Field '{$field}': {$errorInfo['error']} (value type: {$errorInfo['value_type']})";
                }
                $errorMessage = "Failed to normalize fields: " . implode('; ', $errorDetails);
                
                Log::error('Field normalization errors in applyMergedYamlToSpan', [
                    'span_id' => $span->id,
                    'field_errors' => $fieldErrors,
                    'merged_data_keys' => array_keys($mergedData),
                    'merged_data_sample' => array_map(function($v) {
                        return is_array($v) ? '[array]' : (is_string($v) && strlen($v) > 100 ? substr($v, 0, 100) . '...' : $v);
                    }, $mergedData)
                ]);
                
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'field_errors' => $fieldErrors
                ];
            }
            
            // Update basic span fields
            $span->update($updateData);

            // Handle connections if present
            if (isset($mergedData['connections'])) {
                $this->updateConnections($span, $mergedData['connections']);
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Span updated successfully with merged YAML data'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Get detailed error information
            $errorDetails = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            
            // If it's a type error, extract more details
            if (strpos($e->getMessage(), 'Array to string conversion') !== false || 
                strpos($e->getMessage(), 'must be of the type') !== false) {
                $errorDetails['type_error'] = true;
                $errorDetails['merged_data_types'] = array_map('gettype', $mergedData);
            }
            
            Log::error('Failed to apply merged YAML to span', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'error' => $e->getMessage(),
                'error_details' => $errorDetails,
                'merged_data_keys' => array_keys($mergedData),
                'merged_data_sample' => array_map(function($v) {
                    if (is_array($v)) {
                        return '[array with ' . count($v) . ' elements]';
                    }
                    if (is_string($v) && strlen($v) > 200) {
                        return substr($v, 0, 200) . '...';
                    }
                    return $v;
                }, $mergedData)
            ]);
            
            // Build detailed error message
            $errorMessage = 'Failed to update span: ' . $e->getMessage();
            if (isset($errorDetails['type_error'])) {
                $errorMessage .= '. This is likely a type conversion error. Check that all fields have the correct data types.';
            }
            $errorMessage .= ' (File: ' . basename($e->getFile()) . ', Line: ' . $e->getLine() . ')';
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'error_details' => $errorDetails
            ];
        }
    }

    /**
     * Get reserved route names that cannot be used as span slugs
     */
    private function getReservedRouteNames(): array
    {
        return app(\App\Services\RouteReservationService::class)->getReservedRouteNames();
    }

    /**
     * Parse YAML content to span data for preview purposes (skips slug validation)
     */
    public function yamlToSpanDataForPreview(string $yamlContent, ?Span $span = null): array
    {
        try {
            $data = Yaml::parse($yamlContent);
            
            if (!is_array($data)) {
                return [
                    'success' => false,
                    'errors' => ['YAML must parse to an array']
                ];
            }
            
            // Normalize AI-generated data (e.g., convert JSON strings to arrays)
            $this->normalizeAiGeneratedData($data);
            
            // Filter out unsupported connection types before validation
            $this->filterUnsupportedConnections($data);
            
            // Use the preview validation service for schema validation (skips slug validation)
            $validationService = new YamlValidationService();
            $schemaErrors = $validationService->validateSchemaForPreview($data);
            
            if (!empty($schemaErrors)) {
                return [
                    'success' => false,
                    'errors' => $schemaErrors
                ];
            }
            
            // Then validate required fields
            $this->validateRequiredFields($data);
            
            // Validate and normalize dates
            $this->validateAndNormalizeDates($data);
            
            // Validate connections
            $this->validateConnections($data);
            
            // Analyze the impacts if we have a span context
            $impacts = [];
            if ($span) {
                $impacts = $this->analyzeChangeImpacts($data, $span);
            }
            
            return [
                'success' => true,
                'data' => $data,
                'impacts' => $impacts
            ];
            
        } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
            return [
                'success' => false,
                'errors' => ['Invalid YAML syntax: ' . $e->getMessage()]
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'errors' => [ $e->getMessage() ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Unexpected error: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Safely convert a span to array format without deep nesting issues
     */
    public function spanToArraySafe(Span $span): array
    {
        try {
            // Set a reasonable limit for serialization depth
            $maxDepth = 5;
            $currentDepth = 0;
            
            return $this->spanToArrayWithDepthLimit($span, $maxDepth, $currentDepth);
        } catch (\Exception $e) {
            Log::error('Failed to safely convert span to array', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'error' => $e->getMessage()
            ]);
            
            // Return a minimal safe representation
            return [
                'id' => $span->id,
                'name' => $span->name,
                'type' => $span->type_id,
                'state' => $span->state,
                'description' => $span->description,
                'notes' => $span->notes,
                'metadata' => $span->metadata ?? [],
                'error' => 'Serialization failed due to deep nesting'
            ];
        }
    }

    /**
     * Convert span to array with depth limiting to prevent circular references
     */
    private function spanToArrayWithDepthLimit(Span $span, int $maxDepth, int $currentDepth): array
    {
        if ($currentDepth >= $maxDepth) {
            return [
                'id' => $span->id,
                'name' => $span->name,
                'type' => $span->type_id,
                'note' => 'Max depth reached - truncated for performance'
            ];
        }

        $data = [
            'id' => $span->id,
            'name' => $span->name,
            'type' => $span->type_id,
            'state' => $span->state,
            'description' => $span->description,
            'notes' => $span->notes,
            'metadata' => $span->metadata ?? [],
            'sources' => $span->sources ?? [],
            'access_level' => $span->access_level,
        ];
        
        // Ensure required metadata fields are present for place spans
        if ($span->type_id === 'place' && !isset($data['metadata']['subtype'])) {
            $data['metadata']['subtype'] = 'city_district'; // Default to a common place type
        }
        
        // Ensure required metadata fields are present for organisation spans
        if ($span->type_id === 'organisation' && !isset($data['metadata']['subtype'])) {
            $data['metadata']['subtype'] = 'corporation'; // Default to a common organisation type
        }
        
        // Ensure required metadata fields are present for person spans
        if ($span->type_id === 'person' && !isset($data['metadata']['subtype'])) {
            $data['metadata']['subtype'] = 'private_individual'; // Default to private individual
        }

        // Add dates if they exist
        if ($span->start_year) {
            $data['start'] = $this->formatDate($span->start_year, $span->start_month, $span->start_day);
        }
        if ($span->end_year) {
            $data['end'] = $this->formatDate($span->end_year, $span->end_month, $span->end_day);
        }

        // Add connections with depth limiting
        $connections = [];
        $connectionTypes = ['has_role', 'at_organisation', 'during', 'contains', 'located', 'created', 'features'];
        
        foreach ($connectionTypes as $connectionType) {
            $typeConnections = $this->getConnectionsForType($span, $connectionType, $maxDepth, $currentDepth + 1);
            if (!empty($typeConnections)) {
                $connections[$connectionType] = $typeConnections;
            }
        }

        if (!empty($connections)) {
            $data['connections'] = $connections;
        }

        return $data;
    }

    /**
     * Get connections for a specific type with depth limiting
     */
    private function getConnectionsForType(Span $span, string $connectionType, int $maxDepth, int $currentDepth): array
    {
        try {
            $connections = [];
            
            // Get connections as subject
            $subjectConnections = $span->connectionsAsSubject()
                ->where('type_id', $connectionType)
                ->with(['child:id,name,type_id,start_year,end_year,metadata'])
                ->limit(50) // Limit to prevent excessive data
                ->get();

            foreach ($subjectConnections as $connection) {
                $connectionData = [
                    'name' => $connection->child->name,
                    'type' => $connection->child->type_id,
                    'id' => $connection->child->id,
                ];

                // Add dates if they exist
                if ($connection->child->start_year) {
                    $connectionData['start'] = $this->formatDate(
                        $connection->child->start_year,
                        $connection->child->start_month,
                        $connection->child->start_day
                    );
                }
                if ($connection->child->end_year) {
                    $connectionData['end'] = $this->formatDate(
                        $connection->child->end_year,
                        $connection->child->end_month,
                        $connection->child->end_day
                    );
                }

                // Add metadata if it exists and is not too deep
                if ($connection->child->metadata && $currentDepth < $maxDepth - 1) {
                    $connectionData['metadata'] = $connection->child->metadata;
                }

                $connections[] = $connectionData;
            }

            return $connections;
        } catch (\Exception $e) {
            Log::warning("Failed to get connections for type: $connectionType", [
                'span_id' => $span->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Format date components into a string
     */
    private function formatDate(?int $year, ?int $month = null, ?int $day = null): string
    {
        if (!$year) {
            return '';
        }

        if ($month && $day) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        } elseif ($month) {
            return sprintf('%04d-%02d', $year, $month);
        } else {
            return (string) $year;
        }
    }

    /**
     * Convert a span to YAML format safely (prevents deep nesting issues)
     */
    public function spanToYamlSafe(Span $span): string
    {
        $data = $this->spanToArraySafe($span);
        
        // Use a custom YAML dump that ensures dates are quoted
        return $this->dumpYamlWithQuotedDates($data, 4, 2);
    }
} 
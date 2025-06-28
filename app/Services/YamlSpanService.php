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
        return Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    /**
     * Convert a span to array format (for YAML conversion)
     */
    public function spanToArray(Span $span): array
    {
        // Load all necessary relationships including connection spans for dates
        $span->load([
            'connectionsAsSubject.child.type',
            'connectionsAsObject.parent.type', 
            'connectionsAsSubject.type',
            'connectionsAsObject.type',
            'connectionsAsSubject.connectionSpan',
            'connectionsAsObject.connectionSpan',
            'type',
            'owner'
        ]);

        $data = [
            'id' => $span->id,
            'name' => $span->name,
            'type' => $span->type_id,
            'state' => $span->state,
        ];

        // Add dates if present
        if ($span->start_year) {
            $start = (string) $span->start_year;
            if ($span->start_month) {
                $start .= '-' . str_pad($span->start_month, 2, '0', STR_PAD_LEFT);
                if ($span->start_day) {
                    $start .= '-' . str_pad($span->start_day, 2, '0', STR_PAD_LEFT);
                }
            }
            $data['start'] = $start;
        }

        if ($span->end_year) {
            $end = (string) $span->end_year;
            if ($span->end_month) {
                $end .= '-' . str_pad($span->end_month, 2, '0', STR_PAD_LEFT);
                if ($span->end_day) {
                    $end .= '-' . str_pad($span->end_day, 2, '0', STR_PAD_LEFT);
                }
            }
            $data['end'] = $end;
        } else {
            $data['end'] = null;
        }

        // Add optional fields
        if ($span->description) {
            $data['description'] = $span->description;
        }
        if ($span->notes) {
            $data['notes'] = $span->notes;
        }

        // Add metadata (excluding empty arrays/nulls)
        if (!empty($span->metadata)) {
            $data['metadata'] = $span->metadata;
        }

        // Add sources if present
        if (!empty($span->sources)) {
            $data['sources'] = $span->sources;
        }

        // Add access control information
        $data['access_level'] = $span->access_level;
        $data['permissions'] = $span->permissions;
        $data['permission_mode'] = $span->permission_mode;

        // Group connections by type with special handling for family relationships
        $connections = [];
        
        // Process outgoing connections (where this span is the subject/parent)
        foreach ($span->connectionsAsSubject as $connection) {
            $connectionType = $connection->type_id;
            $targetSpan = $connection->child;
            
            $connectionData = [
                'name' => $targetSpan->name,
                'id' => $targetSpan->id,
                'type' => $targetSpan->type_id,
            ];
            
            // Add connection dates from the connection span if available
            if ($connection->connectionSpan) {
                $this->addConnectionDates($connectionData, $connection->connectionSpan);
                
                // Add nested connections from the connection span
                $nestedConnections = $this->getNestedConnections($connection->connectionSpan);
                if (!empty($nestedConnections)) {
                    $connectionData['nested_connections'] = $nestedConnections;
                }
                
                // Special handling for has_role connections: extract dates from nested at_organisation connection span
                if ($connectionType === 'has_role' && !isset($connectionData['start_date']) && !isset($connectionData['end_date'])) {
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
            ];
            
            // Add connection dates from the connection span if available
            if ($connection->connectionSpan) {
                $this->addConnectionDates($connectionData, $connection->connectionSpan);
                
                // Add nested connections from the connection span
                $nestedConnections = $this->getNestedConnections($connection->connectionSpan);
                if (!empty($nestedConnections)) {
                    $connectionData['nested_connections'] = $nestedConnections;
                }
                
                // Special handling for has_role connections: extract dates from nested at_organisation connection span
                if ($connectionType === 'has_role' && !isset($connectionData['start_date']) && !isset($connectionData['end_date'])) {
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
    }

    /**
     * Convert YAML string to span data (validation only, no database writes)
     */
    public function yamlToSpanData(string $yamlContent): array
    {
        try {
            $data = Yaml::parse($yamlContent);
            
            if (!is_array($data)) {
                throw new \InvalidArgumentException('YAML must parse to an array');
            }
            
            // Validate required fields
            $this->validateRequiredFields($data);
            
            // Validate and normalize dates
            $this->validateAndNormalizeDates($data);
            
            // Validate connections
            $this->validateConnections($data);
            
            // Analyze potential impacts of changes
            $impacts = $this->analyzeChangeImpacts($data);
            
            // Generate visual translation
            $visual = $this->translateToVisual($data);
            
            return [
                'success' => true,
                'data' => $data,
                'errors' => [],
                'impacts' => $impacts,
                'visual' => $visual
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'errors' => [$e->getMessage()],
                'impacts' => [],
                'visual' => []
            ];
        }
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
                'permissions' => $validatedData['permissions'] ?? $span->permissions,
                'permission_mode' => $validatedData['permission_mode'] ?? $span->permission_mode,
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
            $connectionData['start_date'] = $start;
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
            $connectionData['end_date'] = $end;
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
                    $connectionData['start_date'] = $start;
                }

                if ($nestedConnectionSpan->end_year) {
                    $end = (string) $nestedConnectionSpan->end_year;
                    if ($nestedConnectionSpan->end_month) {
                        $end .= '-' . str_pad($nestedConnectionSpan->end_month, 2, '0', STR_PAD_LEFT);
                        if ($nestedConnectionSpan->end_day) {
                            $end .= '-' . str_pad($nestedConnectionSpan->end_day, 2, '0', STR_PAD_LEFT);
                        }
                    }
                    $connectionData['end_date'] = $end;
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
                    'id' => $nestedConnection['target_id']
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
        if (isset($data['start'])) {
            $parsedStart = $this->parseDate($data['start']);
            $data['start_year'] = $parsedStart['year'];
            $data['start_month'] = $parsedStart['month'];
            $data['start_day'] = $parsedStart['day'];
        }
        
        if (isset($data['end']) && $data['end'] !== null) {
            $parsedEnd = $this->parseDate($data['end']);
            $data['end_year'] = $parsedEnd['year'];
            $data['end_month'] = $parsedEnd['month'];
            $data['end_day'] = $parsedEnd['day'];
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
            // Allow _incoming variants for backward compatibility
            if (!in_array($connectionType, $validConnectionTypes) && !str_ends_with($connectionType, '_incoming')) {
                throw new \InvalidArgumentException("Unknown connection type '{$connectionType}'. Valid types are: " . implode(', ', $validConnectionTypes));
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
                $requiredFields = ['name', 'id', 'type'];
                foreach ($requiredFields as $field) {
                    if (!isset($connection[$field])) {
                        throw new \InvalidArgumentException("Connection '{$connection['name']}' in '{$connectionType}' is missing required field '{$field}'");
                    }
                }
                
                // Validate date fields if present
                if (isset($connection['start_date'])) {
                    try {
                        $this->parseDate($connection['start_date']);
                    } catch (\InvalidArgumentException $e) {
                        throw new \InvalidArgumentException("Invalid start_date for connection '{$connection['name']}' in '{$connectionType}': " . $e->getMessage());
                    }
                }
                
                if (isset($connection['end_date'])) {
                    try {
                        $this->parseDate($connection['end_date']);
                    } catch (\InvalidArgumentException $e) {
                        throw new \InvalidArgumentException("Invalid end_date for connection '{$connection['name']}' in '{$connectionType}': " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Update connections for a span
     */
    private function updateConnections(Span $span, array $connectionsData): void
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
                    // Handle special family connection mapping
                    $actualConnectionType = $this->mapConnectionType($connectionType);
                    
                    // Parse connection dates if present
                    $dates = null;
                    if (isset($connectionData['start_date']) || isset($connectionData['end_date'])) {
                        $dates = $connectionImporter->parseDatesFromStrings(
                            $connectionData['start_date'] ?? null,
                            $connectionData['end_date'] ?? null
                        );
                    }
                    
                    // Prepare metadata
                    $metadata = $connectionData['metadata'] ?? [];
                    if (isset($connectionData['connection_metadata'])) {
                        $metadata = array_merge($metadata, $connectionData['connection_metadata']);
                    }
                    
                    // Find or create the connected span using the same logic as import
                    $connectedSpan = $connectionImporter->findOrCreateConnectedSpan(
                        $connectionData['name'],
                        $connectionData['type'],
                        null, // Don't pass connection dates as span dates
                        $metadata
                    );
                    
                    // Determine parent/child relationship based on connection type
                    [$parent, $child] = $this->determineConnectionDirection(
                        $span, 
                        $connectedSpan, 
                        $actualConnectionType, 
                        $connectionType
                    );
                    
                    // Create or update the connection
                    $connectionImporter->createConnection(
                        $parent,
                        $child,
                        $actualConnectionType,
                        $dates,
                        $metadata
                    );
                    
                    Log::info('YAML connection processed', [
                        'connection_type' => $actualConnectionType,
                        'parent' => $parent->name,
                        'child' => $child->name,
                        'connected_span_created' => $connectedSpan->wasRecentlyCreated ?? false
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
    private function determineConnectionDirection(Span $mainSpan, Span $connectedSpan, string $connectionType, string $yamlConnectionType): array
    {
        // For family connections, YAML structure determines direction
        if ($connectionType === 'family') {
            if ($yamlConnectionType === 'parents') {
                return [$connectedSpan, $mainSpan]; // Parent -> Child
            } elseif ($yamlConnectionType === 'children') {
                return [$mainSpan, $connectedSpan]; // Parent -> Child
            }
        }
        
        // For other connection types, use the main span as parent by default
        // This matches the YAML export structure where outgoing connections are listed
        return [$mainSpan, $connectedSpan];
    }

    /**
     * Analyze potential impacts of YAML changes
     */
    private function analyzeChangeImpacts(array $data): array
    {
        $impacts = [];
        
        if (!isset($data['id'])) {
            return $impacts;
        }
        
        // Find the original span
        $originalSpan = Span::find($data['id']);
        if (!$originalSpan) {
            $impacts[] = [
                'type' => 'warning',
                'message' => 'This appears to be a new span (ID not found in database)'
            ];
            return $impacts;
        }
        
        // Check for name changes
        if (isset($data['name']) && $data['name'] !== $originalSpan->name) {
            $impacts[] = [
                'type' => 'info',
                'message' => "Name will change from '{$originalSpan->name}' to '{$data['name']}'"
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
            } elseif ($newCount < $currentCount) {
                $removed = $currentCount - $newCount;
                $impacts[] = [
                    'type' => 'warning',
                    'message' => "Will remove {$removed} {$type} connection(s)"
                ];
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
        if (empty($connectionData['start_date']) && empty($connectionData['end_date']) && !empty($connectionData['date'])) {
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
                return 'bg-primary';
            case 'organisation':
                return 'bg-success';
            case 'place':
                return 'bg-warning';
            case 'event':
                return 'bg-danger';
            case 'thing':
                return 'bg-info';
            case 'band':
                return 'bg-dark';
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
                return 'btn-primary';
            case 'organisation':
                return 'btn-success';
            case 'place':
                return 'btn-warning';
            case 'event':
                return 'btn-danger';
            case 'thing':
                return 'btn-info';
            case 'band':
                return 'btn-dark';
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
} 
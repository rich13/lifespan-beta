<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\SpanType;
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
        // This is a complex operation that would need careful handling
        // For now, we'll leave existing connections unchanged to avoid data loss
        // In a full implementation, we'd need to:
        // 1. Compare existing connections with YAML connections
        // 2. Add new connections
        // 3. Remove connections not in YAML
        // 4. Update existing connections
        // 5. Handle the special family structure (parents/children vs family connections)
        
        // Map the YAML structure back to database connections
        $connectionTypes = [];
        foreach ($connectionsData as $key => $connections) {
            if ($key === 'parents') {
                $connectionTypes['family_incoming'] = $connections;
            } elseif ($key === 'children') {
                $connectionTypes['family_outgoing'] = $connections;
            } else {
                $connectionTypes[$key] = $connections;
            }
        }
        
        Log::info('Connection updates not yet implemented in YAML editor', [
            'span_id' => $span->id,
            'connections_in_yaml' => array_keys($connectionsData),
            'mapped_types' => array_keys($connectionTypes)
        ]);
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
                    $impacts[] = [
                        'type' => 'danger',
                        'message' => "Referenced {$type} span '{$connection['name']}' not found in database (ID: {$connection['id']})"
                    ];
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
     * Create a visual translation of YAML data into human-readable sentences
     */
    public function translateToVisual(array $data): array
    {
        $translations = [];
        
        // Basic span info with badges
        if (isset($data['name']) && isset($data['type'])) {
            $article = in_array(strtolower($data['type'][0] ?? ''), ['a', 'e', 'i', 'o', 'u']) ? 'an' : 'a';
            $nameBadge = $this->createEntityBadge($data['name'], $data['type']);
            $typeBadge = $this->createEntityBadge($data['type'], 'type');
            $translations[] = [
                'section' => 'identity',
                'text' => "{$nameBadge} is {$article} {$typeBadge}"
            ];
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
                
                $sentence = $this->buildConnectionSentence($spanName, $type, $connectionName, $connectionType, $connection, $spanType);
                if ($sentence) {
                    $translations[] = [
                        'section' => 'connections',
                        'subsection' => $type,
                        'text' => $sentence
                    ];
                }
            }
        }
        
        return $translations;
    }

    /**
     * Build a natural language sentence for a connection with color-coded badges
     */
    private function buildConnectionSentence(string $spanName, string $connectionType, string $connectionName, string $connectionSpanType, array $connectionData = [], string $spanType = 'thing'): ?string
    {
        // Handle _incoming variants by using inverse language
        $isIncoming = str_ends_with($connectionType, '_incoming');
        $baseType = $isIncoming ? str_replace('_incoming', '', $connectionType) : $connectionType;
        
        // Create badges for subject, object, and predicate
        $subjectBadge = $this->createEntityBadge($spanName, $spanType);
        $objectBadge = $this->createEntityBadge($connectionName, $connectionSpanType);
        $predicateBadge = $this->createPredicateBadge($baseType, $isIncoming);
        
        // Build the base sentence with badges
        $baseSentence = '';
        switch ($baseType) {
            case 'parents':
                $baseSentence = "{$subjectBadge} is the child of {$objectBadge}";
                break;
            case 'children':
                $baseSentence = "{$subjectBadge} is the parent of {$objectBadge}";
                break;
            case 'education':
                if ($isIncoming) {
                    $baseSentence = "{$objectBadge} {$predicateBadge} {$subjectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                }
                break;
            case 'employment':
                if ($isIncoming) {
                    $baseSentence = "{$objectBadge} {$predicateBadge} {$subjectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                }
                break;
            case 'residence':
                if ($isIncoming) {
                    $baseSentence = "{$objectBadge} was home to {$subjectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                }
                break;
            case 'membership':
                if ($isIncoming) {
                    $baseSentence = "{$subjectBadge} has member {$objectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                }
                break;
            case 'created':
                if ($isIncoming) {
                    $baseSentence = "{$objectBadge} was created by {$subjectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                }
                break;
            case 'friend':
                $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                break;
            case 'relationship':
                $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                break;
            case 'contains':
                if ($isIncoming) {
                    $baseSentence = "{$objectBadge} is contained in {$subjectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                }
                break;
            case 'travel':
                if ($isIncoming) {
                    $baseSentence = "{$objectBadge} was visited by {$subjectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                }
                break;
            case 'participation':
                if ($isIncoming) {
                    $baseSentence = "{$objectBadge} had participant {$subjectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                }
                break;
            case 'attendance':
                if ($isIncoming) {
                    $baseSentence = "{$objectBadge} was attended by {$subjectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                }
                break;
            case 'ownership':
                if ($isIncoming) {
                    $baseSentence = "{$objectBadge} was owned by {$subjectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} {$predicateBadge} {$objectBadge}";
                }
                break;
            default:
                // For unknown types, provide a generic but informative sentence
                if ($isIncoming) {
                    $baseSentence = "{$objectBadge} has {$predicateBadge} relationship with {$subjectBadge}";
                } else {
                    $baseSentence = "{$subjectBadge} is connected to {$objectBadge} via {$predicateBadge}";
                }
        }
        
        // Add date information if available
        $dateInfo = $this->buildDatePhrase($connectionData);
        if ($dateInfo) {
            $baseSentence .= " {$dateInfo}";
        }
        
        return $baseSentence;
    }

    /**
     * Build a date phrase for connections with badges (e.g., "from January 2005 to February 2006")
     */
    private function buildDatePhrase(array $connectionData): string
    {
        $hasStart = !empty($connectionData['start_date']);
        $hasEnd = !empty($connectionData['end_date']);
        
        if (!$hasStart && !$hasEnd) {
            return '';
        }
        
        $startPhrase = $hasStart ? $this->formatDateForTranslation($connectionData['start_date']) : '';
        $endPhrase = $hasEnd ? $this->formatDateForTranslation($connectionData['end_date']) : '';
        
        if ($hasStart && $hasEnd) {
            // Remove "on" or "in" prefixes for range formatting
            $startPhrase = str_replace(['on ', 'in '], '', $startPhrase);
            $endPhrase = str_replace(['on ', 'in '], '', $endPhrase);
            $startBadge = $this->createDateBadge($startPhrase);
            $endBadge = $this->createDateBadge($endPhrase);
            return "from {$startBadge} to {$endBadge}";
        } elseif ($hasStart) {
            $startBadge = $this->createDateBadge($startPhrase);
            return "starting {$startBadge}";
        } else {
            $endBadge = $this->createDateBadge($endPhrase);
            return "ending {$endBadge}";
        }
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
     * Create a badge for relationship predicates
     */
    private function createPredicateBadge(string $connectionType, bool $isIncoming = false): string
    {
        $predicate = $this->getPredicateText($connectionType, $isIncoming);
        return "<span class=\"badge bg-secondary me-1\">{$predicate}</span>";
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
                return 'bg-warning text-dark';
            case 'event':
                return 'bg-danger';
            case 'band':
                return 'bg-dark';
            case 'thing':
                return 'bg-secondary';
            case 'type':
                return 'bg-light text-dark border';
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
            case 'attendance':
                return $isIncoming ? 'was attended by' : 'attended';
            case 'ownership':
                return $isIncoming ? 'was owned by' : 'owned';
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
} 
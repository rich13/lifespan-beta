<?php

namespace App\Services;

use App\Models\Span;
use App\Models\User;
use App\Models\Connection;
use App\Models\ConnectionType;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\SpanController;
use Illuminate\Support\Facades\Cache;
use App\Services\Import\Connections\ConnectionImporter;

/**
 * Service for importing legacy span data from YAML files.
 * 
 * This service handles the import of span data from YAML files into the new system.
 * It supports both simulation (dry-run) and actual import modes, and handles:
 * - Main span data (person, organization, etc.)
 * - Family relationships (parents, children)
 * - Education history
 * - Work history
 * - Residences
 * - Other relationships
 * 
 * Each import operation generates a detailed report of what was/will be imported,
 * including any errors or warnings encountered during the process.
 */
class ImportService
{
    /** @var User The user performing the import */
    protected User $user;
    
    /** @var array The parsed YAML data being imported */
    protected array $yaml;
    
    /** @var ?Span The main span being imported */
    protected ?Span $mainSpan = null;
    
    /** @var array Import operation report */
    protected array $report;

    /**
     * Required connection types that must exist in the system
     * @var array
     */
    protected const REQUIRED_CONNECTION_TYPES = [
        'family',
        'education',
        'work',
        'residence',
        'relationship'
    ];

    /**
     * Required fields for the main span
     * @var array
     */
    protected const REQUIRED_MAIN_FIELDS = [
        'name',
        'type'
    ];

    /** @var SpanController */
    protected SpanController $spanController;

    /** @var Import\Connections\ConnectionImporter */
    protected Import\Connections\ConnectionImporter $connectionImporter;

    /**
     * Initialize a new import service instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->report = $this->getBaseReport();
        $this->spanController = new SpanController();
        $this->connectionImporter = new Import\Connections\ConnectionImporter($user);
    }

    /**
     * Perform the actual import operation.
     * 
     * This method imports the YAML data into the database, creating or updating
     * spans and their relationships as needed.
     * 
     * @param string $yamlPath Path to the YAML file to import
     * @return array Detailed report of what was imported
     */
    public function import(string $yamlPath): array
    {
        try {
            // Parse and validate YAML
            if (!$this->parseAndValidateYaml($yamlPath)) {
                return $this->report;
            }

            // Create or update main span
            $this->mainSpan = $this->createOrUpdateMainSpan();

            // Import relationships
            $this->importRelationships();

            return $this->report;

        } catch (\Exception $e) {
            Log::error('Import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->addError('import', $e->getMessage());
            return $this->report;
        }
    }

    /**
     * Parse and validate the YAML file
     */
    protected function parseAndValidateYaml(string $yamlPath): bool
    {
        try {
            $this->yaml = Yaml::parseFile($yamlPath);

            // Validate main span
            $errors = $this->validateMainSpan();
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addError('main_span', $error);
                }
                return false;
            }

            // Validate education spans
            if (!empty($this->yaml['education'])) {
                if (!is_array($this->yaml['education'])) {
                    $this->addError('education', 'Education data must be an array');
                    return false;
                }
                foreach ($this->yaml['education'] as $education) {
                    $errors = $this->validateSubspan($education, 'education');
                    foreach ($errors as $error) {
                        $this->addError('education', $error);
                    }
                }
            }

            // Validate work spans
            if (!empty($this->yaml['work'])) {
                if (!is_array($this->yaml['work'])) {
                    $this->addError('work', 'Work data must be an array');
                    return false;
                }
                foreach ($this->yaml['work'] as $work) {
                    $errors = $this->validateSubspan($work, 'work');
                    foreach ($errors as $error) {
                        $this->addError('work', $error);
                    }
                }
            }

            // Validate residence spans
            if (!empty($this->yaml['residences'])) {
                if (!is_array($this->yaml['residences'])) {
                    $this->addError('residences', 'Residences data must be an array');
                    return false;
                }
                foreach ($this->yaml['residences'] as $residence) {
                    $errors = $this->validateSubspan($residence, 'residence');
                    foreach ($errors as $error) {
                        $this->addError('residences', $error);
                    }
                }
            }

            return empty($this->report['errors']);

        } catch (\Exception $e) {
            $this->addError('yaml', 'Failed to parse YAML: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the base structure for the import report.
     */
    protected function getBaseReport(): array
    {
        return [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'main_span' => [
                'action' => null,
                'id' => null,
                'name' => null
            ],
            'family' => [
                'total' => 0,
                'existing' => 0,
                'created' => 0,
                'details' => []
            ],
            'education' => [
                'total' => 0,
                'existing' => 0,
                'created' => 0,
                'details' => []
            ],
            'work' => [
                'total' => 0,
                'existing' => 0,
                'created' => 0,
                'details' => []
            ],
            'residences' => [
                'total' => 0,
                'existing' => 0,
                'created' => 0,
                'details' => []
            ],
            'relationships' => [
                'total' => 0,
                'existing' => 0,
                'created' => 0,
                'connections_created' => 0,
                'connections_existing' => 0,
                'details' => []
            ]
        ];
    }

    protected function createSpanViaController(array $data): Span
    {
        // Create a request with the span data
        $request = Request::create('', 'POST', $data);
        $request->setUserResolver(fn() => $this->user);
        $request->headers->set('Accept', 'application/json');

        // Use the controller to create the span
        $response = $this->spanController->store($request);
        $responseData = json_decode($response->getContent(), true);

        return Span::findOrFail($responseData['id']);
    }

    public function createOrUpdateMainSpan(): Span
    {
        try {
            // Find existing span
            $existingSpan = null;
            if (isset($this->yaml['id'])) {
                $existingSpan = Span::find($this->yaml['id']);
            }
            if (!$existingSpan) {
                $existingSpan = Span::where('name', $this->yaml['name'])
                    ->where('type_id', $this->yaml['type'])
                    ->first();
            }

            // Parse dates
            $startDate = $this->parseDate($this->yaml['start'] ?? null);
            $endDate = $this->parseDate($this->yaml['end'] ?? null);

            // Prepare the data
            $data = [
                'name' => $this->yaml['name'],
                'type_id' => $this->yaml['type'],
                'state' => $startDate ? 'complete' : 'placeholder',
                'description' => $this->yaml['description'] ?? null,
                'notes' => $this->yaml['notes'] ?? null,
                'metadata' => $this->yaml['metadata'] ?? [],
                'sources' => $this->yaml['sources'] ?? [],
                'start_year' => $startDate ? $startDate->year : null,
                'start_month' => $startDate ? $startDate->month : null,
                'start_day' => $startDate ? $startDate->day : null,
                'end_year' => $endDate ? $endDate->year : null,
                'end_month' => $endDate ? $endDate->month : null,
                'end_day' => $endDate ? $endDate->day : null
            ];

            if ($existingSpan) {
                $data['id'] = $existingSpan->id;
                $data['access_level'] = $existingSpan->access_level;
                $existingSpan->update($data);
                $this->report['main_span']['action'] = 'updated';
                $this->report['main_span']['id'] = $existingSpan->id;
                $this->report['main_span']['name'] = $existingSpan->name;
                return $existingSpan;
            } else {
                $span = $this->createSpanViaController($data);
                $this->report['main_span']['action'] = 'created';
                $this->report['main_span']['id'] = $span->id;
                $this->report['main_span']['name'] = $span->name;
                return $span;
            }

        } catch (\Exception $e) {
            $this->addError('main_span', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse a date string into a Carbon instance.
     * 
     * @param string|null $dateStr The date string to parse (YYYY-MM-DD, YYYY-MM, or YYYY)
     * @return \Carbon\Carbon|null The parsed date or null if invalid
     */
    protected function parseDate(?string $dateStr): ?\Carbon\Carbon
    {
        if (!$dateStr) {
            return null;
        }

        try {
            // Split the date string
            $parts = explode('-', $dateStr);
            $year = (int)$parts[0];
            $month = isset($parts[1]) ? (int)$parts[1] : 1;
            $day = isset($parts[2]) ? (int)$parts[2] : 1;

            // Create a Carbon instance
            return \Carbon\Carbon::createFromDate($year, $month, $day);
        } catch (\Exception $e) {
            Log::error('Failed to parse date', [
                'date_str' => $dateStr,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function validateMainSpan(): array
    {
        $errors = [];
        $name = $this->yaml['name'] ?? '[unnamed]';

        // Required fields
        if (empty($this->yaml['name'])) {
            $errors[] = "Name is required for main span";
            return $errors;  // Return early since name is required for other validations
        }

        if (empty($this->yaml['type'])) {
            $errors[] = "Type is required for main span";
        }

        // Start date validation
        if (empty($this->yaml['start'])) {
            $errors[] = "Start date is required for main span '{$name}'";
        } else {
            $startDate = $this->parseDate($this->yaml['start']);
            if (!$startDate) {
                $errors[] = "Invalid start date format for main span '{$name}'. Expected format: YYYY-MM-DD or YYYY-MM";
            }
        }

        // End date validation (if provided)
        if (!empty($this->yaml['end'])) {
            $endDate = $this->parseDate($this->yaml['end']);
            if (!$endDate) {
                $errors[] = "Invalid end date format for main span '{$name}'. Expected format: YYYY-MM-DD or YYYY-MM";
            } else {
                // Compare dates if both are valid
                $startDate = $this->parseDate($this->yaml['start']);
                if ($startDate && $endDate) {
                    $start = mktime(0, 0, 0, $startDate->month, $startDate->day, $startDate->year);
                    $end = mktime(0, 0, 0, $endDate->month, $endDate->day, $endDate->year);
                    if ($end < $start) {
                        $errors[] = "End date cannot be before start date for main span '{$name}'";
                    }
                }
            }
        }

        return $errors;
    }

    protected function validateSubspan(array $data, string $type): array
    {
        $errors = [];

        // Extract name based on type with fallbacks
        $name = match ($type) {
            'education' => $data['institution'] ?? $data['name'] ?? null,
            'work' => $data['employer'] ?? $data['name'] ?? null,
            'residence' => $data['location'] ?? $data['name'] ?? null,
            default => throw new \Exception("Unknown span type: {$type}")
        };

        $fieldName = match ($type) {
            'education' => 'Institution name',
            'work' => 'Employer name',
            'residence' => 'Location name',
            default => 'Name'
        };

        if (empty($name)) {
            $errors[] = "{$fieldName} is required for {$type} span";
            return $errors;
        }

        // Start date validation
        if (empty($data['start'])) {
            $errors[] = "Start date is required for {$type} span '{$name}'";
        } else {
            $startDate = $this->parseDate($data['start']);
            if (!$startDate) {
                $errors[] = "Invalid start date format for {$type} span '{$name}'. Expected format: YYYY-MM-DD or YYYY-MM";
            }
        }

        // End date validation (if provided)
        if (!empty($data['end'])) {
            $endDate = $this->parseDate($data['end']);
            if (!$endDate) {
                $errors[] = "Invalid end date format for {$type} span '{$name}'. Expected format: YYYY-MM-DD or YYYY-MM";
            } else {
                // Compare dates if both are valid
                $startDate = $this->parseDate($data['start']);
                if ($startDate && $endDate) {
                    $start = mktime(0, 0, 0, $startDate->month, $startDate->day, $startDate->year);
                    $end = mktime(0, 0, 0, $endDate->month, $endDate->day, $endDate->year);
                    if ($end < $start) {
                        $errors[] = "End date cannot be before start date for {$type} span '{$name}'";
                    }
                }
            }
        }

        return $errors;
    }

    protected function importEducation(): void
    {
        try {
            if (!is_array($this->yaml['education'])) {
                throw new \Exception("Education data must be an array");
            }

            $this->report['education']['total'] = count($this->yaml['education']);

            foreach ($this->yaml['education'] as $education) {
                $this->createSubspan($education, 'education');
            }
        } catch (\Exception $e) {
            $this->addError('education', $e->getMessage());
            throw $e;
        }
    }

    protected function importWork(): void
    {
        try {
            if (!is_array($this->yaml['work'])) {
                throw new \Exception("Work data must be an array");
            }

            $this->report['work']['total'] = count($this->yaml['work']);

            foreach ($this->yaml['work'] as $work) {
                $this->createSubspan($work, 'work');
            }
        } catch (\Exception $e) {
            $this->addError('work', $e->getMessage());
            throw $e;
        }
    }

    protected function importResidences(): void
    {
        try {
            if (!is_array($this->yaml['residences'])) {
                throw new \Exception("Residences data must be an array");
            }

            $this->report['residences']['total'] = count($this->yaml['residences']);

            foreach ($this->yaml['residences'] as $residence) {
                $this->createSubspan($residence, 'residence');
            }
        } catch (\Exception $e) {
            $this->addError('residences', $e->getMessage());
            throw $e;
        }
    }

    protected function createSubspan(array $data, string $type): void
    {
        try {
            // Determine name based on type
            $name = match($type) {
                'education' => $data['institution'],
                'work' => $data['employer'],
                'residence' => $data['place'],
                default => throw new \Exception("Unknown subspan type: {$type}")
            };

            // Parse dates for the CONNECTION (not necessarily the span's own temporal extent)
            $startDate = $this->parseDate($data['start'] ?? null);
            $endDate = $this->parseDate($data['end'] ?? null);

            // Find existing span by name and type
            $existingSpan = Span::where('name', $name)
                ->where('type_id', in_array($type, ['work', 'education']) ? 'organisation' : $type)
                ->first();

            // Create metadata
            $metadata = array_diff_key($data, array_flip(['start', 'end']));

            // For organizations (work/education), we treat them as placeholders
            // since the connection dates only tell us when the relationship existed,
            // not when the organization itself existed
            $spanState = match($type) {
                'work', 'education' => 'placeholder',
                'residence' => $startDate ? 'complete' : 'placeholder',
                default => 'placeholder'
            };

            // Prepare span data - note we only set temporal data for residences
            // since those dates likely represent when the place actually existed
            $spanData = [
                'name' => $name,
                'type_id' => in_array($type, ['work', 'education']) ? 'organisation' : $type,
                'state' => $spanState,
                'metadata' => $metadata
            ];

            // Only add temporal data for residences
            if ($type === 'residence') {
                $spanData = array_merge($spanData, [
                    'start_year' => $startDate ? $startDate->year : null,
                    'start_month' => $startDate ? $startDate->month : null,
                    'start_day' => $startDate ? $startDate->day : null,
                    'end_year' => $endDate ? $endDate->year : null,
                    'end_month' => $endDate ? $endDate->month : null,
                    'end_day' => $endDate ? $endDate->day : null
                ]);
            }

            if (!$existingSpan) {
                // Create new span using controller
                $existingSpan = $this->createSpanViaController($spanData);
                $this->report[$type]['created']++;
            } else {
                // Update metadata
                $existingSpan->metadata = array_merge($existingSpan->metadata ?? [], $metadata);
                $existingSpan->save();
                $this->report[$type]['existing']++;
            }

            // Prepare dates for connection - these represent when the relationship existed
            $dates = [];
            if ($startDate) {
                $dates['start_year'] = $startDate->year;
                $dates['start_month'] = $startDate->month;
                $dates['start_day'] = $startDate->day;
            }
            if ($endDate) {
                $dates['end_year'] = $endDate->year;
                $dates['end_month'] = $endDate->month;
                $dates['end_day'] = $endDate->day;
            }

            // Create or update connection using ConnectionImporter
            $connection = $this->connectionImporter->createConnection(
                $this->mainSpan,
                $existingSpan,
                $type === 'work' ? 'employment' : $type,
                $dates,
                $metadata
            );

            // Add to report details
            $this->report[$type]['details'][] = [
                'name' => $name,
                'span_action' => $existingSpan->wasRecentlyCreated ? 'created' : 'existing',
                'span_id' => $existingSpan->id,
                'connection_span_id' => $connection->connection_span_id
            ];

        } catch (\Exception $e) {
            $this->addError($type, $e->getMessage(), [
                'data' => $data
            ]);
            throw $e;
        }
    }

    protected function importOtherRelationships(): void
    {
        try {
            if (!is_array($this->yaml['relationships'])) {
                throw new \Exception("Relationships data must be an array");
            }

            $this->report['relationships']['total'] = count($this->yaml['relationships']);

            foreach ($this->yaml['relationships'] as $relationship) {
                $this->createRelationship($relationship);
            }
        } catch (\Exception $e) {
            $this->addError('relationships', $e->getMessage());
            throw $e;
        }
    }

    protected function createRelationship(array $rel): void
    {
        try {
            if (!isset($rel['person']) || !isset($rel['relationshipType'])) {
                throw new \Exception("Relationship must have 'person' and 'relationshipType' fields");
            }

            // Check for existing person
            $person = Span::where('name', $rel['person'])
                ->where('type_id', 'person')
                ->first();

            if (!$person) {
                // Create new person
                $person = Span::create([
                    'name' => $rel['person'],
                    'type_id' => 'person',
                    'state' => isset($rel['start']) ? 'complete' : 'placeholder',
                    'access_level' => 'private',
                    'metadata' => array_diff_key($rel, array_flip(['person', 'relationshipType'])),
                    'owner_id' => $this->user->id,
                    'updater_id' => $this->user->id,
                    'start_year' => isset($rel['start']) ? $this->parseDate($rel['start'])->year : null,
                    'start_month' => isset($rel['start']) ? $this->parseDate($rel['start'])->month : null,
                    'start_day' => isset($rel['start']) ? $this->parseDate($rel['start'])->day : null,
                    'end_year' => isset($rel['end']) ? $this->parseDate($rel['end'])->year : null,
                    'end_month' => isset($rel['end']) ? $this->parseDate($rel['end'])->month : null,
                    'end_day' => isset($rel['end']) ? $this->parseDate($rel['end'])->day : null
                ]);

                $this->report['relationships']['created']++;
            } else {
                $this->report['relationships']['existing']++;
            }

            // Check for existing connection
            $existingConnection = Connection::whereHas('connectionSpan', function($query) use ($rel) {
                $query->where('type_id', 'connection')
                    ->whereJsonContains('metadata->relationship_type', $rel['relationshipType']);
            })->where(function($query) use ($person) {
                $query->where(function($q) use ($person) {
                    $q->where('parent_id', $this->mainSpan->id)
                        ->where('child_id', $person->id);
                })->orWhere(function($q) use ($person) {
                    $q->where('parent_id', $person->id)
                        ->where('child_id', $this->mainSpan->id);
                });
            })->first();

            if (!$existingConnection) {
                // Create connection span
                $connectionSpan = Span::create([
                    'name' => "{$rel['relationshipType']} relationship between {$this->mainSpan->name} and {$person->name}",
                    'type_id' => 'connection',
                    'state' => 'complete',
                    'access_level' => 'private',
                    'metadata' => [
                        'relationship_type' => $rel['relationshipType']
                    ],
                    'owner_id' => $this->user->id,
                    'updater_id' => $this->user->id,
                    'start_year' => isset($rel['start']) ? $this->parseDate($rel['start'])->year : null,
                    'start_month' => isset($rel['start']) ? $this->parseDate($rel['start'])->month : null,
                    'start_day' => isset($rel['start']) ? $this->parseDate($rel['start'])->day : null,
                    'end_year' => isset($rel['end']) ? $this->parseDate($rel['end'])->year : null,
                    'end_month' => isset($rel['end']) ? $this->parseDate($rel['end'])->month : null,
                    'end_day' => isset($rel['end']) ? $this->parseDate($rel['end'])->day : null
                ]);

                // Create the connection
                Connection::create([
                    'parent_id' => $this->mainSpan->id,
                    'child_id' => $person->id,
                    'type_id' => 'relationship',
                    'connection_span_id' => $connectionSpan->id
                ]);

                $this->report['relationships']['connections_created']++;
            } else {
                $this->report['relationships']['connections_existing']++;
            }

            // Add to report details
            $this->report['relationships']['details'][] = [
                'person' => $rel['person'],
                'type' => $rel['relationshipType'],
                'person_action' => $person->wasRecentlyCreated ? 'created' : 'existing',
                'connection_action' => $existingConnection ? 'existing' : 'created',
                'person_span_id' => $person->id,
                'connection_span_id' => $existingConnection ? $existingConnection->connection_span_id : null
            ];

        } catch (\Exception $e) {
            $this->addError('relationship', $e->getMessage(), [
                'data' => $rel
            ]);
            throw $e;
        }
    }

    protected function importRelationships(): void
    {
        // Import education relationships if present
        if (!empty($this->yaml['education'])) {
            $this->importEducation();
        }

        // Import work relationships if present
        if (!empty($this->yaml['work'])) {
            $this->importWork();
        }

        // Import residence relationships if present
        if (!empty($this->yaml['residences'])) {
            $this->importResidences();
        }

        // Import other relationships if present
        if (!empty($this->yaml['relationships'])) {
            $this->importOtherRelationships();
        }
    }

    protected function addError(string $type, string $message, ?array $context = null): void
    {
        $error = [
            'type' => $type,
            'message' => $message
        ];

        if ($context) {
            $error['context'] = $context;
        }

        $this->report['errors'][] = $error;
        $this->report['success'] = false;

        Log::error("Import error: {$message}", [
            'type' => $type,
            'context' => $context
        ]);
    }

    protected function addWarning(string $message): void
    {
        $this->report['warnings'][] = $message;

        Log::warning($message);
    }
} 
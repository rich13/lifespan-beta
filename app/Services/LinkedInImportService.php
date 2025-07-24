<?php

namespace App\Services;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Services\Import\Connections\ConnectionImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

class LinkedInImportService
{
    protected ?ConnectionImporter $connectionImporter = null;

    public function __construct()
    {
        // ConnectionImporter will be created when needed with the specific user
    }
    
    protected function getConnectionImporter(User $user): ConnectionImporter
    {
        if (!$this->connectionImporter) {
            $this->connectionImporter = new ConnectionImporter($user);
        }
        return $this->connectionImporter;
    }

    /**
     * Preview CSV data without importing
     */
    public function previewCsv(UploadedFile $file, User $user): array
    {
        $csvData = $this->parseCsv($file);
        
        // Analyze what would be created vs connected to existing
        $preview = [
            'total_rows' => count($csvData),
            'preview_rows' => array_slice($csvData, 0, 5),
            'headers' => array_keys($csvData[0] ?? []),
            'sample_data' => $this->analyzeCsvData($csvData),
            'import_preview' => $this->generateImportPreview($csvData, $user)
        ];
        
        return $preview;
    }

    /**
     * Generate detailed import preview
     */
    protected function generateImportPreview(array $csvData, User $user): array
    {
        $preview = [
            'person' => [
                'name' => $user->name,
                'action' => 'unknown',
                'exists' => false
            ],
            'positions' => [
                'total' => count($csvData),
                'valid' => 0,
                'invalid' => 0,
                'details' => []
            ],
            'organisations' => [
                'will_create' => [],
                'will_connect' => [],
                'total_new' => 0,
                'total_existing' => 0
            ],
            'roles' => [
                'will_create' => [],
                'will_connect' => [],
                'total_new' => 0,
                'total_existing' => 0
            ]
        ];

        // Find the user's personal span
        $personSpan = $this->findUserPersonalSpan($user);
        if ($personSpan) {
            $preview['person']['exists'] = true;
            $preview['person']['action'] = 'connect';
        } else {
            $preview['person']['exists'] = false;
            $preview['person']['action'] = 'error';
        }

        // Analyze each position
        foreach ($csvData as $index => $row) {
            $companyName = trim($row['Company Name'] ?? '');
            $title = trim($row['Title'] ?? '');
            $description = trim($row['Description'] ?? '');
            $location = trim($row['Location'] ?? '');
            $startedOn = trim($row['Started On'] ?? '');
            $finishedOn = trim($row['Finished On'] ?? '');
            
            $positionPreview = [
                'row' => $index + 1,
                'company' => $companyName,
                'title' => $title,
                'start_date' => $startedOn,
                'end_date' => $finishedOn,
                'location' => $location,
                'description' => $description,
                'valid' => false,
                'errors' => []
            ];
            
            // Validate required fields
            if (empty($companyName)) {
                $positionPreview['errors'][] = 'Company name is required';
            }
            if (empty($title)) {
                $positionPreview['errors'][] = 'Title is required';
            }
            
            if (empty($positionPreview['errors'])) {
                $positionPreview['valid'] = true;
                $preview['positions']['valid']++;
                
                // Check organisation
                $existingOrganisation = Span::where('name', 'ILIKE', $companyName)
                    ->where('type_id', 'organisation')
                    ->where('owner_id', $user->id)
                    ->first();
                    
                if ($existingOrganisation) {
                    $preview['organisations']['will_connect'][] = $companyName;
                    $preview['organisations']['total_existing']++;
                    $positionPreview['organisation_action'] = 'connect';
                } else {
                    if (!in_array($companyName, $preview['organisations']['will_create'])) {
                        $preview['organisations']['will_create'][] = $companyName;
                        $preview['organisations']['total_new']++;
                    }
                    $positionPreview['organisation_action'] = 'create';
                }
                
                // Check role
                $existingRole = Span::where('name', 'ILIKE', $title)
                    ->where('type_id', 'role')
                    ->where('owner_id', $user->id)
                    ->first();
                    
                if ($existingRole) {
                    $preview['roles']['will_connect'][] = $title;
                    $preview['roles']['total_existing']++;
                    $positionPreview['role_action'] = 'connect';
                } else {
                    if (!in_array($title, $preview['roles']['will_create'])) {
                        $preview['roles']['will_create'][] = $title;
                        $preview['roles']['total_new']++;
                    }
                    $positionPreview['role_action'] = 'create';
                }
                
            } else {
                $preview['positions']['invalid']++;
            }
            
            $preview['positions']['details'][] = $positionPreview;
        }
        
        return $preview;
    }

    /**
     * Import LinkedIn CSV data
     */
    public function importCsv(UploadedFile $file, User $user, bool $updateExisting = false): array
    {
        $csvData = $this->parseCsv($file);
        
        // Find the user's personal span
        $personSpan = $this->findUserPersonalSpan($user);
        
        if (!$personSpan) {
            return [
                'success' => false,
                'error' => 'Could not find your personal span. Please create your personal profile first.',
                'positions' => [
                    'processed' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'errors' => count($csvData)
                ]
            ];
        }
        
        $result = [
            'person_span' => [
                'id' => $personSpan->id,
                'name' => $personSpan->name,
                'action' => $personSpan->wasRecentlyCreated ? 'created' : 'existing'
            ],
            'positions' => [
                'total' => count($csvData),
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'details' => []
            ],
            'organisations' => [
                'created' => 0,
                'existing' => 0,
                'total' => 0
            ],
            'roles' => [
                'created' => 0,
                'existing' => 0,
                'total' => 0
            ]
        ];

        DB::beginTransaction();
        
        try {
            foreach ($csvData as $index => $row) {
                $positionResult = $this->processPosition($user, $personSpan, $row, $updateExisting);
                
                $result['positions']['processed']++;
                $result['positions']['details'][] = $positionResult;
                
                if ($positionResult['success']) {
                    if ($positionResult['action'] === 'created') {
                        $result['positions']['created']++;
                    } else {
                        $result['positions']['updated']++;
                    }
                    
                    // Count organisations and roles
                    if ($positionResult['organisation_action'] === 'created') {
                        $result['organisations']['created']++;
                    } else {
                        $result['organisations']['existing']++;
                    }
                    
                    if ($positionResult['role_action'] === 'created') {
                        $result['roles']['created']++;
                    } else {
                        $result['roles']['existing']++;
                    }
                } else {
                    $result['positions']['errors']++;
                }
            }
            
            $result['organisations']['total'] = $result['organisations']['created'] + $result['organisations']['existing'];
            $result['roles']['total'] = $result['roles']['created'] + $result['roles']['existing'];
            
            DB::commit();
            
            // Log the import
            $this->logImport($user, $result);
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('LinkedIn import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Process a single position row
     */
    protected function processPosition(User $user, Span $personSpan, array $row, bool $updateExisting): array
    {
        try {
            $companyName = trim($row['Company Name'] ?? '');
            $title = trim($row['Title'] ?? '');
            $description = trim($row['Description'] ?? '');
            $location = trim($row['Location'] ?? '');
            $startedOn = trim($row['Started On'] ?? '');
            $finishedOn = trim($row['Finished On'] ?? '');
            
            if (empty($companyName) || empty($title)) {
                return [
                    'success' => false,
                    'error' => 'Company name and title are required',
                    'row' => $row
                ];
            }
            
            // Parse dates
            $dates = $this->getConnectionImporter($user)->parseDatesFromStrings($startedOn, $finishedOn);
            
            // Find or create organisation span
            $organisationSpan = $this->getConnectionImporter($user)->findOrCreateConnectedSpan(
                $companyName,
                'organisation',
                null, // Don't set dates on organisation span
                ['location' => $location]
            );
            
            // Find or create role span
            $roleMetadata = array_filter([
                'description' => $description,
                'location' => $location
            ]);
            
            $roleSpan = $this->getConnectionImporter($user)->findOrCreateConnectedSpan(
                $title,
                'role',
                null, // Don't set dates on role span
                $roleMetadata
            );
            
            // Create notes from description and location
            $notes = [];
            if (!empty($description)) {
                $notes[] = $description;
            }
            if (!empty($location)) {
                $notes[] = "Location: $location";
            }
            $connectionNotes = !empty($notes) ? implode("\n\n", $notes) : null;
            
            // Create "has_role" connection between person and role
            $hasRoleConnection = $this->getConnectionImporter($user)->createConnection(
                $personSpan,
                $roleSpan,
                'has_role',
                $dates,
                ['description' => $description]
            );
            
            // Update the connection span with notes
            if ($connectionNotes) {
                $hasRoleConnection->connectionSpan->update([
                    'notes' => $connectionNotes,
                    'updater_id' => $user->id
                ]);
            }
            
            // Create "at_organisation" connection between the role connection and organisation
            $atConnection = $this->getConnectionImporter($user)->createConnection(
                $hasRoleConnection->connectionSpan,
                $organisationSpan,
                'at_organisation',
                $dates, // Same dates as the role connection
                ['location' => $location]
            );
            
            return [
                'success' => true,
                'action' => $hasRoleConnection->wasRecentlyCreated ? 'created' : 'updated',
                'company' => $companyName,
                'title' => $title,
                'organisation_action' => $organisationSpan->wasRecentlyCreated ? 'created' : 'existing',
                'role_action' => $roleSpan->wasRecentlyCreated ? 'created' : 'existing',
                'start_date' => $startedOn,
                'end_date' => $finishedOn,
                'has_role_connection_id' => $hasRoleConnection->id,
                'at_connection_id' => $atConnection->id
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to process LinkedIn position', [
                'error' => $e->getMessage(),
                'row' => $row
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'row' => $row
            ];
        }
    }

    /**
     * Find user's personal span
     */
    protected function findUserPersonalSpan(User $user): ?Span
    {
        return Span::where('name', 'ILIKE', $user->name)
            ->where('type_id', 'person')
            ->where('owner_id', $user->id)
            ->first();
    }

    /**
     * Parse CSV file
     */
    protected function parseCsv(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        $lines = explode("\n", trim($content));
        
        if (empty($lines)) {
            throw new \Exception('CSV file is empty');
        }
        
        // Parse headers
        $headers = str_getcsv(array_shift($lines));
        
        // Clean up headers
        $headers = array_map('trim', $headers);
        
        // Validate required headers
        $requiredHeaders = ['Company Name', 'Title'];
        foreach ($requiredHeaders as $required) {
            if (!in_array($required, $headers)) {
                throw new \Exception("Required header '{$required}' not found in CSV");
            }
        }
        
        // Parse data rows
        $data = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            $row = str_getcsv($line);
            
            // Ensure we have the same number of columns as headers
            while (count($row) < count($headers)) {
                $row[] = '';
            }
            
            // Truncate if we have more columns than headers
            $row = array_slice($row, 0, count($headers));
            
            $data[] = array_combine($headers, $row);
        }
        
        return $data;
    }

    /**
     * Analyze CSV data for preview
     */
    protected function analyzeCsvData(array $csvData): array
    {
        $analysis = [
            'total_positions' => count($csvData),
            'companies' => [],
            'date_ranges' => [],
            'locations' => []
        ];
        
        foreach ($csvData as $row) {
            // Count companies
            $company = trim($row['Company Name'] ?? '');
            if (!empty($company)) {
                $analysis['companies'][$company] = ($analysis['companies'][$company] ?? 0) + 1;
            }
            
            // Count locations
            $location = trim($row['Location'] ?? '');
            if (!empty($location)) {
                $analysis['locations'][$location] = ($analysis['locations'][$location] ?? 0) + 1;
            }
            
            // Analyze date ranges
            $startDate = trim($row['Started On'] ?? '');
            $endDate = trim($row['Finished On'] ?? '');
            if (!empty($startDate)) {
                $analysis['date_ranges'][] = [
                    'start' => $startDate,
                    'end' => $endDate,
                    'ongoing' => empty($endDate)
                ];
            }
        }
        
        // Sort by frequency
        arsort($analysis['companies']);
        arsort($analysis['locations']);
        
        return $analysis;
    }



    /**
     * Log import activity
     */
    protected function logImport(User $user, array $result): void
    {
        Log::info('LinkedIn import completed', [
            'user_id' => $user->id,
            'person_name' => $result['person_span']['name'],
            'positions_processed' => $result['positions']['processed'],
            'positions_created' => $result['positions']['created'],
            'organisations_created' => $result['organisations']['created'],
            'roles_created' => $result['roles']['created']
        ]);
    }
} 
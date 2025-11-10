<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NewSpanController extends Controller
{
    /**
     * Show a standalone view that renders the existing Create New Span modal.
     */
    public function showSpan(): \Illuminate\Contracts\View\View
    {
        return view('new.span');
    }

    /**
     * Show the combined person-role-organisation creation form.
     */
    public function showPersonRoleOrganisation(): \Illuminate\Contracts\View\View
    {
        return view('new.person-role-org');
    }

    /**
     * Handle submission of the combined form that creates the person/role/organisation structure.
     */
    public function storePersonRoleOrganisation(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'person_id' => ['nullable', 'uuid', 'exists:spans,id'],
            'person_name' => ['required_without:person_id', 'nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'uuid', 'exists:spans,id'],
            'role_name' => ['required_without:role_id', 'nullable', 'string', 'max:255'],
            'organisation_id' => ['nullable', 'uuid', 'exists:spans,id'],
            'organisation_name' => ['required_without:organisation_id', 'nullable', 'string', 'max:255'],
            'start_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'start_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'end_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'end_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'end_day' => ['nullable', 'integer', 'min:1', 'max:31'],
        ]);

        $this->validateDateDependencies($validated, 'start');
        $this->validateDateDependencies($validated, 'end');

        $userId = Auth::id();

        try {
            $connectionSpan = DB::transaction(function () use ($validated, $userId) {
                $person = $this->resolveSpan(
                    $validated['person_id'] ?? null,
                    $validated['person_name'] ?? null,
                    'person',
                    $userId,
                    'person_name'
                );

                $role = $this->resolveSpan(
                    $validated['role_id'] ?? null,
                    $validated['role_name'] ?? null,
                    'role',
                    $userId,
                    'role_name'
                );

                $organisation = $this->resolveSpan(
                    $validated['organisation_id'] ?? null,
                    $validated['organisation_name'] ?? null,
                    'organisation',
                    $userId,
                    'organisation_name'
                );

                // Prevent duplicate has_role connections
                $existingConnection = Connection::where('parent_id', $person->id)
                    ->where('child_id', $role->id)
                    ->where('type_id', 'has_role')
                    ->first();

                if ($existingConnection) {
                    throw ValidationException::withMessages([
                        'role_name' => ['A has role connection between these spans already exists.'],
                    ]);
                }

                $connectionSpan = $this->createConnectionSpan(
                    sprintf('%s has role %s', $person->name, $role->name),
                    $validated,
                    $userId,
                    true
                );

                $hasRoleConnection = Connection::create([
                    'parent_id' => $person->id,
                    'child_id' => $role->id,
                    'type_id' => 'has_role',
                    'connection_span_id' => $connectionSpan->id,
                ]);

                $atOrganisationSpan = $this->createConnectionSpan(
                    sprintf('%s has role %s at %s', $person->name, $role->name, $organisation->name),
                    $validated,
                    $userId,
                    false
                );

                Connection::create([
                    'parent_id' => $connectionSpan->id,
                    'child_id' => $organisation->id,
                    'type_id' => 'at_organisation',
                    'connection_span_id' => $atOrganisationSpan->id,
                ]);

                return $connectionSpan;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'general' => 'Something went wrong while creating the connection. Please try again.',
                ]);
        }

        return redirect()
            ->route('spans.show', $connectionSpan->slug)
            ->with('status', 'Connection created successfully.');
    }

    /**
     * Ensure day/month aren't provided without a year.
     */
    private function validateDateDependencies(array $data, string $prefix): void
    {
        $yearKey = "{$prefix}_year";
        $monthKey = "{$prefix}_month";
        $dayKey = "{$prefix}_day";

        if (empty($data[$yearKey])) {
            if (!empty($data[$monthKey]) || !empty($data[$dayKey])) {
                throw ValidationException::withMessages([
                    $yearKey => ['Year is required when providing month or day.'],
                ]);
            }
        } else {
            if (!empty($data[$dayKey]) && empty($data[$monthKey])) {
                throw ValidationException::withMessages([
                    $monthKey => ['Month is required when providing a day.'],
                ]);
            }
        }
    }

    /**
     * Find an existing span by name/type, otherwise create a placeholder.
     */
    private function resolveSpan(?string $spanId, ?string $name, string $typeId, string $userId, string $fieldName): Span
    {
        if ($spanId) {
            $span = Span::find($spanId);
            if (!$span) {
                throw ValidationException::withMessages([
                    $fieldName => ['Selected span could not be found.'],
                ]);
            }

            if ($span->type_id !== $typeId) {
                throw ValidationException::withMessages([
                    $fieldName => ['Selected span is not of the expected type.'],
                ]);
            }

            return $span;
        }

        if (!$name) {
            throw ValidationException::withMessages([
                $fieldName => ['Name is required.'],
            ]);
        }

        return $this->firstOrCreateSpan($name, $typeId, $userId);
    }

    private function firstOrCreateSpan(string $name, string $typeId, string $userId): Span
    {
        $existing = Span::where('name', $name)
            ->where('type_id', $typeId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Span::create([
            'name' => $name,
            'type_id' => $typeId,
            'owner_id' => $userId,
            'updater_id' => $userId,
            'state' => 'placeholder',
            'access_level' => 'private',
            'metadata' => [],
        ]);
    }

    /**
     * Create a connection span using shared date fields.
     */
    private function createConnectionSpan(string $name, array $validated, string $userId, bool $withDates = true): Span
    {
        $spanData = [
            'name' => $name,
            'type_id' => 'connection',
            'owner_id' => $userId,
            'updater_id' => $userId,
            'state' => 'placeholder',
            'access_level' => 'public',
            'metadata' => [],
        ];

        if ($withDates) {
            foreach (['start_year', 'start_month', 'start_day', 'end_year', 'end_month', 'end_day'] as $field) {
                if (!empty($validated[$field])) {
                    $spanData[$field] = (int) $validated[$field];
                }
            }
        }

        return Span::create($spanData);
    }

    /**
     * Preview bulk CSV upload - returns what would happen for each row.
     */
    public function previewBulkPersonRoleOrganisation(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'bulk_role_id' => ['nullable', 'uuid', 'exists:spans,id'],
            'bulk_role_name' => ['required_without:bulk_role_id', 'nullable', 'string', 'max:255'],
            'bulk_organisation_id' => ['nullable', 'uuid', 'exists:spans,id'],
            'bulk_organisation_name' => ['required_without:bulk_organisation_id', 'nullable', 'string', 'max:255'],
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            // Resolve role and organisation
            $role = $this->resolveSpan(
                $validated['bulk_role_id'] ?? null,
                $validated['bulk_role_name'] ?? null,
                'role',
                Auth::id(),
                'bulk_role_name'
            );

            $organisation = $this->resolveSpan(
                $validated['bulk_organisation_id'] ?? null,
                $validated['bulk_organisation_name'] ?? null,
                'organisation',
                Auth::id(),
                'bulk_organisation_name'
            );

            // Parse CSV file
            $file = $request->file('csv_file');
            $csvData = $this->parseCsvFile($file);
            
            // Detect duplicate names in CSV
            $nameCounts = [];
            foreach ($csvData as $row) {
                $name = trim($row['name']);
                if (!empty($name)) {
                    $nameCounts[$name] = ($nameCounts[$name] ?? 0) + 1;
                }
            }
            
            $preview = [];
            $processedNames = []; // Track which names we've seen to determine if this is first or later occurrence

            // Preview each row
            foreach ($csvData as $rowIndex => $row) {
                $personName = trim($row['name']);
                $isDuplicate = ($nameCounts[$personName] ?? 0) > 1;
                $isFirstOccurrence = !isset($processedNames[$personName]);
                $processedNames[$personName] = true;
                
                $previewRow = [
                    'name' => $personName,
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'valid' => true,
                    'error' => null,
                    'action' => 'create',
                    'person_exists' => false,
                    'connection_exists' => false,
                    'is_duplicate' => $isDuplicate,
                    'is_first_occurrence' => $isFirstOccurrence,
                    'duplicate_count' => $isDuplicate ? $nameCounts[$personName] : 0,
                ];

                try {
                    // Check if person exists
                    $existingPerson = Span::where('name', $personName)
                        ->where('type_id', 'person')
                        ->first();

                    if ($existingPerson) {
                        $previewRow['person_exists'] = true;
                        $previewRow['person_id'] = $existingPerson->id;
                    }

                    // Parse dates
                    $startDate = $this->parseDateString($row['start_date']);
                    
                    if (!$startDate || !isset($startDate['year'])) {
                        throw new \InvalidArgumentException('Start date is required and must be in format YYYY, YYYY-MM, or YYYY-MM-DD.');
                    }

                    if (!empty($row['end_date'])) {
                        $endDate = $this->parseDateString($row['end_date']);
                        if (!$endDate) {
                            throw new \InvalidArgumentException('Invalid end date format.');
                        }
                    }

                    // Check if connection already exists in database for this exact period
                    $connectionExists = false;
                    if ($existingPerson) {
                        $connectionExists = $this->connectionExistsForPeriod($existingPerson, $role, $startDate, $endDate);
                    }

                    if ($connectionExists) {
                        // Connection already exists - will be skipped
                        $previewRow['action'] = 'skip';
                        $previewRow['action_description'] = 'Skip - connection already exists for this time period';
                        $previewRow['connection_exists'] = true;
                    } else {
                        // Check for overlapping date ranges with other CSV rows for the same person
                        $hasOverlap = false;
                        if ($isDuplicate && !$isFirstOccurrence) {
                            // Check if this row's dates overlap with earlier occurrences in the CSV
                            foreach ($preview as $prevRow) {
                                if ($prevRow['name'] === $personName && $prevRow['valid'] && !empty($prevRow['start_date'])) {
                                    $prevStart = $this->parseDateString($prevRow['start_date']);
                                    $prevEnd = !empty($prevRow['end_date']) ? $this->parseDateString($prevRow['end_date']) : null;
                                    
                                    if ($prevStart && $startDate) {
                                        $prevStartYear = $prevStart['year'];
                                        $prevEndYear = $prevEnd ? $prevEnd['year'] : null;
                                        $thisStartYear = $startDate['year'];
                                        $thisEndYear = $endDate ? $endDate['year'] : null;
                                        
                                        // Check for overlap (simplified - just checking years)
                                        if (!$thisEndYear || !$prevEndYear) {
                                            // One or both are ongoing - assume overlap
                                            $hasOverlap = true;
                                        } elseif (($thisStartYear <= $prevEndYear && $thisEndYear >= $prevStartYear)) {
                                            $hasOverlap = true;
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Create a new connection - person can have same role multiple times
                        $previewRow['action'] = 'create';
                        if ($existingPerson) {
                            if ($isDuplicate) {
                                if ($hasOverlap) {
                                    $previewRow['action_description'] = '⚠️ Create separate connection (duplicate name - dates may overlap with earlier row)';
                                } else {
                                    $previewRow['action_description'] = 'Create separate connection (person exists, different period)';
                                }
                            } else {
                                $previewRow['action_description'] = 'Create new connection (person exists)';
                            }
                        } else {
                            if ($isDuplicate) {
                                if ($hasOverlap) {
                                    $previewRow['action_description'] = '⚠️ Create person and separate connection (duplicate name - dates may overlap)';
                                } else {
                                    $previewRow['action_description'] = 'Create person and separate connection (duplicate name, different period)';
                                }
                            } else {
                                $previewRow['action_description'] = 'Create new person and connection';
                            }
                        }
                    }

                } catch (\Exception $e) {
                    $previewRow['valid'] = false;
                    $previewRow['error'] = $e->getMessage();
                    $previewRow['action_description'] = 'Error: ' . $e->getMessage();
                }

                $preview[] = $previewRow;
            }

            // Count duplicates
            $duplicateNames = array_filter($nameCounts, function($count) {
                return $count > 1;
            });
            $hasDuplicates = !empty($duplicateNames);
            $duplicateWarning = $hasDuplicates 
                ? 'Note: Duplicate names found in CSV. Each occurrence will create a separate connection (person can hold the same role multiple times at different periods).'
                : null;

            return response()->json([
                'preview' => $preview,
                'role' => $role->name,
                'organisation' => $organisation->name,
                'has_duplicates' => $hasDuplicates,
                'duplicate_warning' => $duplicateWarning,
                'duplicate_names' => array_keys($duplicateNames),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process a single row from bulk CSV upload.
     */
    public function storeBulkPersonRoleOrganisationRow(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'bulk_role_id' => ['nullable', 'uuid', 'exists:spans,id'],
            'bulk_role_name' => ['required_without:bulk_role_id', 'nullable', 'string', 'max:255'],
            'bulk_organisation_id' => ['nullable', 'uuid', 'exists:spans,id'],
            'bulk_organisation_name' => ['required_without:bulk_organisation_id', 'nullable', 'string', 'max:255'],
            'person_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'string'],
            'end_date' => ['nullable', 'string'],
        ]);

        $userId = Auth::id();

        try {
            // Resolve role and organisation
            $role = $this->resolveSpan(
                $validated['bulk_role_id'] ?? null,
                $validated['bulk_role_name'] ?? null,
                'role',
                $userId,
                'bulk_role_name'
            );

            $organisation = $this->resolveSpan(
                $validated['bulk_organisation_id'] ?? null,
                $validated['bulk_organisation_name'] ?? null,
                'organisation',
                $userId,
                'bulk_organisation_name'
            );

            // Process the row
            $row = [
                'name' => $validated['person_name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? '',
            ];

            $result = $this->processBulkRow($row, $role, $organisation, $userId, 1);

            if ($result['status'] === 'error') {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'status' => $result['status'],
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle bulk CSV upload for person-role-organisation creation.
     */
    public function storeBulkPersonRoleOrganisation(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'bulk_role_id' => ['nullable', 'uuid', 'exists:spans,id'],
            'bulk_role_name' => ['required_without:bulk_role_id', 'nullable', 'string', 'max:255'],
            'bulk_organisation_id' => ['nullable', 'uuid', 'exists:spans,id'],
            'bulk_organisation_name' => ['required_without:bulk_organisation_id', 'nullable', 'string', 'max:255'],
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10MB max
        ]);

        $userId = Auth::id();

        try {
            // Resolve role and organisation
            $role = $this->resolveSpan(
                $validated['bulk_role_id'] ?? null,
                $validated['bulk_role_name'] ?? null,
                'role',
                $userId,
                'bulk_role_name'
            );

            $organisation = $this->resolveSpan(
                $validated['bulk_organisation_id'] ?? null,
                $validated['bulk_organisation_name'] ?? null,
                'organisation',
                $userId,
                'bulk_organisation_name'
            );

            // Parse CSV file
            $file = $request->file('csv_file');
            $csvData = $this->parseCsvFile($file);
            
            $results = [
                'created' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => [],
            ];

            // Process each row and collect results
            $rowResults = [];

            foreach ($csvData as $rowIndex => $row) {
                $result = $this->processBulkRow($row, $role, $organisation, $userId, $rowIndex + 1);
                
                if ($result['status'] === 'created') {
                    $results['created']++;
                    $rowResults[] = [
                        'index' => $rowIndex,
                        'success' => true,
                        'status' => 'created',
                        'message' => $result['message'],
                    ];
                } elseif ($result['status'] === 'skipped') {
                    $results['skipped']++;
                    $rowResults[] = [
                        'index' => $rowIndex,
                        'success' => true,
                        'status' => 'skipped',
                        'message' => $result['message'],
                    ];
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Row " . ($rowIndex + 1) . ": " . $result['message'];
                    $rowResults[] = [
                        'index' => $rowIndex,
                        'success' => false,
                        'status' => 'error',
                        'message' => $result['message'],
                    ];
                }
            }

            // Build summary message
            $summaryParts = [];
            if ($results['created'] > 0) {
                $summaryParts[] = "{$results['created']} connection(s) created.";
            }
            if ($results['skipped'] > 0) {
                $summaryParts[] = "{$results['skipped']} row(s) skipped (already exist).";
            }
            if ($results['failed'] > 0) {
                $summaryParts[] = "{$results['failed']} row(s) failed.";
            }
            $summary = implode(' ', $summaryParts);

            return response()->json([
                'success' => true,
                'results' => $rowResults,
                'summary' => $summary,
                'total' => count($csvData),
                'created_count' => $results['created'],
                'skipped_count' => $results['skipped'],
                'failed_count' => $results['failed'],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->errors()),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while processing the CSV file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse CSV file and return array of rows.
     */
    private function parseCsvFile($file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        
        if ($handle === false) {
            throw new \RuntimeException('Could not open CSV file.');
        }

        while (($data = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }

            // Expect 3 columns: Name, Start Date, End Date
            if (count($data) < 3) {
                throw new \InvalidArgumentException('CSV must have at least 3 columns: Name, Start Date, End Date.');
            }

            $rows[] = [
                'name' => trim($data[0]),
                'start_date' => trim($data[1] ?? ''),
                'end_date' => trim($data[2] ?? ''),
            ];
        }

        fclose($handle);

        if (empty($rows)) {
            throw new \InvalidArgumentException('CSV file is empty or contains no valid rows.');
        }

        return $rows;
    }

    /**
     * Process a single row from the bulk CSV.
     * Returns an array with 'status' (created|skipped|error) and optional 'message'.
     */
    private function processBulkRow(array $row, Span $role, Span $organisation, string $userId, int $rowNumber): array
    {
        if (empty($row['name'])) {
            return [
                'status' => 'error',
                'message' => 'Name is required.',
            ];
        }

        try {
            // Find or create person
            $person = $this->firstOrCreateSpan($row['name'], 'person', $userId);

            // Parse dates
            $startDate = $this->parseDateString($row['start_date']);
            $endDate = !empty($row['end_date']) ? $this->parseDateString($row['end_date']) : null;

            if (!$startDate || !isset($startDate['year'])) {
                return [
                    'status' => 'error',
                    'message' => 'Start date is required and must be in format YYYY, YYYY-MM, or YYYY-MM-DD.',
                ];
            }

            // Check if connection already exists for this exact period
            if ($this->connectionExistsForPeriod($person, $role, $startDate, $endDate)) {
                return [
                    'status' => 'skipped',
                    'message' => 'Connection already exists for this time period.',
                ];
            }

            // Create new connection
            DB::transaction(function () use ($person, $role, $organisation, $startDate, $endDate, $userId) {
                $connectionSpan = $this->createConnectionSpan(
                    sprintf('%s has role %s', $person->name, $role->name),
                    [
                        'start_year' => $startDate['year'],
                        'start_month' => $startDate['month'] ?? null,
                        'start_day' => $startDate['day'] ?? null,
                        'end_year' => $endDate['year'] ?? null,
                        'end_month' => $endDate['month'] ?? null,
                        'end_day' => $endDate['day'] ?? null,
                    ],
                    $userId,
                    true
                );

                $hasRoleConnection = Connection::create([
                    'parent_id' => $person->id,
                    'child_id' => $role->id,
                    'type_id' => 'has_role',
                    'connection_span_id' => $connectionSpan->id,
                ]);

                // Check if at_organisation connection already exists
                $existingAtOrg = Connection::where('parent_id', $connectionSpan->id)
                    ->where('child_id', $organisation->id)
                    ->where('type_id', 'at_organisation')
                    ->first();

                if (!$existingAtOrg) {
                    $atOrganisationSpan = $this->createConnectionSpan(
                        sprintf('%s has role %s at %s', $person->name, $role->name, $organisation->name),
                        [],
                        $userId,
                        false
                    );

                    Connection::create([
                        'parent_id' => $connectionSpan->id,
                        'child_id' => $organisation->id,
                        'type_id' => 'at_organisation',
                        'connection_span_id' => $atOrganisationSpan->id,
                    ]);
                }
            });

            return [
                'status' => 'created',
                'message' => 'Connection created successfully.',
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse a date string (YYYY, YYYY-MM, or YYYY-MM-DD).
     */
    private function parseDateString(?string $dateString): ?array
    {
        if (empty($dateString)) {
            return null;
        }

        $dateString = trim($dateString);

        // Try year-only format first (YYYY)
        if (preg_match('/^\d{4}$/', $dateString)) {
            return ['year' => (int) $dateString, 'month' => null, 'day' => null];
        }

        // Try ISO format (YYYY-MM-DD, YYYY-MM)
        if (preg_match('/^\d{4}(-\d{1,2}(-\d{1,2})?)?$/', $dateString)) {
            $parts = explode('-', $dateString);
            $result = ['year' => (int) $parts[0]];
            if (isset($parts[1])) {
                $result['month'] = (int) $parts[1];
            }
            if (isset($parts[2])) {
                $result['day'] = (int) $parts[2];
            }
            return $result;
        }

        throw new \InvalidArgumentException("Invalid date format: {$dateString}. Expected YYYY, YYYY-MM, or YYYY-MM-DD");
    }

    /**
     * Check if a connection already exists for the same person, role, and date range.
     */
    private function connectionExistsForPeriod(Span $person, Span $role, array $startDate, ?array $endDate): bool
    {
        // Find all has_role connections between this person and role
        $connections = Connection::where('parent_id', $person->id)
            ->where('child_id', $role->id)
            ->where('type_id', 'has_role')
            ->with('connectionSpan')
            ->get();

        foreach ($connections as $connection) {
            if (!$connection->connectionSpan) {
                continue;
            }

            $span = $connection->connectionSpan;

            // Check if dates match
            $startYearMatch = ($span->start_year === $startDate['year']);
            $startMonthMatch = ($span->start_month ?? null) === ($startDate['month'] ?? null);
            $startDayMatch = ($span->start_day ?? null) === ($startDate['day'] ?? null);

            $endYearMatch = ($span->end_year ?? null) === ($endDate['year'] ?? null);
            $endMonthMatch = ($span->end_month ?? null) === ($endDate['month'] ?? null);
            $endDayMatch = ($span->end_day ?? null) === ($endDate['day'] ?? null);

            if ($startYearMatch && $startMonthMatch && $startDayMatch && 
                $endYearMatch && $endMonthMatch && $endDayMatch) {
                return true;
            }
        }

        return false;
    }
}


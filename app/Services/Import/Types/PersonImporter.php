<?php

namespace App\Services\Import\Types;

use App\Models\Span;
use App\Services\Import\Connections\ConnectionImporter;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PersonImporter extends BaseSpanImporter
{
    protected ConnectionImporter $connectionImporter;

    public function __construct(User $user)
    {
        parent::__construct($user);
        $this->connectionImporter = new ConnectionImporter($user);
    }

    protected function getSpanType(): string
    {
        return 'person';
    }

    protected function validateTypeSpecificFields(): void
    {
        // Gender is optional but must be valid if present
        if (isset($this->data['gender']) && !in_array($this->data['gender'], ['male', 'female', 'other'])) {
            $this->addError('validation', 'Invalid gender value');
        }

        // Validate all connected spans
        $this->validateConnectedSpans('family', ['mother', 'father', 'children']);
        $this->validateConnectedSpans('education');
        $this->validateConnectedSpans('work');
        $this->validateConnectedSpans('places');
        $this->validateConnectedSpans('relationships');
    }

    protected function validateConnectedSpans(string $section, ?array $specificFields = null): void
    {
        if ($section !== 'family') {
            return;
        }

        if (!isset($this->data[$section])) {
            return;
        }

        $familyData = $this->data[$section];

        // Validate mother (optional string)
        if (isset($familyData['mother']) && !is_string($familyData['mother'])) {
            $this->addError('validation', 'Mother must be a string');
        }

        // Validate father (optional string)
        if (isset($familyData['father']) && !is_string($familyData['father'])) {
            $this->addError('validation', 'Father must be a string');
        }

        // Validate children (optional array of strings)
        if (isset($familyData['children'])) {
            if (!is_array($familyData['children'])) {
                $this->addError('validation', 'Children must be an array');
                return;
            }

            foreach ($familyData['children'] as $index => $child) {
                if (!is_string($child)) {
                    $this->addError('validation', "Child at index {$index} must be a string");
                }
            }
        }
    }

    protected function setTypeSpecificFields(Span $span): void
    {
        $metadata = $span->metadata ?? [];
        
        if (isset($this->data['gender'])) {
            $metadata['gender'] = $this->data['gender'];
        }

        $span->metadata = $metadata;
    }

    protected function importTypeSpecificRelationships(Span $span): void
    {
        $this->importConnections($span, 'family');
        $this->importConnections($span, 'education');
        $this->importConnections($span, 'work');
        // Handle places section for residences
        if (isset($this->data['places'])) {
            // Map places data to match residences format
            $places = array_map(function($place) {
                return array_merge($place, [
                    'place' => $place['location'] // Map location to place field
                ]);
            }, $this->data['places']);
            $this->data['residences'] = $places;
            $this->importConnections($span, 'residences');
        }
        $this->importConnections($span, 'relationships');
    }

    protected function importConnections(Span $mainSpan, string $section): void
    {
        if (!isset($this->data[$section])) {
            Log::info("No data for section {$section}, skipping");
            return;
        }

        if ($section === 'family') {
            Log::info('Processing family data', ['family' => $this->data[$section]]);

            try {
                $familyData = $this->data[$section];

                // Create dates array for parent connections using the main span's birth date
                $dates = [
                    'start_year' => $mainSpan->start_year,
                    'start_month' => $mainSpan->start_month,
                    'start_day' => $mainSpan->start_day
                ];

                // Handle mother
                if (isset($familyData['mother'])) {
                    Log::info('Processing mother connection', ['mother' => $familyData['mother']]);
                    try {
                        $motherSpan = $this->connectionImporter->findOrCreateConnectedSpan(
                            $familyData['mother'],
                            'person',
                            null,  // Don't set dates on the mother span
                            ['gender' => 'female']
                        );
                        
                        $connection = $this->connectionImporter->createConnection(
                            $motherSpan,
                            $mainSpan,
                            'family',
                            $dates,  // Use child's birth date for connection start
                            ['relationship' => 'mother']
                        );
                        Log::info('Mother connection processed', ['connection' => $connection->toArray()]);

                        $this->report['family']['details'][] = [
                            'name' => $familyData['mother'],
                            'relationship' => 'mother',
                            'person_span_id' => $motherSpan->id
                        ];

                        if ($motherSpan->wasRecentlyCreated) {
                            $this->report['family']['created']++;
                        } else {
                            $this->report['family']['existing']++;
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to process mother connection', [
                            'error' => $e->getMessage(),
                            'mother' => $familyData['mother']
                        ]);
                        $this->addWarning("Failed to process mother connection: " . $e->getMessage());
                    }
                }

                // Handle father
                if (isset($familyData['father'])) {
                    Log::info('Processing father connection', ['father' => $familyData['father']]);
                    try {
                        $fatherSpan = $this->connectionImporter->findOrCreateConnectedSpan(
                            $familyData['father'],
                            'person',
                            null,  // Don't set dates on the father span
                            ['gender' => 'male']
                        );
                        
                        $connection = $this->connectionImporter->createConnection(
                            $fatherSpan,
                            $mainSpan,
                            'family',
                            $dates,  // Use child's birth date for connection start
                            ['relationship' => 'father']
                        );
                        Log::info('Father connection processed', ['connection' => $connection->toArray()]);

                        $this->report['family']['details'][] = [
                            'name' => $familyData['father'],
                            'relationship' => 'father',
                            'person_span_id' => $fatherSpan->id
                        ];

                        if ($fatherSpan->wasRecentlyCreated) {
                            $this->report['family']['created']++;
                        } else {
                            $this->report['family']['existing']++;
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to process father connection', [
                            'error' => $e->getMessage(),
                            'father' => $familyData['father']
                        ]);
                        $this->addWarning("Failed to process father connection: " . $e->getMessage());
                    }
                }

                // Handle children
                if (isset($familyData['children']) && is_array($familyData['children'])) {
                    Log::info('Processing children connections', ['children' => $familyData['children']]);
                    foreach ($familyData['children'] as $childName) {
                        Log::info('Processing child connection', ['child' => $childName]);
                        try {
                            $childSpan = $this->connectionImporter->findOrCreateConnectedSpan(
                                $childName,
                                'person',
                                null  // Don't set dates on child spans
                            );
                            
                            // For child connections, we create placeholder connections without dates
                            // The dates will be set when the child's birth date is known
                            $connection = $this->connectionImporter->createConnection(
                                $mainSpan,
                                $childSpan,
                                'family',
                                null,  // No dates for child connections until birth date is known
                                ['relationship' => 'child']
                            );
                            Log::info('Child connection processed', ['connection' => $connection->toArray()]);

                            $this->report['family']['details'][] = [
                                'name' => $childName,
                                'relationship' => 'child',
                                'person_span_id' => $childSpan->id
                            ];

                            if ($childSpan->wasRecentlyCreated) {
                                $this->report['family']['created']++;
                            } else {
                                $this->report['family']['existing']++;
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to process child connection', [
                                'error' => $e->getMessage(),
                                'child' => $childName
                            ]);
                            $this->addWarning("Failed to process child connection: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to process family connections', [
                    'error' => $e->getMessage(),
                    'family_data' => $familyData ?? null
                ]);
                $this->addWarning("Failed to process family connections: " . $e->getMessage());
            }
            return;
        }

        // Handle array of connected spans
        foreach ($this->data[$section] as $item) {
            if (!is_array($item)) {
                $this->addWarning("Invalid item in {$section} section - expected array");
                continue;
            }

            try {
                $name = match($section) {
                    'education' => $item['institution'],
                    'work' => $item['employer'],
                    'residences' => $item['place'], // Updated to use 'place' consistently
                    'relationships' => $item['person'],
                    default => throw new \InvalidArgumentException("Unknown section type: {$section}")
                };
                
                $type = $this->getConnectedSpanType($section);
                $connectionType = $this->getSectionConnectionType($section);
                
                // Parse dates if available, but don't require them
                $dates = null;
                if (isset($item['start'])) {
                    $dates = $this->connectionImporter->parseDatesFromStrings(
                        $item['start'],
                        $item['end'] ?? null
                    );
                }

                $metadata = array_filter([
                    'role' => $item['role'] ?? null,
                    'level' => $item['level'] ?? null,
                    'course' => $item['course'] ?? null,
                    'reason' => $item['reason'] ?? null,
                    'type' => $item['type'] ?? null
                ]);

                Log::info("Creating connected span for {$section}", [
                    'name' => $name,
                    'type' => $type,
                    'dates' => $dates,
                    'metadata' => $metadata
                ]);

                $connectedSpan = $this->connectionImporter->findOrCreateConnectedSpan(
                    $name,
                    $type,
                    null,  // Don't pass dates here - they're for the connection
                    $metadata
                );

                if ($connectedSpan->wasRecentlyCreated) {
                    Log::info("Created new {$section} span", ['span' => $connectedSpan->toArray()]);
                    $this->report[$section]['created'] = ($this->report[$section]['created'] ?? 0) + 1;
                } else {
                    Log::info("Found existing {$section} span", ['span' => $connectedSpan->toArray()]);
                    $this->report[$section]['existing'] = ($this->report[$section]['existing'] ?? 0) + 1;
                }

                $connection = $this->connectionImporter->createConnection(
                    $mainSpan,
                    $connectedSpan,
                    $connectionType,
                    $dates,
                    $metadata
                );
                Log::info("{$section} connection processed", ['connection' => $connection->toArray()]);

                // Add to report details
                $this->report[$section]['details'][] = [
                    'name' => $name,
                    'span_action' => $connectedSpan->wasRecentlyCreated ? 'created' : 'existing',
                    'span_id' => $connectedSpan->id,
                    'connection_span_id' => $connection->connection_span_id
                ];

            } catch (\Exception $e) {
                Log::error("Failed to import {$section} connection", [
                    'error' => $e->getMessage(),
                    'item' => $item
                ]);
                $this->addWarning("Failed to import {$section} connection: " . $e->getMessage());
                continue;
            }
        }

        // Update total count for this section
        $this->report[$section]['total'] = count($this->report[$section]['details'] ?? []);
    }

    protected function getConnectedSpanType(string $section): string
    {
        return match($section) {
            'education' => 'organisation',
            'work' => 'organisation',
            'residences' => 'place',
            'relationships' => 'person',
            default => throw new \InvalidArgumentException("Unknown section type: {$section}")
        };
    }

    protected function getSectionConnectionType(string $section): string
    {
        return match($section) {
            'education' => 'education',
            'work' => 'employment',
            'residences' => 'residence',
            'relationships' => 'relationship',
            default => throw new \InvalidArgumentException("Unknown connection type: {$section}")
        };
    }
} 
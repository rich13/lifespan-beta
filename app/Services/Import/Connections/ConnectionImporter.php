<?php

namespace App\Services\Import\Connections;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use Illuminate\Support\Facades\Log;

class ConnectionImporter
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Find or create a span that will be connected to another span.
     * 
     * @param string $name The name of the span to find/create
     * @param string $type The type ID of the span
     * @param array|null $dates Connection dates (when the relationship existed)
     * @param array|null $metadata Additional metadata for the span
     * @return Span The found or created span
     */
    public function findOrCreateConnectedSpan(
        string $name,
        string $type,
        ?array $dates = null,
        ?array $metadata = null
    ): Span {
        // First try to find an existing span with this name and type (case insensitive)
        $span = Span::where('name', 'ILIKE', $name)
            ->where('type_id', $type)
            ->first();
            
        if (!$span) {
            // Create a new span if none exists
            $span = Span::create([
                'name' => $name,
                'type_id' => $type,
                'owner_id' => $this->user->id,
                'updater_id' => $this->user->id,
                'state' => 'placeholder',
                'metadata' => $metadata
            ]);
        }

        // Important: We do NOT set the span's temporal information from the connection dates
        // The dates passed to this method represent when the CONNECTION existed
        // They do not necessarily represent when the SPAN itself existed
        // For example: If Person A worked at Company B from 1990-1995,
        // that doesn't mean Company B only existed from 1990-1995
        
        // We only update the span's temporal information if:
        // 1. The metadata contains explicit birth/death or founding/dissolution dates
        // 2. We receive explicit span temporal information from the import
        // Otherwise, we leave it as a placeholder until we get definitive information

        return $span;
    }

    public function createConnection(
        Span $parent,
        Span $child,
        string $connectionType,
        ?array $dates = null,
        ?array $metadata = null
    ): Connection {
        // Prevent self-referential connections
        if ($parent->id === $child->id) {
            Log::error('Self-referential connection attempt prevented', [
                'span_id' => $parent->id,
                'span_name' => $parent->name,
                'connection_type' => $connectionType
            ]);
            throw new \InvalidArgumentException(
                "A span cannot be connected to itself. Span '{$parent->name}' cannot have a '{$connectionType}' connection to itself."
            );
        }

        Log::info('Creating connection', [
            'parent' => $parent->toArray(),
            'child' => $child->toArray(),
            'type' => $connectionType,
            'dates' => $dates,
            'metadata' => $metadata
        ]);

        // Validate dates if provided
        if ($dates) {
            $startTimestamp = mktime(
                0, 0, 0,
                $dates['start_month'] ?? 1,
                $dates['start_day'] ?? 1,
                $dates['start_year']
            );
            
            if (isset($dates['end_year'])) {
                $endTimestamp = mktime(
                    0, 0, 0,
                    $dates['end_month'] ?? 12,
                    $dates['end_day'] ?? 31,
                    $dates['end_year']
                );
                
                if ($endTimestamp < $startTimestamp) {
                    Log::warning('Invalid date range: end date before start date', [
                        'start_date' => [
                            'year' => $dates['start_year'],
                            'month' => $dates['start_month'] ?? 1,
                            'day' => $dates['start_day'] ?? 1
                        ],
                        'end_date' => [
                            'year' => $dates['end_year'],
                            'month' => $dates['end_month'] ?? 12,
                            'day' => $dates['end_day'] ?? 31
                        ]
                    ]);
                    // Remove invalid end date
                    unset($dates['end_year'], $dates['end_month'], $dates['end_day']);
                }
            }
        }

        // For residence connections, we need to allow multiple connections to the same place
        // but only if they have different date ranges
        if ($connectionType === 'residence') {
            // Check for existing connection with the same date range
            $existingConnection = Connection::where('parent_id', $parent->id)
                ->where('child_id', $child->id)
                ->where('type_id', $connectionType)
                ->whereHas('connectionSpan', function($query) use ($dates) {
                    if ($dates) {
                        $query->where([
                            'start_year' => $dates['start_year'] ?? null,
                            'start_month' => $dates['start_month'] ?? null,
                            'start_day' => $dates['start_day'] ?? null,
                            'end_year' => $dates['end_year'] ?? null,
                            'end_month' => $dates['end_month'] ?? null,
                            'end_day' => $dates['end_day'] ?? null
                        ]);
                    }
                })->first();
        } else {
            // For other connection types, keep the existing behavior
            $existingConnection = Connection::where('parent_id', $parent->id)
                ->where('child_id', $child->id)
                ->where('type_id', $connectionType)
                ->first();
        }

        if ($existingConnection) {
            Log::info('Found existing connection', ['connection' => $existingConnection->toArray()]);
            // If connection exists, update its connection span with new dates/metadata
            $connectionSpan = $existingConnection->connectionSpan;
            if ($dates || $metadata) {
                // Update metadata (connection_type is now handled as a root field, not in metadata)
                $updatedMetadata = array_merge($connectionSpan->metadata ?? [], $metadata ?? []);
                
                $connectionSpan->update([
                    'start_year' => $dates['start_year'] ?? $connectionSpan->start_year,
                    'start_month' => $dates['start_month'] ?? $connectionSpan->start_month,
                    'start_day' => $dates['start_day'] ?? $connectionSpan->start_day,
                    'end_year' => $dates['end_year'] ?? $connectionSpan->end_year,
                    'end_month' => $dates['end_month'] ?? $connectionSpan->end_month,
                    'end_day' => $dates['end_day'] ?? $connectionSpan->end_day,
                    'metadata' => $updatedMetadata,
                    'updater_id' => $this->user->id,
                    'state' => 'placeholder'  // Always use placeholder state
                ]);
            }
            return $existingConnection;
        }

        // Create a connection span to represent the temporal relationship
        Log::info('Creating connection span with dates', [
            'dates' => $dates,
            'parent' => $parent->name,
            'child' => $child->name,
            'type' => $connectionType
        ]);
        
        // Use metadata as provided (connection_type is now handled as a root field, not in metadata)
        $connectionMetadata = $metadata ?? [];
        
        $connectionSpan = Span::create([
            'name' => $this->generateConnectionName($parent, $child, $connectionType),
            'type_id' => 'connection',
            'start_year' => $dates['start_year'] ?? null,
            'start_month' => $dates['start_month'] ?? null,
            'start_day' => $dates['start_day'] ?? null,
            'end_year' => $dates['end_year'] ?? null,
            'end_month' => $dates['end_month'] ?? null,
            'end_day' => $dates['end_day'] ?? null,
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'access_level' => 'public',
            'state' => 'placeholder',  // Always start as placeholder
            'metadata' => $connectionMetadata
        ]);
        Log::info('Created connection span', [
            'connection_span' => $connectionSpan->toArray(),
            'start_precision' => $connectionSpan->start_precision,
            'end_precision' => $connectionSpan->end_precision
        ]);

        // Create the actual connection
        $connection = Connection::create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => $connectionType,
            'connection_span_id' => $connectionSpan->id
        ]);
        Log::info('Created connection', [
            'connection' => $connection->toArray(),
            'connection_span' => $connection->connectionSpan
        ]);

        return $connection;
    }

    public function parseDatesFromStrings(
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        if (!$startDate && !$endDate) {
            return null;
        }

        $dates = [];
        
        // Parse start date if provided
        if ($startDate) {
            $parsedStart = $this->parseLinkedInDate($startDate);
            if ($parsedStart) {
                $dates['start_year'] = $parsedStart['year'];
                if (isset($parsedStart['month'])) $dates['start_month'] = $parsedStart['month'];
                if (isset($parsedStart['day'])) $dates['start_day'] = $parsedStart['day'];
            }
        }

        // Parse end date if provided
        if ($endDate) {
            $parsedEnd = $this->parseLinkedInDate($endDate);
            if ($parsedEnd) {
                $dates['end_year'] = $parsedEnd['year'];
                if (isset($parsedEnd['month'])) $dates['end_month'] = $parsedEnd['month'];
                if (isset($parsedEnd['day'])) $dates['end_day'] = $parsedEnd['day'];

                // Validate that end date is not before start date
                if (isset($dates['start_year'])) {
                    $startTimestamp = mktime(
                        0, 0, 0,
                        $dates['start_month'] ?? 1,
                        $dates['start_day'] ?? 1,
                        $dates['start_year']
                    );
                    $endTimestamp = mktime(
                        0, 0, 0,
                        $dates['end_month'] ?? 12,
                        $dates['end_day'] ?? 31,
                        $dates['end_year']
                    );
                    
                    if ($endTimestamp < $startTimestamp) {
                        Log::warning('Invalid date range: end date before start date', [
                            'start_date' => $startDate,
                            'end_date' => $endDate
                        ]);
                        // Remove invalid end date
                        unset($dates['end_year'], $dates['end_month'], $dates['end_day']);
                    }
                }
            }
        }

        return empty($dates) ? null : $dates;
    }

    /**
     * Parse LinkedIn date format (e.g., "Jul 2023", "May 2014", "2020", "2020-01-01")
     */
    public function parseLinkedInDate(?string $dateString): ?array
    {
        if (empty($dateString)) {
            return null;
        }

        $dateString = trim($dateString);

        // Try year-only format first (YYYY)
        if (preg_match('/^\d{4}$/', $dateString)) {
            return ['year' => (int) $dateString];
        }

        // Try ISO format (YYYY-MM-DD, YYYY-MM, YYYY)
        if (preg_match('/^\d{4}(-\d{1,2}(-\d{1,2})?)?$/', $dateString)) {
            try {
                $parts = explode('-', $dateString);
                $result = ['year' => (int) $parts[0]];
                if (isset($parts[1])) $result['month'] = (int) $parts[1];
                if (isset($parts[2])) $result['day'] = (int) $parts[2];
                return $result;
            } catch (\Exception $e) {
                Log::warning('Failed to parse ISO date format', [
                    'date_string' => $dateString,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }

        // Try LinkedIn format (Month Year, e.g., "Jul 2023", "May 2014")
        if (preg_match('/^([A-Za-z]+)\s+(\d{4})$/', $dateString, $matches)) {
            $monthName = $matches[1];
            $year = (int) $matches[2];
            
            $monthMap = [
                'jan' => 1, 'january' => 1,
                'feb' => 2, 'february' => 2,
                'mar' => 3, 'march' => 3,
                'apr' => 4, 'april' => 4,
                'may' => 5,
                'jun' => 6, 'june' => 6,
                'jul' => 7, 'july' => 7,
                'aug' => 8, 'august' => 8,
                'sep' => 9, 'september' => 9,
                'oct' => 10, 'october' => 10,
                'nov' => 11, 'november' => 11,
                'dec' => 12, 'december' => 12
            ];
            
            $monthLower = strtolower($monthName);
            if (isset($monthMap[$monthLower])) {
                return [
                    'year' => $year,
                    'month' => $monthMap[$monthLower]
                ];
            } else {
                Log::warning('Unknown month name in LinkedIn date', [
                    'date_string' => $dateString,
                    'month_name' => $monthName
                ]);
                return null;
            }
        }

        // Try other common formats
        // Full date with day (e.g., "14 May 2020", "May 14, 2020")
        $patterns = [
            '/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/', // "14 May 2020"
            '/^([A-Za-z]+)\s+(\d{1,2})\s*,?\s*(\d{4})$/', // "May 14, 2020" or "May 14 2020" or "May 14,2020"
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $dateString, $matches)) {
                $monthName = $matches[2] ?? $matches[1];
                $day = (int) ($matches[1] ?? $matches[2]);
                $year = (int) $matches[3];
                
                $monthMap = [
                    'jan' => 1, 'january' => 1,
                    'feb' => 2, 'february' => 2,
                    'mar' => 3, 'march' => 3,
                    'apr' => 4, 'april' => 4,
                    'may' => 5,
                    'jun' => 6, 'june' => 6,
                    'jul' => 7, 'july' => 7,
                    'aug' => 8, 'august' => 8,
                    'sep' => 9, 'september' => 9,
                    'oct' => 10, 'october' => 10,
                    'nov' => 11, 'november' => 11,
                    'dec' => 12, 'december' => 12
                ];
                
                $monthLower = strtolower($monthName);
                if (isset($monthMap[$monthLower])) {
                    return [
                        'year' => $year,
                        'month' => $monthMap[$monthLower],
                        'day' => $day
                    ];
                }
            }
        }

        Log::warning('Could not parse LinkedIn date format', [
            'date_string' => $dateString
        ]);
        
        return null;
    }

    protected function generateConnectionName(Span $parent, Span $child, string $connectionType): string
    {
        // Get the connection type model
        $type = ConnectionType::findOrFail($connectionType);
        
        // Use the forward predicate from the model
        return "{$parent->name} {$type->forward_predicate} {$child->name}";
    }
} 
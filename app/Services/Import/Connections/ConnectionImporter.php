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
            try {
                $parts = explode('-', $startDate);
                $dates['start_year'] = (int) $parts[0];
                if (isset($parts[1])) $dates['start_month'] = (int) $parts[1];
                if (isset($parts[2])) $dates['start_day'] = (int) $parts[2];
            } catch (\Exception $e) {
                // If date parsing fails, return null to indicate no dates
                return null;
            }
        }

        // Parse end date if provided
        if ($endDate) {
            try {
                $parts = explode('-', $endDate);
                $dates['end_year'] = (int) $parts[0];
                if (isset($parts[1])) $dates['end_month'] = (int) $parts[1];
                if (isset($parts[2])) $dates['end_day'] = (int) $parts[2];

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
            } catch (\Exception $e) {
                // If end date parsing fails, just omit it
                unset($dates['end_year'], $dates['end_month'], $dates['end_day']);
            }
        }

        return empty($dates) ? null : $dates;
    }

    protected function generateConnectionName(Span $parent, Span $child, string $connectionType): string
    {
        // Get the connection type model
        $type = ConnectionType::findOrFail($connectionType);
        
        // Use the forward predicate from the model
        return "{$parent->name} {$type->forward_predicate} {$child->name}";
    }
} 
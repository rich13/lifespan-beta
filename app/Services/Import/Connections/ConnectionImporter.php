<?php

namespace App\Services\Import\Connections;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;

class ConnectionImporter
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function findOrCreateConnectedSpan(
        string $name,
        string $type,
        ?array $dates = null,
        ?array $metadata = null
    ): Span {
        // Find or create the connected span
        $span = Span::firstOrCreate(
            [
                'name' => $name,
                'type_id' => $type
            ],
            [
                'owner_id' => $this->user->id,
                'updater_id' => $this->user->id,
                'state' => 'placeholder',
                'metadata' => $metadata
            ]
        );

        // If dates are provided and the span is new or a placeholder, update them
        if ($dates && ($span->wasRecentlyCreated || $span->state === 'placeholder')) {
            if (isset($dates['start_year'])) {
                $span->start_year = $dates['start_year'];
                $span->start_month = $dates['start_month'] ?? null;
                $span->start_day = $dates['start_day'] ?? null;
            }
            if (isset($dates['end_year'])) {
                $span->end_year = $dates['end_year'];
                $span->end_month = $dates['end_month'] ?? null;
                $span->end_day = $dates['end_day'] ?? null;
            }
            $span->save();
        }

        return $span;
    }

    public function createConnection(
        Span $parent,
        Span $child,
        string $connectionType,
        ?array $dates = null,
        ?array $metadata = null
    ): Connection {
        // Create a connection span to represent the temporal relationship
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
            'state' => 'complete',
            'metadata' => $metadata
        ]);

        // Create the actual connection
        return Connection::create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => $connectionType,
            'connection_span_id' => $connectionSpan->id
        ]);
    }

    public function parseDatesFromStrings(
        string $startDate,
        ?string $endDate = null
    ): array {
        $dates = [];
        
        // Parse start date
        $parts = explode('-', $startDate);
        $dates['start_year'] = (int) $parts[0];
        if (isset($parts[1])) $dates['start_month'] = (int) $parts[1];
        if (isset($parts[2])) $dates['start_day'] = (int) $parts[2];

        // Parse end date if provided
        if ($endDate) {
            $parts = explode('-', $endDate);
            $dates['end_year'] = (int) $parts[0];
            if (isset($parts[1])) $dates['end_month'] = (int) $parts[1];
            if (isset($parts[2])) $dates['end_day'] = (int) $parts[2];
        }

        return $dates;
    }

    protected function generateConnectionName(Span $parent, Span $child, string $connectionType): string
    {
        $typeMap = [
            'education' => 'education at',
            'employment' => 'employment at',
            'residence' => 'residence in',
            'relationship' => 'relationship with',
            'family' => 'family relationship with',
            'member_of' => 'membership in'
        ];

        $description = $typeMap[$connectionType] ?? 'connection with';
        return "{$parent->name}'s {$description} {$child->name}";
    }
} 
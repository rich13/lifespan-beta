<?php

namespace App\Services\Import\Types;

use App\Models\Span;
use App\Models\Connection;

class BandImporter extends BaseSpanImporter
{
    protected function getSpanType(): string
    {
        return 'band';
    }

    protected function validateTypeSpecificFields(): void
    {
        // Validate members if present
        if (isset($this->data['members'])) {
            if (!is_array($this->data['members'])) {
                $this->addError('validation', 'Members must be an array');
            } else {
                foreach ($this->data['members'] as $member) {
                    if (!isset($member['name'])) {
                        $this->addError('validation', 'Each member must have a name');
                    }
                    if (!isset($member['role'])) {
                        $this->addError('validation', 'Each member must have a role');
                    }
                    if (!isset($member['start'])) {
                        $this->addError('validation', 'Each member must have a start date');
                    }
                }
            }
        }
    }

    protected function simulateTypeSpecificImport(): void
    {
        // Simulate member connections
        if (isset($this->data['members'])) {
            $memberCount = count($this->data['members']);
            
            foreach ($this->data['members'] as $member) {
                $existingPerson = Span::where('name', $member['name'])
                    ->where('type_id', 'person')
                    ->first();

                $this->updateReport('relationships', [
                    'details' => [[
                        'name' => $member['name'],
                        'role' => $member['role'],
                        'action' => $existingPerson ? 'will_use_existing' : 'will_create'
                    ]]
                ]);

                if ($existingPerson) {
                    $this->updateReport('relationships', ['existing' => 1]);
                } else {
                    $this->updateReport('relationships', ['will_create' => 1]);
                }
            }

            $this->updateReport('relationships', ['total' => $memberCount]);
        }
    }

    protected function setTypeSpecificFields(Span $span): void
    {
        // No additional fields needed for band spans at this time
    }

    protected function importTypeSpecificRelationships(Span $span): void
    {
        // Import band members
        if (isset($this->data['members'])) {
            foreach ($this->data['members'] as $member) {
                $this->createMemberConnection($span, $member);
            }
        }
    }

    protected function createMemberConnection(Span $bandSpan, array $member): void
    {
        // Create or find the person span
        $personSpan = Span::firstOrCreate(
            ['name' => $member['name'], 'type_id' => 'person'],
            [
                'owner_id' => $this->user->id,
                'updater_id' => $this->user->id,
                'state' => 'placeholder'
            ]
        );

        // Parse dates
        $startDates = $this->parseDate($member['start']);
        $endDates = isset($member['end']) ? $this->parseDate($member['end']) : null;

        // Create a connection span
        $connectionSpan = Span::create([
            'name' => "{$member['name']}'s membership in {$bandSpan->name}",
            'type_id' => 'connection',
            'start_year' => $startDates['year'],
            'start_month' => $startDates['month'] ?? null,
            'start_day' => $startDates['day'] ?? null,
            'end_year' => $endDates['year'] ?? null,
            'end_month' => $endDates['month'] ?? null,
            'end_day' => $endDates['day'] ?? null,
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'state' => 'complete',
            'metadata' => [
                'role' => $member['role']
            ]
        ]);

        // Create the connection
        Connection::firstOrCreate([
            'parent_id' => $bandSpan->id,
            'child_id' => $personSpan->id,
            'type_id' => 'member_of',
            'connection_span_id' => $connectionSpan->id
        ]);
    }
} 
<?php

namespace App\Services\Import\Types;

use App\Models\Span;
use App\Models\Connection;
use App\Models\User;
use App\Http\Controllers\SpanController;

class BandImporter extends BaseSpanImporter
{
    protected ConnectionImporter $connectionImporter;
    protected SpanController $spanController;

    public function __construct(User $user)
    {
        parent::__construct($user);
        $this->connectionImporter = new ConnectionImporter($user);
        $this->spanController = new SpanController();
    }

    protected function getSpanType(): string
    {
        return 'band';
    }

    protected function validateTypeSpecificFields(): void
    {
        // Validate members array
        if (!isset($this->data['members']) || !is_array($this->data['members'])) {
            $this->addError('validation', 'Members must be an array');
            return;
        }

        foreach ($this->data['members'] as $member) {
            if (!isset($member['name'])) {
                $this->addError('validation', 'Member must have a name');
            }
            if (!isset($member['start'])) {
                $this->addError('validation', 'Member must have a start date');
            }
            if (!isset($member['role'])) {
                $this->addError('validation', 'Member must have a role');
            }
        }
    }

    protected function setTypeSpecificFields(Span $span): void
    {
        // No type-specific fields for bands yet
    }

    protected function importTypeSpecificRelationships(Span $bandSpan): void
    {
        foreach ($this->data['members'] as $member) {
            $this->importMemberConnection($bandSpan, $member);
        }
    }

    protected function importMemberConnection(Span $bandSpan, array $member): void
    {
        try {
            // Parse dates using the ConnectionImporter
            $dates = $this->connectionImporter->parseDatesFromStrings(
                $member['start'],
                $member['end'] ?? null
            );

            // Create metadata
            $metadata = [
                'role' => $member['role']
            ];

            // Find or create the person span using the ConnectionImporter
            $personSpan = $this->connectionImporter->findOrCreateConnectedSpan(
                $member['name'],
                'person',
                $dates,
                $metadata
            );

            // Create the connection using the ConnectionImporter
            $this->connectionImporter->createConnection(
                $bandSpan,
                $personSpan,
                'member_of',
                $dates,
                $metadata
            );

            // Update report
            $this->report['members']['details'][] = [
                'name' => $member['name'],
                'role' => $member['role'],
                'person_span_id' => $personSpan->id
            ];
        } catch (\Exception $e) {
            $this->addError('member', $e->getMessage(), [
                'data' => $member
            ]);
            throw $e;
        }
    }
} 
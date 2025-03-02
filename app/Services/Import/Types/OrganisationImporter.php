<?php

namespace App\Services\Import\Types;

use App\Models\Span;
use App\Services\Import\Connections\ConnectionImporter;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OrganisationImporter extends BaseSpanImporter
{
    protected ConnectionImporter $connectionImporter;

    public function __construct(User $user)
    {
        parent::__construct($user);
        $this->connectionImporter = new ConnectionImporter($user);
    }

    protected function getSpanType(): string
    {
        return 'organisation';
    }

    protected function validateTypeSpecificFields(): void
    {
        // Validate organisation type if present
        if (isset($this->data['type']) && !in_array($this->data['type'], [
            'business', 'educational', 'government', 'non-profit', 'religious', 'other'
        ])) {
            $this->addError('validation', 'Invalid organisation type');
        }

        // Validate all connected spans
        $this->validateConnectedSpans('members');
        $this->validateConnectedSpans('relationships');
    }

    protected function validateConnectedSpans(string $section, ?array $specificFields = null): void
    {
        if (!isset($this->data[$section])) {
            return;
        }

        if (!is_array($this->data[$section])) {
            $this->addError('validation', "{$section} must be an array");
            return;
        }

        foreach ($this->data[$section] as $item) {
            if (!is_array($item)) {
                $this->addError('validation', "Each item in {$section} must be an array");
                continue;
            }

            if ($section === 'members') {
                if (!isset($item['name'])) {
                    $this->addError('validation', 'Member must have a name');
                }
                if (!isset($item['role'])) {
                    $this->addError('validation', 'Member must have a role');
                }
            } else if ($section === 'relationships') {
                if (!isset($item['organisation'])) {
                    $this->addError('validation', 'Relationship must specify an organisation');
                }
                if (!isset($item['type'])) {
                    $this->addError('validation', 'Relationship must specify a type');
                }
            }
        }
    }

    protected function setTypeSpecificFields(Span $span): void
    {
        $metadata = $span->metadata ?? [];
        
        // Set organisation type
        if (isset($this->data['type'])) {
            $metadata['type'] = $this->data['type'];
        }

        // Set other optional fields
        if (isset($this->data['industry'])) {
            $metadata['industry'] = $this->data['industry'];
        }
        if (isset($this->data['headquarters'])) {
            $metadata['headquarters'] = $this->data['headquarters'];
        }
        if (isset($this->data['website'])) {
            $metadata['website'] = $this->data['website'];
        }

        $span->metadata = $metadata;
    }

    protected function importTypeSpecificRelationships(Span $mainSpan): void
    {
        // Import members if present
        if (isset($this->data['members'])) {
            $this->importMembers($mainSpan);
        }

        // Import relationships with other organisations if present
        if (isset($this->data['relationships'])) {
            $this->importRelationships($mainSpan);
        }
    }

    protected function importMembers(Span $mainSpan): void
    {
        foreach ($this->data['members'] as $member) {
            try {
                // Parse dates if available
                $dates = null;
                if (isset($member['start'])) {
                    $dates = $this->connectionImporter->parseDatesFromStrings(
                        $member['start'],
                        $member['end'] ?? null
                    );
                }

                // Create metadata
                $metadata = array_filter([
                    'role' => $member['role'] ?? null,
                    'department' => $member['department'] ?? null,
                    'title' => $member['title'] ?? null
                ]);

                // Find or create the person span
                $personSpan = $this->connectionImporter->findOrCreateConnectedSpan(
                    $member['name'],
                    'person',
                    null,  // Don't set dates on the person span
                    $metadata
                );

                // Create the connection
                $connection = $this->connectionImporter->createConnection(
                    $mainSpan,
                    $personSpan,
                    'employment',
                    $dates,
                    $metadata
                );

                // Update report
                $this->report['members']['details'][] = [
                    'name' => $member['name'],
                    'role' => $member['role'],
                    'person_span_id' => $personSpan->id,
                    'connection_span_id' => $connection->connection_span_id
                ];

                if ($personSpan->wasRecentlyCreated) {
                    $this->report['members']['created']++;
                } else {
                    $this->report['members']['existing']++;
                }

            } catch (\Exception $e) {
                Log::error('Failed to import member', [
                    'error' => $e->getMessage(),
                    'member' => $member
                ]);
                $this->addWarning("Failed to import member {$member['name']}: " . $e->getMessage());
            }
        }

        $this->report['members']['total'] = count($this->report['members']['details'] ?? []);
    }

    protected function importRelationships(Span $mainSpan): void
    {
        foreach ($this->data['relationships'] as $relationship) {
            try {
                // Parse dates if available
                $dates = null;
                if (isset($relationship['start'])) {
                    $dates = $this->connectionImporter->parseDatesFromStrings(
                        $relationship['start'],
                        $relationship['end'] ?? null
                    );
                }

                // Create metadata
                $metadata = array_filter([
                    'type' => $relationship['type'] ?? null,
                    'description' => $relationship['description'] ?? null
                ]);

                // Find or create the related organisation span
                $relatedSpan = $this->connectionImporter->findOrCreateConnectedSpan(
                    $relationship['organisation'],
                    'organisation',
                    null,  // Don't set dates on the organisation span
                    $metadata
                );

                // Create the connection
                $connection = $this->connectionImporter->createConnection(
                    $mainSpan,
                    $relatedSpan,
                    'relationship',
                    $dates,
                    $metadata
                );

                // Update report
                $this->report['relationships']['details'][] = [
                    'name' => $relationship['organisation'],
                    'type' => $relationship['type'],
                    'organisation_span_id' => $relatedSpan->id,
                    'connection_span_id' => $connection->connection_span_id
                ];

                if ($relatedSpan->wasRecentlyCreated) {
                    $this->report['relationships']['created']++;
                } else {
                    $this->report['relationships']['existing']++;
                }

            } catch (\Exception $e) {
                Log::error('Failed to import relationship', [
                    'error' => $e->getMessage(),
                    'relationship' => $relationship
                ]);
                $this->addWarning("Failed to import relationship with {$relationship['organisation']}: " . $e->getMessage());
            }
        }

        $this->report['relationships']['total'] = count($this->report['relationships']['details'] ?? []);
    }
} 
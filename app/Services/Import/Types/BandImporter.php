<?php

namespace App\Services\Import\Types;

use App\Models\Span;
use App\Models\Connection;
use App\Models\User;
use App\Http\Controllers\SpanController;
use App\Services\Import\Connections\ConnectionImporter;
use Illuminate\Support\Facades\Log;

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
        // Validate all connected spans
        $this->validateConnectedSpans('members');
    }

    protected function validateConnectedSpans(string $section): void
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
                if (!isset($item['start'])) {
                    $this->addError('validation', 'Member must have a start date');
                }
                if (!isset($item['role'])) {
                    $this->addError('validation', 'Member must have a role');
                }
            }
        }
    }

    protected function setTypeSpecificFields(Span $span): void
    {
        $metadata = $span->metadata ?? [];
        
        // Set metadata from the YAML file
        if (isset($this->data['metadata'])) {
            $metadata = array_merge($metadata, $this->data['metadata']);
        }

        $span->metadata = $metadata;
    }

    protected function importTypeSpecificRelationships(Span $mainSpan): void
    {
        if (isset($this->data['members'])) {
            $this->importMembers($mainSpan);
        }
    }

    protected function importMembers(Span $mainSpan): void
    {
        foreach ($this->data['members'] as $member) {
            Log::info('Importing band member', ['member' => $member]);
            // Parse dates using the ConnectionImporter
            $dates = null;
            if (isset($member['start'])) {
                $dates = $this->connectionImporter->parseDatesFromStrings(
                    $member['start'],
                    $member['end'] ?? null
                );
                Log::info('Parsed member dates', [
                    'member' => $member['name'],
                    'start' => $member['start'],
                    'end' => $member['end'] ?? null,
                    'parsed_dates' => $dates
                ]);
            }

            // Create metadata
            $metadata = array_filter([
                'role' => $member['role']
            ]);

            // Find or create the person span
            $personSpan = $this->connectionImporter->findOrCreateConnectedSpan(
                $member['name'],
                'person',
                null,  // Don't set dates on the person span
                $metadata
            );
            Log::info('Found/created person span', ['person' => $personSpan]);

            // Create the connection with dates
            Log::info('Creating connection with dates', [
                'member' => $member['name'],
                'dates' => $dates,
                'metadata' => $metadata
            ]);
            $connection = $this->connectionImporter->createConnection(
                $personSpan,  // person is the parent (subject)
                $mainSpan,    // band is the child (object)
                'membership',
                $dates,       // Pass the parsed dates
                $metadata
            );
            Log::info('Created connection', [
                'connection' => $connection,
                'connection_span' => $connection->connectionSpan
            ]);

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
        }

        $this->report['members']['total'] = count($this->report['members']['details'] ?? []);
    }

    protected function getBaseReport(): array
    {
        $report = parent::getBaseReport();
        $report['members'] = [
            'created' => 0,
            'existing' => 0,
            'total' => 0,
            'details' => []
        ];
        return $report;
    }
} 
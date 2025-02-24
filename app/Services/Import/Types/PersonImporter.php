<?php

namespace App\Services\Import\Types;

use App\Models\Span;
use App\Services\Import\Connections\ConnectionImporter;
use App\Models\User;

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
        if (!isset($this->data[$section])) {
            return;
        }

        if ($specificFields) {
            // Handle specific fields like family structure
            foreach ($specificFields as $field) {
                if (isset($this->data[$section][$field])) {
                    if ($field === 'children') {
                        if (!is_array($this->data[$section][$field])) {
                            $this->addError('validation', 'Children must be an array');
                        }
                    } elseif (!is_string($this->data[$section][$field])) {
                        $this->addError('validation', ucfirst($field) . ' must be a string');
                    }
                }
            }
        } else {
            // Handle array of connected spans
            if (!is_array($this->data[$section])) {
                $this->addError('validation', ucfirst($section) . ' must be an array');
                return;
            }

            foreach ($this->data[$section] as $item) {
                if (!isset($item['start'])) {
                    $this->addError('validation', ucfirst($section) . ' entry must have a start date');
                }
            }
        }
    }

    protected function simulateTypeSpecificImport(): void
    {
        $this->simulateConnections('family');
        $this->simulateConnections('education');
        $this->simulateConnections('work');
        $this->simulateConnections('places');
        $this->simulateConnections('relationships');
    }

    protected function simulateConnections(string $section): void
    {
        if (!isset($this->data[$section])) {
            return;
        }

        $count = 0;
        $details = [];

        if ($section === 'family') {
            // Handle family structure
            if (isset($this->data[$section]['mother'])) {
                $count++;
                $details[] = $this->simulateConnection($this->data[$section]['mother'], 'person', 'mother');
            }
            if (isset($this->data[$section]['father'])) {
                $count++;
                $details[] = $this->simulateConnection($this->data[$section]['father'], 'person', 'father');
            }
            if (isset($this->data[$section]['children'])) {
                foreach ($this->data[$section]['children'] as $child) {
                    $count++;
                    $details[] = $this->simulateConnection($child, 'person', 'child');
                }
            }
        } else {
            // Handle array of connected spans
            foreach ($this->data[$section] as $item) {
                $count++;
                $name = match($section) {
                    'education' => $item['institution'],
                    'work' => $item['institution'],
                    'places' => $item['location'],
                    'relationships' => $item['person'],
                    default => throw new \InvalidArgumentException("Unknown section type: {$section}")
                };
                
                $details[] = $this->simulateConnection(
                    $name,
                    $this->getConnectedSpanType($section),
                    $item['role'] ?? null
                );
            }
        }

        $this->updateReport($section, [
            'total' => $count,
            'details' => $details
        ]);
    }

    protected function simulateConnection(string $name, string $type, ?string $role = null): array
    {
        $existing = Span::where('name', $name)
            ->where('type_id', $type)
            ->first();

        return [
            'name' => $name,
            'role' => $role,
            'action' => $existing ? 'will_use_existing' : 'will_create'
        ];
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
        $this->importConnections($span, 'places');
        $this->importConnections($span, 'relationships');
    }

    protected function importConnections(Span $mainSpan, string $section): void
    {
        if (!isset($this->data[$section])) {
            return;
        }

        if ($section === 'family') {
            // Handle family structure
            if (isset($this->data[$section]['mother'])) {
                $this->createConnection($mainSpan, $this->data[$section]['mother'], 'person', 'family', false);
            }
            if (isset($this->data[$section]['father'])) {
                $this->createConnection($mainSpan, $this->data[$section]['father'], 'person', 'family', false);
            }
            if (isset($this->data[$section]['children'])) {
                foreach ($this->data[$section]['children'] as $child) {
                    $this->createConnection($mainSpan, $child, 'person', 'family', true);
                }
            }
        } else {
            // Handle array of connected spans
            foreach ($this->data[$section] as $item) {
                $name = match($section) {
                    'education' => $item['institution'],
                    'work' => $item['institution'],
                    'places' => $item['location'],
                    'relationships' => $item['person'],
                    default => throw new \InvalidArgumentException("Unknown section type: {$section}")
                };
                
                $type = $this->getConnectedSpanType($section);
                $connectionType = $this->getConnectionType($section);
                
                $dates = $this->connectionImporter->parseDatesFromStrings(
                    $item['start'],
                    $item['end'] ?? null
                );

                $metadata = array_filter([
                    'role' => $item['role'] ?? null,
                    'level' => $item['level'] ?? null,
                    'course' => $item['course'] ?? null,
                    'reason' => $item['reason'] ?? null,
                    'type' => $item['type'] ?? null
                ]);

                $connectedSpan = $this->connectionImporter->findOrCreateConnectedSpan(
                    $name,
                    $type,
                    $dates,
                    $metadata
                );

                $this->connectionImporter->createConnection(
                    $mainSpan,
                    $connectedSpan,
                    $connectionType,
                    $dates,
                    $metadata
                );
            }
        }
    }

    protected function getConnectedSpanType(string $section): string
    {
        return match($section) {
            'education' => 'organisation',
            'work' => 'organisation',
            'places' => 'place',
            'relationships' => 'person',
            default => throw new \InvalidArgumentException("Unknown section type: {$section}")
        };
    }

    protected function getConnectionType(string $section): string
    {
        return match($section) {
            'education' => 'education',
            'work' => 'employment',
            'places' => 'residence',
            'relationships' => 'relationship',
            default => throw new \InvalidArgumentException("Unknown connection type: {$section}")
        };
    }
} 
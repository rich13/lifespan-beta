<?php

namespace App\Services\Import\Types;

use App\Models\Span;
use App\Services\Import\SpanImporter;

abstract class BaseSpanImporter extends SpanImporter
{
    protected function validateYaml(): void
    {
        if (!isset($this->data['name'])) {
            $this->addError('validation', 'Name is required');
        }

        if (!isset($this->data['type'])) {
            $this->addError('validation', 'Type is required');
        }

        if ($this->data['type'] !== $this->getSpanType()) {
            $this->addError('validation', "Invalid type: {$this->data['type']}, expected: {$this->getSpanType()}");
        }

        // Validate dates if present
        if (isset($this->data['start'])) {
            $start = $this->parseDate($this->data['start']);
            if (!$start) {
                $this->addError('validation', 'Invalid start date format');
            }
        }

        if (isset($this->data['end']) && $this->data['end'] !== null) {
            $end = $this->parseDate($this->data['end']);
            if (!$end) {
                $this->addError('validation', 'Invalid end date format');
            }
        }

        $this->validateTypeSpecificFields();
    }

    protected function simulateImport(): void
    {
        if ($this->report['errors']) {
            return;
        }

        // Check if span already exists
        $existingSpan = Span::where('name', $this->data['name'])
            ->where('type_id', $this->getSpanType())
            ->first();

        if ($existingSpan) {
            $this->updateReport('main_span', [
                'will_update' => true,
                'existing' => true,
                'name' => $this->data['name'],
                'type' => $this->getSpanType(),
            ]);
        } else {
            $this->updateReport('main_span', [
                'will_create' => true,
                'name' => $this->data['name'],
                'type' => $this->getSpanType(),
            ]);
        }

        $this->simulateTypeSpecificImport();
    }

    protected function performImport(): void
    {
        if ($this->report['errors']) {
            return;
        }

        $span = Span::firstOrNew([
            'name' => $this->data['name'],
            'type_id' => $this->getSpanType(),
        ]);

        $dates = $this->parseDate($this->data['start']);
        if ($dates) {
            $span->start_year = $dates['year'];
            $span->start_month = $dates['month'] ?? null;
            $span->start_day = $dates['day'] ?? null;
        }

        if (isset($this->data['end']) && $this->data['end'] !== null) {
            $dates = $this->parseDate($this->data['end']);
            if ($dates) {
                $span->end_year = $dates['year'];
                $span->end_month = $dates['month'] ?? null;
                $span->end_day = $dates['day'] ?? null;
            }
        }

        // Set common fields
        $span->owner_id = $this->user->id;
        $span->updater_id = $this->user->id;
        $span->state = 'complete';  // Default to complete, can be overridden

        // Allow type-specific importers to set additional fields
        $this->setTypeSpecificFields($span);

        $span->save();

        // Allow type-specific importers to handle their own relationships
        $this->importTypeSpecificRelationships($span);
    }

    abstract protected function getSpanType(): string;
    
    abstract protected function validateTypeSpecificFields(): void;
    
    abstract protected function simulateTypeSpecificImport(): void;
    
    abstract protected function setTypeSpecificFields(Span $span): void;
    
    abstract protected function importTypeSpecificRelationships(Span $span): void;
} 
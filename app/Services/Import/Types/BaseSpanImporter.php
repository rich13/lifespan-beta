<?php

namespace App\Services\Import\Types;

use App\Models\Span;
use App\Services\Import\SpanImporter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

abstract class BaseSpanImporter extends SpanImporter
{
    protected User $user;
    protected array $data;
    protected array $report;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->report = $this->getBaseReport();
    }

    protected function getBaseReport(): array
    {
        return [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'main_span' => [
                'action' => null,
                'id' => null,
                'name' => null
            ],
            'family' => [
                'created' => 0,
                'existing' => 0,
                'total' => 0,
                'details' => []
            ]
        ];
    }

    public function import(string $yamlPath): array
    {
        $this->data = Yaml::parseFile($yamlPath);
        
        try {
            DB::beginTransaction();
            
            $this->validateYaml();
            $this->performImport();
            
            DB::commit();
            return $this->report;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->addError('general', $e->getMessage());
            return $this->report;
        }
    }

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

    protected function performImport(): void
    {
        if ($this->report['errors']) {
            return;
        }

        $span = Span::firstOrNew([
            'name' => $this->data['name'],
            'type_id' => $this->getSpanType()
        ]);

        // Parse start date and set state accordingly
        $startDate = $this->parseDate($this->data['start'] ?? null);
        $span->state = 'placeholder';  // Default to placeholder

        if ($startDate) {
            $span->start_year = $startDate->year;
            $span->start_month = $startDate->month;
            $span->start_day = $startDate->day;
            $span->state = 'complete';  // Only set to complete if we have a start date
        }

        if (isset($this->data['end']) && $this->data['end'] !== null) {
            $endDate = $this->parseDate($this->data['end']);
            if ($endDate) {
                $span->end_year = $endDate->year;
                $span->end_month = $endDate->month;
                $span->end_day = $endDate->day;
            }
        }

        // Set common fields
        $span->owner_id = $this->user->id;
        $span->updater_id = $this->user->id;

        // Allow type-specific importers to set additional fields
        $this->setTypeSpecificFields($span);

        $span->save();

        // Allow type-specific importers to handle their own relationships
        $this->importTypeSpecificRelationships($span);
    }

    abstract protected function getSpanType(): string;
    
    abstract protected function validateTypeSpecificFields(): void;
    
    abstract protected function setTypeSpecificFields(Span $span): void;
    
    abstract protected function importTypeSpecificRelationships(Span $span): void;

    protected function addError(string $type, string $message, ?array $context = null): void
    {
        $error = ['type' => $type, 'message' => $message];
        if ($context) {
            $error['context'] = $context;
        }
        $this->report['errors'][] = $error;
        $this->report['success'] = false;
    }

    protected function addWarning(string $message): void
    {
        $this->report['warnings'][] = $message;
    }

    protected function updateReport(string $section, array $data): void
    {
        foreach ($data as $key => $value) {
            if (isset($this->report[$section][$key])) {
                if (is_array($value) && isset($this->report[$section][$key])) {
                    $this->report[$section][$key] = array_merge(
                        $this->report[$section][$key],
                        $value
                    );
                } else {
                    $this->report[$section][$key] = $value;
                }
            }
        }
    }
} 
<?php

namespace App\Services\Import;

use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

abstract class SpanImporter
{
    protected User $user;
    protected array $data;
    protected array $report;
    protected bool $simulate;

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
                'will_create' => false,
                'will_update' => false,
                'existing' => false,
            ],
            'family' => [
                'total' => 0,
                'will_create' => 0,
                'existing' => 0,
                'details' => [],
            ],
            'education' => [
                'total' => 0,
                'will_create' => 0,
                'existing' => 0,
                'details' => [],
            ],
            'work' => [
                'total' => 0,
                'will_create' => 0,
                'existing' => 0,
                'details' => [],
            ],
            'residences' => [
                'total' => 0,
                'will_create' => 0,
                'existing' => 0,
                'details' => [],
            ],
            'relationships' => [
                'total' => 0,
                'will_create' => 0,
                'existing' => 0,
                'details' => [],
            ],
        ];
    }

    public function simulate(string $yamlPath): array
    {
        $this->simulate = true;
        $this->data = Yaml::parseFile($yamlPath);
        
        try {
            DB::beginTransaction();
            
            $this->validateYaml();
            $this->simulateImport();
            
            DB::rollBack();
            return $this->report;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->addError('general', $e->getMessage());
            return $this->report;
        }
    }

    public function import(string $yamlPath): array
    {
        $this->simulate = false;
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

    abstract protected function validateYaml(): void;
    
    abstract protected function simulateImport(): void;
    
    abstract protected function performImport(): void;

    protected function parseDate(?string $dateStr): ?array
    {
        if (!$dateStr) {
            return null;
        }

        $parts = explode('-', $dateStr);
        $result = [];

        if (isset($parts[0])) {
            $result['year'] = (int) $parts[0];
        }
        if (isset($parts[1])) {
            $result['month'] = (int) $parts[1];
        }
        if (isset($parts[2])) {
            $result['day'] = (int) $parts[2];
        }

        return $result;
    }

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
<?php

namespace App\Services\Import;

use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Log;

abstract class SpanImporter
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
                'total' => 0,
                'existing' => 0,
                'created' => 0,
                'details' => []
            ],
            'education' => [
                'total' => 0,
                'existing' => 0,
                'created' => 0,
                'details' => []
            ],
            'work' => [
                'total' => 0,
                'existing' => 0,
                'created' => 0,
                'details' => []
            ],
            'residences' => [
                'total' => 0,
                'existing' => 0,
                'created' => 0,
                'details' => []
            ],
            'relationships' => [
                'total' => 0,
                'existing' => 0,
                'created' => 0,
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

    abstract protected function validateYaml(): void;
    
    abstract protected function performImport(): void;

    /**
     * Parse a date string into a Carbon instance.
     * 
     * @param string|null $dateStr The date string to parse (YYYY-MM-DD, YYYY-MM, or YYYY)
     * @return \Carbon\Carbon|null The parsed date or null if invalid
     */
    protected function parseDate(?string $dateStr): ?\Carbon\Carbon
    {
        if (!$dateStr) {
            return null;
        }

        try {
            // Split the date string
            $parts = explode('-', $dateStr);
            $year = (int)$parts[0];
            $month = isset($parts[1]) ? (int)$parts[1] : 1;
            $day = isset($parts[2]) ? (int)$parts[2] : 1;

            // Create a Carbon instance
            return \Carbon\Carbon::createFromDate($year, $month, $day);
        } catch (\Exception $e) {
            Log::error('Failed to parse date', [
                'date_str' => $dateStr,
                'error' => $e->getMessage()
            ]);
            return null;
        }
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
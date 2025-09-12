<?php

namespace App\Services;

use App\Models\Span;
use App\Models\SpanType;
use App\Models\ConnectionType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SpreadsheetValidationService
{
    /**
     * Validate spreadsheet data for a span
     */
    public function validateSpanData(array $data, ?Span $span = null): array
    {
        $errors = [];
        
        // Get the span type
        $spanType = SpanType::where('type_id', $data['type'])->first();
        if (!$spanType) {
            return ['error' => 'Invalid span type: ' . $data['type']];
        }
        
        // Handle subtype field conversion before validation
        $data = $this->normalizeSpreadsheetData($data);
        
        // Build validation rules
        $rules = $this->buildValidationRules($spanType, $span);
        
        // Create validator
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            $errors = $validator->errors()->all(); // Return flat array of error messages
        }
        
        // Validate main span date ranges
        $dateErrors = $this->validateSpanDates($data);
        if (!empty($dateErrors)) {
            $errors = array_merge($errors, $dateErrors);
        }
        
        // Validate connections if present
        if (!empty($data['connections'])) {
            $connectionErrors = $this->validateConnections($data['connections']);
            if (!empty($connectionErrors)) {
                $errors = array_merge($errors, $connectionErrors);
            }
        }
        
        return $errors;
    }
    
    /**
     * Normalize spreadsheet data to match validation expectations
     */
    private function normalizeSpreadsheetData(array $data): array
    {
        // Handle subtype field - it can be either top-level or in metadata
        if (isset($data['subtype'])) {
            // If subtype is provided as top-level field, add it to metadata for validation
            if (!isset($data['metadata'])) {
                $data['metadata'] = [];
            }
            $data['metadata']['subtype'] = $data['subtype'];
        }
        
        return $data;
    }
    
    /**
     * Validate span date ranges
     */
    private function validateSpanDates(array $data): array
    {
        $errors = [];
        
        // Check if start date is before end date
        if (!empty($data['start_year']) && !empty($data['end_year'])) {
            $startDate = $this->buildDateString($data['start_year'], $data['start_month'], $data['start_day']);
            $endDate = $this->buildDateString($data['end_year'], $data['end_month'], $data['end_day']);
            
            if ($startDate > $endDate) {
                $errors[] = 'Start date must be before end date';
            }
        }
        
        return $errors;
    }
    
    /**
     * Build validation rules for a span
     */
    private function buildValidationRules(SpanType $spanType, ?Span $span = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|string|exists:span_types,type_id',
            'state' => [
                'required',
                Rule::in(['placeholder', 'draft', 'complete'])
            ],
            'access_level' => [
                'required',
                Rule::in(['private', 'shared', 'public'])
            ],
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'start_year' => [
                'required_unless:state,placeholder',
                'nullable',
                'integer',
                'min:1',
                'max:9999'
            ],
            'start_month' => [
                'nullable',
                'integer',
                'between:1,12'
            ],
            'start_day' => [
                'nullable',
                'integer',
                'between:1,31'
            ],
            'end_year' => 'nullable|integer|min:1|max:9999',
            'end_month' => [
                'nullable',
                'integer',
                'between:1,12'
            ],
            'end_day' => [
                'nullable',
                'integer',
                'between:1,31'
            ],
            'metadata' => 'nullable|array',
            'connections' => 'nullable|array'
        ];
        
        // Add slug validation
        if ($span) {
            $rules['slug'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('spans', 'slug')->ignore($span->id)
            ];
        } else {
            $rules['slug'] = 'required|string|max:255|unique:spans,slug';
        }
        
        // Add metadata validation rules from span type
        $metadataRules = $spanType->getValidationRules();
        $rules = array_merge($rules, $metadataRules);
        
        return $rules;
    }
    
    /**
     * Validate connections data
     */
    private function validateConnections(array $connections): array
    {
        $errors = [];
        
        foreach ($connections as $index => $connection) {
            $connectionErrors = $this->validateConnection($connection, $index);
            if (!empty($connectionErrors)) {
                $errors = array_merge($errors, $connectionErrors);
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate a single connection
     */
    private function validateConnection(array $connection, int $index): array
    {
        $rules = [
            'subject' => 'required|string|max:255',
            'predicate' => 'required|string|exists:connection_types,type',
            'object' => 'required|string|max:255',
            'direction' => 'nullable|in:outgoing,incoming', // Make direction optional
            'start_year' => 'nullable|integer|min:1|max:9999',
            'start_month' => 'nullable|integer|between:1,12',
            'start_day' => 'nullable|integer|between:1,31',
            'end_year' => 'nullable|integer|min:1|max:9999',
            'end_month' => 'nullable|integer|between:1,12',
            'end_day' => 'nullable|integer|between:1,31',
            'metadata' => 'nullable|array'
        ];
        
        $validator = Validator::make($connection, $rules);
        
        if ($validator->fails()) {
            return $validator->errors()->all(); // Return flat array of error messages
        }
        
        // Validate date ranges
        $dateErrors = $this->validateConnectionDates($connection);
        if (!empty($dateErrors)) {
            return $dateErrors;
        }
        
        return [];
    }
    
    /**
     * Validate connection date ranges
     */
    private function validateConnectionDates(array $connection): array
    {
        $errors = [];
        
        // Check if start date is before end date
        if (!empty($connection['start_year']) && !empty($connection['end_year'])) {
            $startDate = $this->buildDateString($connection['start_year'], $connection['start_month'], $connection['start_day']);
            $endDate = $this->buildDateString($connection['end_year'], $connection['end_month'], $connection['end_day']);
            
            if ($startDate > $endDate) {
                $errors[] = 'Start date must be before end date';
            }
        }
        
        return $errors;
    }
    
    /**
     * Build a date string for comparison
     */
    private function buildDateString(?int $year, ?int $month, ?int $day): string
    {
        $date = sprintf('%04d', $year);
        if ($month) {
            $date .= sprintf('-%02d', $month);
        } else {
            $date .= '-01';
        }
        if ($day) {
            $date .= sprintf('-%02d', $day);
        } else {
            $date .= '-01';
        }
        return $date;
    }
}

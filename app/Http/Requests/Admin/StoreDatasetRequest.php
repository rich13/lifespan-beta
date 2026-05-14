<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->getEffectiveAdminStatus();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:datasets,slug'],
            'description' => ['nullable', 'string', 'max:10000'],
            'value_label' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:64'],
            'attribution' => ['required', 'string', 'max:10000'],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ];
    }
}

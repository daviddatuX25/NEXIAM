<?php

namespace App\Http\Requests;

use App\Models\KnowledgeDocument;
use App\Services\CsvToNarrativeService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCsvImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', KnowledgeDocument::class) ?? false;
    }

    public function rules(): array
    {
        $maxKb = (int) (CsvToNarrativeService::MAX_FILE_SIZE_BYTES / 1024);

        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:' . $maxKb],
            'title' => ['required', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
            'metadata.category' => ['nullable', 'string', 'max:128'],
            'metadata.year' => ['nullable', 'string', 'max:32'],
            'metadata.description' => ['nullable', 'string', 'max:500'],
            'metadata.tags' => ['nullable', 'array'],
            'metadata.tags.*' => ['string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a CSV file.',
            'file.file' => 'The uploaded file is invalid.',
            'file.mimes' => 'File must be a CSV (.csv or .txt).',
            'file.max' => 'File must not exceed 2MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('metadata') && is_array($this->metadata)) {
            $meta = array_filter($this->metadata, fn ($v) => $v !== null && $v !== '');
            $this->merge(['metadata' => $meta]);
        }
    }
}

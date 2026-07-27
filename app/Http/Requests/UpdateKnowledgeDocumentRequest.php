<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('knowledge_document')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:100000'],
            'metadata' => ['sometimes', 'array'],
            'metadata.category' => ['nullable', 'string', 'max:128'],
            'metadata.year' => ['nullable', 'string', 'max:32'],
            'metadata.description' => ['nullable', 'string', 'max:500'],
            'metadata.tags' => ['nullable', 'array'],
            'metadata.tags.*' => ['string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
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

<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

final class ProgramGovernanceReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'note_id' => ['sometimes', 'integer', 'min:1'],
            'note_page' => ['sometimes', 'integer', 'min:1'],
            'note_search' => ['sometimes', 'string', 'max:120'],
            'publication_page' => ['sometimes', 'integer', 'min:1'],
            'discussion_page' => ['sometimes', 'integer', 'min:1'],
            'discussion_search' => ['sometimes', 'string', 'max:120'],
            'topic' => ['sometimes', 'in:benchmark,quota,advising'],
            'benchmark_set_id' => ['sometimes', 'integer', 'min:1'],
            'set_id' => ['sometimes', 'integer', 'min:1'],
            'revision_id' => ['sometimes', 'integer', 'min:1'],
            'compare' => ['sometimes', 'boolean'],
            'return_to' => ['sometimes', 'string', 'max:2000'],
        ];
    }
}

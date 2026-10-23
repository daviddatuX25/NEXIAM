<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

class RevokeKnowledgeSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

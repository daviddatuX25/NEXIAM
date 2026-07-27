<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'ai_companion_persona' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ai_companion_persona') && is_string($this->ai_companion_persona)) {
            $this->merge(['ai_companion_persona' => strip_tags($this->ai_companion_persona)]);
        }
    }
}

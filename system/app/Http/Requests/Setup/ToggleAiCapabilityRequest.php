<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class ToggleAiCapabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tool_id' => ['required', 'string', 'max:128'],
            'enabled' => ['required', 'boolean'],
            'lock_for_office' => ['sometimes', 'boolean'],
        ];
    }
}

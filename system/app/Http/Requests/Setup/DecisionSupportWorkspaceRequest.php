<?php

namespace App\Http\Requests\Setup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DecisionSupportWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'program_id' => ['sometimes', 'string', 'max:100'],
            'program' => ['sometimes', 'string', 'max:100'],
            'cycle_id' => ['sometimes', 'string', 'max:100'],
            'cycle' => ['sometimes', 'string', 'max:100'],
            'tab' => ['sometimes', 'in:tools,notes,discussion'],
            'tool' => ['sometimes', 'in:benchmarks,profile'],
            'note_id' => ['sometimes', 'integer', 'min:1'],
            'return_to' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}

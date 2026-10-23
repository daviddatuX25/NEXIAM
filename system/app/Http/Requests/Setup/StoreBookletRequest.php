<?php

namespace App\Http\Requests\Setup;

use App\Models\Assessment\OmrQuestionBookletVersion;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBookletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'authoring_mode' => ['nullable', 'string', 'in:stored_questions,answer_keys_only'],
            'is_synthetic' => ['nullable', 'boolean'],
            'code' => ['nullable', 'string', 'regex:/^[A-Z0-9-]{3,32}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('authoring_mode') || empty($this->input('authoring_mode'))) {
            $this->merge([
                'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
            ]);
        }
        if (! $this->has('is_synthetic') || $this->input('is_synthetic') === null) {
            $this->merge([
                'is_synthetic' => false,
            ]);
        }
    }
}

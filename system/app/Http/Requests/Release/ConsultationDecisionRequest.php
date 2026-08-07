<?php

namespace App\Http\Requests\Release;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationDecisionRequest extends FormRequest
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
            'decision_type' => ['required', 'string', 'in:approve,modify,override,defer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }
}

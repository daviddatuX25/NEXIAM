<?php

namespace App\Http\Requests\Consultation;

use Illuminate\Foundation\Http\FormRequest;

class ReassignConsultationRequest extends FormRequest
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
            'target_session_id' => ['required', 'integer', 'exists:consultation_sessions,id'],
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'exists:consultation_assignments,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

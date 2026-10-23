<?php

namespace App\Http\Requests\Consultation;

use Illuminate\Foundation\Http\FormRequest;

class SwitchConsultationSlotRequest extends FormRequest
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
            'consultation_session_id' => ['required', 'integer', 'exists:consultation_sessions,id'],
            'admission_attempt_id' => ['required', 'integer', 'exists:admission_attempts,id'],
            'expected_assignment_id' => ['required', 'integer', 'exists:consultation_assignments,id'],
            'expected_assignment_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ];
    }
}

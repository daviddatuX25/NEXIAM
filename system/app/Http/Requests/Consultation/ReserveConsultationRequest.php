<?php

namespace App\Http\Requests\Consultation;

use Illuminate\Foundation\Http\FormRequest;

class ReserveConsultationRequest extends FormRequest
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
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}

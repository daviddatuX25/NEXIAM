<?php

namespace App\Http\Requests\Consultation;

use Illuminate\Foundation\Http\FormRequest;

class BulkConsultationSessionActionRequest extends FormRequest
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
            'session_ids' => ['required', 'array', 'min:1'],
            'session_ids.*' => ['required', 'integer', 'exists:consultation_sessions,id'],
            'booking_mode' => ['nullable', 'string', 'in:open_for_reservation,staff_only'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

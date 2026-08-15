<?php

namespace App\Http\Requests\Consultation;

use App\Models\Consultation\ConsultationSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsultationSessionRequest extends FormRequest
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
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'code' => ['required', 'string', 'max:64'],
            'scheduled_at' => ['required', 'date'],
            'booking_mode' => ['required', Rule::in(ConsultationSession::BOOKING_MODES)],
            'expected_version' => ['required', 'integer'],
        ];
    }
}

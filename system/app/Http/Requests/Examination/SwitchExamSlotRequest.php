<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class SwitchExamSlotRequest extends FormRequest
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
            'exam_session_id' => ['required', 'integer', 'exists:exam_sessions,id'],
            'admission_attempt_id' => ['required', 'integer', 'exists:admission_attempts,id'],
            'expected_assignment_id' => ['required', 'integer', 'exists:exam_assignments,id'],
            'expected_assignment_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ];
    }
}

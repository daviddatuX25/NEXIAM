<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncOfflineAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_actor_id' => ['required', 'integer', 'min:1'],
            'assignment_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'outcome' => ['required', Rule::in(['present', 'absent', 'void'])],
            'expected_outcome' => ['present', 'nullable', Rule::in(['present', 'absent', 'void'])],
            'expected_attendance_id' => ['present', 'nullable', 'integer', 'min:1'],
            'expected_recorded_at' => ['present', 'nullable', 'date'],
            'expected_started_at' => ['required', 'date'],
        ];
    }
}

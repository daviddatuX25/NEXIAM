<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class SelectOmrPendingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'exam_session_id' => ['nullable', 'integer', 'min:1'],
            'grading_session_id' => ['nullable', 'integer', 'min:1', 'required_without:exam_session_id'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}

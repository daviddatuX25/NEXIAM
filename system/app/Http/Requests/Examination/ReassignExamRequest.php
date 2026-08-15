<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class ReassignExamRequest extends FormRequest
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
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'exists:exam_assignments,id'],
            'target_session_id' => ['required', 'integer', 'exists:exam_sessions,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

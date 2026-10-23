<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveOmrApplicantExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'exam_assignment_id' => ['required', 'integer', 'exists:exam_assignments,id'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ];
    }
}

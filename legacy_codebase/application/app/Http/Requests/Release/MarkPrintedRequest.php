<?php

namespace App\Http\Requests\Release;

use Illuminate\Foundation\Http\FormRequest;

class MarkPrintedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'test_administrator']) ?? false;
    }

    public function rules(): array
    {
        return [
            'applicant_ids' => ['required', 'array', 'min:1', function ($attribute, $value, $fail) {
                $session = $this->route('grading_session');
                $uniqueIds = array_unique($value);
                $validCount = $session->applicants()->whereIn('applicants.id', $uniqueIds)->count();
                if ($validCount !== count($uniqueIds)) {
                    $fail('One or more applicants are not part of this grading session.');
                }
            }],
            'applicant_ids.*' => ['required', 'integer', 'exists:applicants,id'],
            'printed' => ['required', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admission;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\IntakeGradeField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuthorizeAcceptedAmendmentRequest extends FormRequest
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
        /** @var AdmissionAttempt|null $attempt */
        $attempt = $this->route('attempt');

        $rules = [
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
            'identity_first_name' => ['nullable', 'string', 'max:120'],
            'identity_middle_name' => ['nullable', 'string', 'max:120'],
            'identity_last_name' => ['nullable', 'string', 'max:120'],
            'identity_suffix' => ['nullable', 'string', 'max:32'],
            'identity_birthdate' => ['nullable', 'date'],
            'identity_sex' => ['nullable', Rule::in(['male', 'female'])],
            'identity_email' => ['nullable', 'email', 'max:255'],
            'identity_phone' => ['nullable', 'string', 'max:64'],
            'preferred_program_code' => ['nullable', 'string', 'max:32'],
            'program_preference_1_code' => [
                'nullable',
                'string',
                'max:32',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'program_preference_2_code' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1_code',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'program_preference_3_code' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1_code',
                'different:program_preference_2_code',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'applicant_type' => ['nullable', Rule::in(['new', 'transferee'])],
            'last_school_enrolled' => ['nullable', 'string', 'max:255'],
            'strand' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'zip_code' => ['nullable', 'string', 'max:32'],
            'grades' => ['nullable', 'array'],
        ];

        if ($attempt instanceof AdmissionAttempt) {
            foreach (IntakeGradeField::activeForInstitution((int) $attempt->institution_id) as $field) {
                $rules['grades.'.$field->code] = [
                    'nullable',
                    'numeric',
                    'min:'.(float) $field->min_value,
                    'max:'.(float) $field->max_value,
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function amendmentPayload(): array
    {
        $validated = $this->validated();
        $gradesInput = $validated['grades'] ?? [];
        $grades = [];
        if (is_array($gradesInput)) {
            foreach ($gradesInput as $code => $value) {
                if (! is_string($code) || $value === null || $value === '') {
                    continue;
                }
                $grades[$code] = (string) $value;
            }
        }
        $validated['grades'] = $grades;

        if (isset($validated['identity_birthdate'])) {
            $validated['identity_birthdate'] = $this->date('identity_birthdate')?->format('Y-m-d');
        }

        return $validated;
    }
}

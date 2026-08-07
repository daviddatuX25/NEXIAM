<?php

namespace App\Http\Requests\Admission;

use App\Admission\Data\AdmissionIntakeData;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeGradeField;
use App\Models\Foundation\PrivacyPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitAdmissionRequest extends FormRequest
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
        $rules = [
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'application_window_id' => ['required', 'integer', 'exists:application_windows,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'suffix' => ['nullable', 'string', 'max:32'],
            'birthdate' => ['required', 'date', 'before:today'],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'applicant_type' => ['required', Rule::in(['new', 'transferee'])],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'last_school_enrolled' => ['nullable', 'string', 'max:255'],
            'strand' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'zip_code' => ['nullable', 'string', 'max:32'],
            'program_preference_1' => [
                'required',
                'string',
                'max:32',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'program_preference_2' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'program_preference_3' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1',
                'different:program_preference_2',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'terms_accepted' => ['accepted'],
            'grades' => ['nullable', 'array'],
        ];

        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        if ($institution !== null) {
            foreach (IntakeGradeField::activeForInstitution((int) $institution->id) as $field) {
                $key = 'grades.'.$field->code;
                $rules[$key] = [
                    $field->is_required ? 'required' : 'nullable',
                    'numeric',
                    'min:'.(float) $field->min_value,
                    'max:'.(float) $field->max_value,
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms_accepted.accepted' => 'You must read and agree to the Privacy Policy to submit an application.',
            'program_preference_1.required' => 'Select your first program preference.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Keep room for open-window checks without inventing closed-window bypass.
        });
    }

    public function toIntakeData(): AdmissionIntakeData
    {
        $prefs = array_values(array_filter([
            $this->string('program_preference_1')->toString(),
            $this->input('program_preference_2'),
            $this->input('program_preference_3'),
        ], fn ($v) => is_string($v) && $v !== ''));

        $policy = PrivacyPolicy::active();
        $gradesInput = $this->input('grades', []);
        $grades = [];
        if (is_array($gradesInput)) {
            foreach ($gradesInput as $code => $value) {
                if (! is_string($code) || $value === null || $value === '') {
                    continue;
                }
                $grades[$code] = (string) $value;
            }
        }

        return new AdmissionIntakeData(
            firstName: $this->string('first_name')->toString(),
            lastName: $this->string('last_name')->toString(),
            birthdate: $this->string('birthdate')->toString(),
            sex: $this->string('sex')->toString(),
            contactEmail: $this->string('email')->toString(),
            applicantType: $this->string('applicant_type')->toString(),
            programPreferenceCodes: $prefs,
            grades: $grades,
            middleName: $this->input('middle_name'),
            suffix: $this->input('suffix'),
            contactPhone: $this->input('phone'),
            lastSchoolEnrolled: $this->input('last_school_enrolled'),
            strand: $this->input('strand'),
            addressLine: $this->input('address_line'),
            city: $this->input('city'),
            province: $this->input('province'),
            zipCode: $this->input('zip_code'),
            termsAccepted: $this->boolean('terms_accepted'),
            privacyPolicyId: $policy?->id,
        );
    }
}

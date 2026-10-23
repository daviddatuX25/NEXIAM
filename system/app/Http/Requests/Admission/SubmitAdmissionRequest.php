<?php

namespace App\Http\Requests\Admission;

use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\GwaScaleNormalizer;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationSuggestionList;
use App\Models\Foundation\Institution;
use App\Setup\Services\IntakeFieldPolicyRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'public_policy_id' => [
                'bail', 'required', 'integer', 'min:1',
                Rule::exists('public_policies', 'id')->where(fn ($query) => $query->where('institution_id', (int) Institution::current()->id)),
            ],
            'public_policy_revision_id' => ['bail', 'required', 'integer', 'min:1'],
            'public_policy_revision_digest' => ['bail', 'required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
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
            'strand' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '' || $this->input('applicant_type') === 'transferee') {
                        return;
                    }
                    $validStrands = ['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL-ICT', 'TVL-HE', 'TVL-IA', 'TVL-AFA', 'SPORTS', 'ARTS'];
                    $institution = Institution::current();
                    if ($institution !== null) {
                        $list = ApplicationSuggestionList::query()
                            ->where('institution_id', $institution->id)
                            ->where('category', 'shs')
                            ->first();
                        if ($list && is_array($list->entries)) {
                            foreach ($list->entries as $entry) {
                                if (isset($entry['aliases']) && is_array($entry['aliases'])) {
                                    foreach ($entry['aliases'] as $alias) {
                                        $validStrands[] = strtoupper(trim((string) $alias));
                                    }
                                }
                                if (isset($entry['label'])) {
                                    $validStrands[] = strtoupper(trim((string) $entry['label']));
                                }
                            }
                        }
                    }
                    if (! in_array(strtoupper(trim((string) $value)), array_unique($validStrands), true)) {
                        $fail('Select a valid senior high school strand from the list.');
                    }
                },
            ],
            'gwa_scale' => ['nullable', 'string', Rule::in(GwaScaleNormalizer::allowedScales())],
            'gwa' => [
                'nullable',
                'numeric',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $scale = (string) ($this->input('gwa_scale') ?: GwaScaleNormalizer::SCALE_PERCENTAGE_100);
                    if (! GwaScaleNormalizer::isValidValueForScale((float) $value, $scale)) {
                        $fail('Enter a valid GWA for the selected grading scale ('.GwaScaleNormalizer::scaleLabel($scale).').');
                    }
                },
            ],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'zip_code' => ['nullable', 'string', 'max:32'],
            'program_preference_1' => [
                'required',
                'string',
                'max:32',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)->where('is_admission_offered', true)),
            ],
            'program_preference_2' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)->where('is_admission_offered', true)),
            ],
            'program_preference_3' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1',
                'different:program_preference_2',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)->where('is_admission_offered', true)),
            ],
            'terms_accepted' => ['accepted'],
        ];

        $institution = Institution::query()->orderBy('id')->first();
        $cycleId = $this->input('academic_cycle_id');
        if ($institution !== null && $cycleId) {
            $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->find($cycleId);
            if ($cycle !== null) {
                $fieldRules = app(IntakeFieldPolicyRegistry::class)->compiledRulesForCycle($institution, $cycle);

                foreach ($fieldRules as $fieldKey => $requirementLevel) {
                    $inputKey = $this->inputKeyForPolicy($fieldKey);
                    if (! isset($rules[$inputKey])) {
                        continue;
                    }

                    if (! $requirementLevel->isEnabled()) {
                        $rules[$inputKey] = ['prohibited'];

                        continue;
                    }

                    $rules[$inputKey][0] = $requirementLevel->isRequired() ? 'required' : 'nullable';
                }
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

    public function toIntakeData(): AdmissionIntakeData
    {
        $prefs = array_values(array_filter([
            $this->string('program_preference_1')->toString(),
            $this->input('program_preference_2'),
            $this->input('program_preference_3'),
        ], fn ($v) => is_string($v) && $v !== ''));

        return new AdmissionIntakeData(
            firstName: $this->string('first_name')->toString(),
            lastName: $this->string('last_name')->toString(),
            birthdate: $this->string('birthdate')->toString(),
            sex: $this->string('sex')->toString(),
            contactEmail: $this->string('email')->toString(),
            applicantType: $this->string('applicant_type')->toString() ?: 'new',
            programPreferenceCodes: $prefs,
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
            publicPolicyId: $this->integer('public_policy_id'),
            publicPolicyRevisionId: $this->integer('public_policy_revision_id'),
            publicPolicyRevisionDigest: $this->string('public_policy_revision_digest')->toString(),
            gwa: $this->filled('gwa') ? (float) $this->input('gwa') : null,
            gwaScale: $this->filled('gwa_scale') ? $this->string('gwa_scale')->toString() : null,
        );
    }

    private function inputKeyForPolicy(string $fieldKey): string
    {
        return match ($fieldKey) {
            'contact_email' => 'email',
            'contact_phone' => 'phone',
            'course_preference_1' => 'program_preference_1',
            'course_preference_2' => 'program_preference_2',
            'course_preference_3' => 'program_preference_3',
            default => $fieldKey,
        };
    }
}

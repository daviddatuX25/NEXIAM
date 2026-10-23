<?php

declare(strict_types=1);

namespace App\Http\Requests\Admission;

use App\Models\Foundation\Institution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAdminAdmissionRequest extends FormRequest
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
        $institution = Institution::current();
        $institutionId = (int) ($institution?->id ?? 0);

        $rules = [
            'first_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'suffix' => ['nullable', 'string', 'max:32'],
            'birthdate' => ['required', 'date', 'before:today'],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'applicant_type' => ['required', Rule::in(['new', 'transferee'])],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'last_school_enrolled' => ['nullable', 'string', 'max:255'],
            'strand' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'zip_code' => ['nullable', 'string', 'max:32'],
            'program_preference_1_code' => [
                'required',
                'string',
                'max:32',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)->where('institution_id', $institutionId)),
            ],
            'program_preference_2_code' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1_code',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)->where('institution_id', $institutionId)),
            ],
            'program_preference_3_code' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1_code',
                'different:program_preference_2_code',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)->where('institution_id', $institutionId)),
            ],
            'status' => ['nullable', Rule::in(['pending', 'accepted'])],
            'complete_documents' => ['sometimes', 'boolean'],
            'verified_requirement_ids' => ['sometimes', 'array'],
            'verified_requirement_ids.*' => [
                'integer',
                Rule::exists('intake_requirements', 'id')->where(
                    fn ($q) => $q->where('institution_id', $institutionId)->where('is_active', true),
                ),
            ],
            'correction_notes' => ['nullable', 'string', 'max:2000'],
        ];

        return $rules;
    }
}

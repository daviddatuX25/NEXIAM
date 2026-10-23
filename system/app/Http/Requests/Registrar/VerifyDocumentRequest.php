<?php

namespace App\Http\Requests\Registrar;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attempt = $this->route('attempt');
        $actor = $this->user();

        return $attempt instanceof AdmissionAttempt
            && $actor instanceof User
            && app(AttemptRequirementChecklistBoundary::class)->allows(
                $actor,
                $attempt,
                CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY,
            );
    }

    protected function prepareForValidation(): void
    {
        $boundary = app(AttemptRequirementChecklistBoundary::class);
        $normalized = [];

        foreach (['applicant_instruction', 'internal_remarks'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $normalized[$field] = $boundary->normalizeText($value);
            }
        }

        $this->merge($normalized);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:0'],
            'requirement_set_id' => ['nullable', 'integer', 'min:1'],
            'requirement_item_id' => ['nullable', 'integer', 'min:1', 'required_without:intake_requirement_id'],
            'intake_requirement_id' => ['nullable', 'integer', 'min:1', 'required_without:requirement_item_id'],
            'expected_latest_verification_id' => ['present', 'nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['verified', 'deficient', 'missing'])],
            'applicant_instruction' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn (): bool => $this->input('status') === 'deficient'),
            ],
            'internal_remarks' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }
}

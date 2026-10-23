<?php

namespace App\Http\Requests\Registrar;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RecordRequirementInternalNoteRequest extends FormRequest
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
        $value = $this->input('internal_remarks');
        if (is_string($value)) {
            $this->merge(['internal_remarks' => app(AttemptRequirementChecklistBoundary::class)->normalizeText($value)]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'requirement_set_id' => ['nullable', 'integer', 'min:1'],
            'requirement_item_id' => ['nullable', 'integer', 'min:1'],
            'intake_requirement_id' => ['nullable', 'integer', 'min:1'],
            'expected_latest_verification_id' => ['present', 'nullable', 'integer', 'min:1'],
            'expected_latest_note_id' => ['present', 'nullable', 'integer', 'min:1'],
            'internal_remarks' => ['present', 'nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'status' => ['prohibited'],
            'applicant_instruction' => ['prohibited'],
            'recipient' => ['prohibited'],
        ];
    }
}

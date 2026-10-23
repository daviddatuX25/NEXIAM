<?php

namespace App\Http\Requests\Registrar;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class BulkVerifyDocumentsRequest extends FormRequest
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

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:0'],
            'requirement_set_id' => ['nullable', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.requirement_item_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.intake_requirement_id'],
            'items.*.intake_requirement_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.requirement_item_id'],
            'items.*.expected_latest_verification_id' => ['present', 'nullable', 'integer', 'min:1'],
            'items.*.internal_remarks' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }
}

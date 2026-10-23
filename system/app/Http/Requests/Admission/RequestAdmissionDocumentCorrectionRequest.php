<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

class RequestAdmissionDocumentCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:0'],
            'requirement_set_id' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:requirement_item_id,expected_latest_verification_id,applicant_instruction'],
            'items.*.requirement_item_id' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'items.*.expected_latest_verification_id' => ['present', 'nullable', 'integer', 'min:1'],
            'items.*.applicant_instruction' => ['required', 'string', 'max:2000'],
            'overall_note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'recipient' => ['prohibited'],
            'recipients' => ['prohibited'],
            'recipient_id' => ['prohibited'],
            'recipient_identity_id' => ['prohibited'],
            'channels' => ['prohibited'],
            'channel' => ['prohibited'],
            'status' => ['prohibited'],
            'internal_remarks' => ['prohibited'],
            'attempt_id' => ['prohibited'],
            'institution_id' => ['prohibited'],
            'academic_cycle_id' => ['prohibited'],
            'practice_fixture_set_id' => ['prohibited'],
            'applicant_identity_id' => ['prohibited'],
            'actor_id' => ['prohibited'],
            'requested_by_user_id' => ['prohibited'],
        ];
    }
}

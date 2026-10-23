<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OmrWorkbenchCaptureActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['next_page', 'next_set', 'redo_last', 'capture_retake'])],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'authorization_id' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'accepted_operation_id' => ['required_unless:action,capture_retake', 'prohibited_if:action,capture_retake', 'uuid'],
            'accepted_target_key' => ['required_unless:action,capture_retake', 'prohibited_if:action,capture_retake', 'string', 'max:512'],
            'idempotency_key' => ['required', 'string', 'same:authorization_id'],
            'replacement_reason' => ['required_if:action,redo_last,capture_retake', 'prohibited_unless:action,redo_last,capture_retake', 'nullable', 'string', 'min:1', 'max:500'],
            'expected_capture_version' => ['required_if:action,capture_retake', 'prohibited_unless:action,capture_retake', 'integer', 'min:0'],
            'source_page_evidence_id' => ['required_if:action,capture_retake', 'prohibited_unless:action,capture_retake', 'integer', 'min:1'],
            'capture_id' => ['required_if:action,capture_retake', 'prohibited_unless:action,capture_retake', 'integer', 'min:1'],
            'page_no' => ['required_if:action,capture_retake', 'prohibited_unless:action,capture_retake', 'integer', 'between:1,4'],
            'pending_capture_id' => ['required_if:action,capture_retake', 'prohibited_unless:action,capture_retake', 'uuid'],
            'pending_capture_revision' => ['required_if:action,capture_retake', 'prohibited_unless:action,capture_retake', 'integer', 'min:1'],
            'target' => ['required_if:action,capture_retake', 'prohibited_unless:action,capture_retake', 'array:workbench_id,client_capture_id,page_no,target_revision,instance_id,sheet_id,target_key'],
            'target.workbench_id' => ['required_with:target', 'string'],
            'target.client_capture_id' => ['required_with:target', 'uuid'],
            'target.page_no' => ['required_with:target', 'integer', 'between:1,4'],
            'target.target_revision' => ['required_with:target', 'integer', 'min:1'],
            'target.instance_id' => ['present_with:target', 'nullable', 'string'],
            'target.sheet_id' => ['present_with:target', 'nullable', 'integer', 'min:1'],
            'target.target_key' => ['required_with:target', 'string', 'max:512'],
            'workbench_id' => ['prohibited'],
            'target_key' => ['prohibited'],
            'instance_id' => ['prohibited'],
            'client_capture_id' => ['prohibited'],
            'sheet_id' => ['prohibited'],
            'template_version_id' => ['prohibited'],
            'run_id' => ['prohibited'],
            'batch_id' => ['prohibited'],
            'actor_id' => ['prohibited'],
        ];
    }
}

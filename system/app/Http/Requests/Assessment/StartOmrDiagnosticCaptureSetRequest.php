<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class StartOmrDiagnosticCaptureSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'template_version_id' => ['prohibited'],
            'template_id' => ['prohibited'],
            'booklet_version_id' => ['prohibited'],
            'page_no' => ['prohibited'],
            'active_page_no' => ['prohibited'],
            'page_count' => ['prohibited'],
            'pages' => ['prohibited'],
            'expected_pages' => ['prohibited'],
            'registration_mode' => ['prohibited'],
            'qr_mode' => ['prohibited'],
            'copy_policy' => ['prohibited'],
            'context' => ['prohibited'],
            'pending_capture_id' => ['prohibited'],
            'pending_capture_revision' => ['prohibited'],
            'institution_id' => ['prohibited'],
            'academic_cycle_id' => ['prohibited'],
            'execution_kind' => ['prohibited'],
            'kind' => ['prohibited'],
            'mode' => ['prohibited'],
            'run_id' => ['prohibited'],
            'workbench_id' => ['prohibited'],
            'session_id' => ['prohibited'],
            'actor_id' => ['prohibited'],
            'declared_set_code' => ['prohibited'],
            'answer_key' => ['prohibited'],
            'expected_vector' => ['prohibited'],
        ];
    }
}

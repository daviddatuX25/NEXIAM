<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class StartOmrProductionCaptureSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'issuance_id' => ['required_without:supplier_stock_allocation_id', 'prohibits:supplier_stock_allocation_id', 'nullable', 'integer', 'min:1'],
            'supplier_stock_allocation_id' => ['required_without:issuance_id', 'prohibits:issuance_id', 'nullable', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'template_id' => ['prohibited'],
            'template' => ['prohibited'],
            'template_version_id' => ['prohibited'],
            'version_id' => ['prohibited'],
            'booklet_id' => ['prohibited'],
            'booklet_version' => ['prohibited'],
            'booklet_version_id' => ['prohibited'],
            'registration_mode' => ['prohibited'],
            'qr_mode' => ['prohibited'],
            'copy_policy' => ['prohibited'],
            'expected_pages' => ['prohibited'],
            'pages' => ['prohibited'],
            'page_count' => ['prohibited'],
            'page_no' => ['prohibited'],
            'active_page_no' => ['prohibited'],
            'revision' => ['prohibited'],
            'expected_revision' => ['prohibited'],
            'pending_capture_id' => ['prohibited'],
            'pending_capture_revision' => ['prohibited'],
            'issuance_code' => ['prohibited'],
            'qr_disabled_issuance_id' => ['prohibited'],
            'expected_version' => ['prohibited'],
            'expected_parent_version' => ['prohibited'],
            'expected_run_revision' => ['prohibited'],
            'workbench_id' => ['prohibited'],
            'execution_kind' => ['prohibited'],
            'kind' => ['prohibited'],
            'mode' => ['prohibited'],
            'institution_id' => ['prohibited'],
            'academic_cycle_id' => ['prohibited'],
            'session_id' => ['prohibited'],
            'grading_session_id' => ['prohibited'],
            'route_session_id' => ['prohibited'],
            'batch_id' => ['prohibited'],
            'run_id' => ['prohibited'],
            'actor_id' => ['prohibited'],
            'route_context' => ['prohibited'],
            'context' => ['prohibited'],
            'session' => ['prohibited'],
            'run' => ['prohibited'],
        ];
    }
}

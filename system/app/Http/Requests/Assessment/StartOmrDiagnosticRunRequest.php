<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class StartOmrDiagnosticRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('diagnostic_purpose')) {
            $purpose = $this->filled('acceptance_plan_id')
                ? 'kit_acceptance'
                : ($this->filled('test_kit_version_id') ? 'mock_rehearsal' : 'material_diagnostic');
            $this->merge(['diagnostic_purpose' => $purpose]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'diagnostic_purpose' => ['required', 'string', 'in:material_diagnostic,kit_acceptance,mock_rehearsal'],
            'template_version_id' => ['required_without:test_kit_version_id', 'nullable', 'integer', 'min:1'],
            'booklet_version_id' => ['required_without:test_kit_version_id', 'nullable', 'integer', 'min:1'],
            'declared_set_code' => ['required_without:test_kit_version_id', 'nullable', 'string', 'in:A,B,C,D'],
            'test_kit_version_id' => ['required_if:diagnostic_purpose,kit_acceptance,mock_rehearsal', 'sometimes', 'nullable', 'integer', 'min:1'],
            'acceptance_plan_id' => ['required_if:diagnostic_purpose,kit_acceptance', 'prohibited_unless:diagnostic_purpose,kit_acceptance', 'integer', 'min:1'],
            'expected_revision' => ['required_if:diagnostic_purpose,kit_acceptance,mock_rehearsal', 'integer', 'min:0'],
            'instance_count' => ['required_if:diagnostic_purpose,mock_rehearsal', 'prohibited_unless:diagnostic_purpose,mock_rehearsal', 'integer', 'in:1,2,3'],
            'pattern_mode' => ['required_if:diagnostic_purpose,mock_rehearsal', 'prohibited_unless:diagnostic_purpose,mock_rehearsal', 'string', 'in:guided_mix,perfect_set_a,blank_baseline'],
            'expected_mock_manifest_sha256' => ['required_if:diagnostic_purpose,mock_rehearsal', 'prohibited_unless:diagnostic_purpose,mock_rehearsal', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'resume_run_public_id' => ['sometimes', 'prohibited_unless:diagnostic_purpose,mock_rehearsal', 'uuid'],
            'idempotency_key' => ['required_if:diagnostic_purpose,mock_rehearsal', 'sometimes', 'nullable', 'string', 'max:255'],
            'institution_id' => ['prohibited'],
            'academic_cycle_id' => ['prohibited'],
            'execution_kind' => ['prohibited'],
            'kind' => ['prohibited'],
            'mode' => ['prohibited'],
            'run_id' => ['prohibited'],
            'workbench_id' => ['prohibited'],
            'session_id' => ['prohibited'],
            'answer_key' => ['prohibited'],
            'expected_vector' => ['prohibited'],
        ];
    }
}

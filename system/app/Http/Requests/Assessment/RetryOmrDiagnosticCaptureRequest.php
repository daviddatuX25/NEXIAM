<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class RetryOmrDiagnosticCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'capture_id' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
            'client_capture_id' => ['sometimes', 'nullable', 'uuid'],
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

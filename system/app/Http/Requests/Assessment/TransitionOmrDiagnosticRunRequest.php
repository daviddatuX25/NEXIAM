<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionOmrDiagnosticRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
            'mode' => ['prohibited'],
            'session_id' => ['prohibited'],
            'key' => ['prohibited'],
            'expected_vector' => ['prohibited'],
            'execution_kind' => ['prohibited'],
            'kind' => ['prohibited'],
            'run_id' => ['prohibited'],
            'workbench_id' => ['prohibited'],
            'context' => ['prohibited'],
            'institution_id' => ['prohibited'],
            'academic_cycle_id' => ['prohibited'],
        ];
    }
}

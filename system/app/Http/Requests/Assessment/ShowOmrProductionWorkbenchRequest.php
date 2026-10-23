<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class ShowOmrProductionWorkbenchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'session_id' => ['prohibited'],
            'capture_sheet_id' => ['sometimes', 'integer', 'min:1'],
            'capture_operation_id' => ['sometimes', 'uuid', 'required_with:capture_idempotency_key'],
            'capture_idempotency_key' => ['sometimes', 'string', 'min:1', 'max:255', 'required_with:capture_operation_id'],
            'grading_session_id' => ['prohibited'],
            'route_session_id' => ['prohibited'],
            'batch_id' => ['prohibited'],
            'run_id' => ['prohibited'],
            'workbench_id' => ['prohibited'],
            'execution_kind' => ['prohibited'],
            'kind' => ['prohibited'],
            'mode' => ['prohibited'],
            'context' => ['prohibited'],
            'route_context' => ['prohibited'],
            'session' => ['prohibited'],
            'run' => ['prohibited'],
            'actor_id' => ['prohibited'],
            'workbench' => ['prohibited'],
        ];
    }
}

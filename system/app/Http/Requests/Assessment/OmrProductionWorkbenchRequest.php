<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

abstract class OmrProductionWorkbenchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function workbenchRules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:1'],
            'expected_version' => ['prohibited'],
            'expected_parent_version' => ['prohibited'],
            'expected_run_revision' => ['prohibited'],
            'workbench_id' => ['prohibited'],
            'execution_kind' => ['prohibited'],
            'kind' => ['prohibited'],
            'mode' => ['prohibited'],
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
            'capture_sheet_id' => ['prohibited'],
        ];
    }
}

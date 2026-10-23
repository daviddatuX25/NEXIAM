<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class ShowOmrDiagnosticWorkbenchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'capture_operation_id' => ['sometimes', 'uuid', 'required_with:capture_idempotency_key'],
            'capture_idempotency_key' => ['sometimes', 'string', 'min:1', 'max:255', 'required_with:capture_operation_id'],
            'capture_page_no' => ['sometimes', 'integer', 'between:1,4'],
            'workbench_id' => ['prohibited'],
            'run_id' => ['prohibited'],
            'actor_id' => ['prohibited'],
            'context' => ['prohibited'],
        ];
    }
}

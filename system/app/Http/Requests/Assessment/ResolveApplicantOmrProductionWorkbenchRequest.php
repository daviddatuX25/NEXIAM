<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

final class ResolveApplicantOmrProductionWorkbenchRequest extends OmrProductionWorkbenchRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->workbenchRules(),
            'sheet_id' => ['sometimes', 'integer', 'min:1'],
            'exam_assignment_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}

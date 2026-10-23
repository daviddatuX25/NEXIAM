<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

final class ResolveDuplicateOmrProductionWorkbenchRequest extends OmrProductionWorkbenchRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->workbenchRules(),
            'sheet_id' => ['sometimes', 'integer', 'min:1'],
            'survivor_sheet_id' => ['sometimes', 'integer', 'min:1'],
            'rejected_sheet_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
            'survivor_version' => ['required', 'integer', 'min:0'],
            'rejected_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Validation\Rule;

final class ReviewOmrProductionWorkbenchRequest extends OmrProductionWorkbenchRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->workbenchRules(),
            'sheet_id' => ['sometimes', 'integer', 'min:1'],
            'action' => ['required', 'string', Rule::in(['confirm', 'abstain'])],
            'abstain_reason' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

final class RetryOmrProductionWorkbenchRequest extends OmrProductionWorkbenchRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->workbenchRules(),
            'sheet_id' => ['sometimes', 'integer', 'min:1'],
            'page_no' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}

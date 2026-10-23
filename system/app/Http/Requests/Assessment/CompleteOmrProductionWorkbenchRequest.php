<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

final class CompleteOmrProductionWorkbenchRequest extends OmrProductionWorkbenchRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->workbenchRules(),
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}

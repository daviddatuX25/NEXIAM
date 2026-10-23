<?php

declare(strict_types=1);

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawPublicPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
            'kind' => ['prohibited'],
            'is_designated_intake' => ['prohibited'],
        ];
    }
}

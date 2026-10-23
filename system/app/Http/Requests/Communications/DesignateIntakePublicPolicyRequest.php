<?php

declare(strict_types=1);

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

final class DesignateIntakePublicPolicyRequest extends FormRequest
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
            'expected_intake_policy_id' => ['present', 'nullable', 'integer', 'min:1'],
            'kind' => ['prohibited'],
            'is_designated_intake' => ['prohibited'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

class SavePublicPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->route('policy') === null;

        return [
            'publisher_key' => [$creating ? 'required' : 'prohibited', 'string', 'max:100'],
            'program_id' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:1'],
            'kind' => ['sometimes', 'required', 'in:policy,privacy'],
            'is_designated_intake' => ['prohibited'],
            'request_id' => ['required', 'uuid'],
            'expected_revision' => [$creating ? 'prohibited' : 'required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'min:1', 'max:160'],
            'content' => ['required', 'string', 'min:1', 'max:10000'],
        ];
    }
}

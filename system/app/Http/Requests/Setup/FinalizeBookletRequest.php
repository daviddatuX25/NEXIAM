<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

final class FinalizeBookletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}

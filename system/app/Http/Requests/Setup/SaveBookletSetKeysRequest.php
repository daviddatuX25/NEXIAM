<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

final class SaveBookletSetKeysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array'],
            'items.*' => ['array:booklet_item_uid,booklet_section_uid,global_position,selected_token'],
            'items.*.booklet_item_uid' => ['required', 'uuid', 'distinct:strict'],
            'items.*.booklet_section_uid' => ['required', 'uuid'],
            'items.*.global_position' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'items.*.selected_token' => ['sometimes', 'nullable', 'string', 'max:32'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'complete' => ['sometimes', 'boolean'],
        ];
    }
}

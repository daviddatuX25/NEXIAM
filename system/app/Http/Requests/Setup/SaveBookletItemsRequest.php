<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

final class SaveBookletItemsRequest extends FormRequest
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
            'items.*' => ['array:id,booklet_item_uid,booklet_section_uid,section_name,section_display_order,section_expected_count,option_tokens,key_required,authored_position,correct_option_code,content'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.booklet_item_uid' => ['nullable', 'uuid'],
            'items.*.booklet_section_uid' => ['required', 'uuid'],
            'items.*.section_name' => ['required', 'string', 'max:120'],
            'items.*.section_display_order' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.section_expected_count' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.option_tokens' => ['required', 'array', 'min:1'],
            'items.*.option_tokens.*' => ['required', 'string', 'max:32', 'distinct:strict'],
            'items.*.key_required' => ['required', 'boolean'],
            'items.*.authored_position' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.correct_option_code' => ['nullable', 'string', 'max:32'],
            'items.*.content' => ['nullable', 'array:prompt,choices'],
            'items.*.content.prompt' => ['nullable', 'string', 'max:10000'],
            'items.*.content.choices' => ['nullable', 'array'],
            'items.*.content.choices.*' => ['nullable', 'string', 'max:2000'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'complete' => ['sometimes', 'boolean'],
        ];
    }
}

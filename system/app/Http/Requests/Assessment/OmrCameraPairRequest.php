<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class OmrCameraPairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workbench_id' => ['required', 'string', 'max:180', 'regex:/^(production|diagnostic):[A-Za-z0-9._:-]+$/'],
            'takeover' => ['sometimes', 'boolean'],
            'expected_controller_generation' => ['required_if:takeover,true', 'integer', 'min:1'],
            'capture_page_no' => ['nullable', 'integer', 'between:1,4'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class OmrCameraNodeRejoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmed' => ['required', 'accepted'],
            'capture_page_no' => ['sometimes', 'integer', 'between:1,4'],
        ];
    }
}

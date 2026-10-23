<?php

declare(strict_types=1);

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

final class StageProgramPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'photo' => [
                'required',
                'file',
                'mimes:jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=4096,max_height=4096',
            ],
        ];
    }
}

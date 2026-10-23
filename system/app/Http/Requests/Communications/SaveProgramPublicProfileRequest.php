<?php

declare(strict_types=1);

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

final class SaveProgramPublicProfileRequest extends FormRequest
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
            'expected_revision' => ['required', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string', 'max:240'],
            'description' => ['required', 'string', 'min:1', 'max:10000'],
            'photos' => ['present', 'array', 'max:6'],
            'photos.*' => ['required', 'array:id,alt_text,caption'],
            'photos.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'photos.*.alt_text' => ['required', 'string', 'min:1', 'max:160'],
            'photos.*.caption' => ['nullable', 'string', 'max:240'],
        ];
    }
}

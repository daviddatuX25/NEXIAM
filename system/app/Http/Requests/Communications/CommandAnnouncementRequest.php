<?php

declare(strict_types=1);

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

class CommandAnnouncementRequest extends FormRequest
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
            'expected_revision' => ['required', 'integer', 'min:1'],
        ];
    }
}

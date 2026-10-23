<?php

declare(strict_types=1);

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'publisher_key' => [$creating ? 'required' : 'prohibited', 'string', 'max:100'],
            'program_id' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:1', Rule::requiredIf($creating && str_starts_with((string) $this->input('publisher_key', ''), 'program:'))],
            'request_id' => ['required', 'uuid'],
            'expected_revision' => [$creating ? 'prohibited' : 'required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'min:1', 'max:80'],
            'summary' => ['required', 'string', 'min:1', 'max:160'],
            'body' => ['required', 'string', 'min:1', 'max:10000'],
            'priority' => ['required', Rule::in(['normal', 'urgent'])],
            'is_featured' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ];
    }
}

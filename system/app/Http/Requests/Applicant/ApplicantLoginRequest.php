<?php

namespace App\Http\Requests\Applicant;

use App\Admission\Support\ApplicantAccessKey;
use Illuminate\Foundation\Http\FormRequest;

class ApplicantLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'access_key' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! ApplicantAccessKey::isValidShape($value)) {
                        $fail('Enter a valid 8-character access key (format ABCD-EFGH).');
                    }
                },
            ],
            'birthdate' => ['required', 'date', 'before:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('access_key')) {
            $this->merge([
                'access_key' => ApplicantAccessKey::format((string) $this->input('access_key')),
            ]);
        }
    }
}

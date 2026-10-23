<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeToApplicationOpeningRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => trim($email)]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'consent' => ['required', 'accepted'],
        ];
    }
}

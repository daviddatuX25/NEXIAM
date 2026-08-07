<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class AiCompanionChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('applicant') !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}

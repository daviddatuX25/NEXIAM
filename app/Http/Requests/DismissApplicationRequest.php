<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DismissApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('dismiss', $this->route('application'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

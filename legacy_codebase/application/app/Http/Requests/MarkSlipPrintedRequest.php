<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkSlipPrintedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'registrar_administrator']) ?? false;
    }

    public function rules(): array
    {
        return [
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['required', 'integer', 'exists:applications,id'],
            'printed' => ['required', 'boolean'],
        ];
    }
}

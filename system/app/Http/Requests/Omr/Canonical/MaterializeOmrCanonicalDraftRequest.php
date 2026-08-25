<?php

namespace App\Http\Requests\Omr\Canonical;

use Illuminate\Foundation\Http\FormRequest;

class MaterializeOmrCanonicalDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}

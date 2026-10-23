<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmOmrSourcePagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'pages' => ['required', 'array', 'min:1', 'max:4'],
            'pages.*.page_no' => ['required', 'integer', 'min:1', 'max:4', 'distinct:strict'],
            'pages.*.decision' => ['required', 'string', Rule::in(['confirmed', 'rejected'])],
            'pages.*.reason' => ['nullable', 'string', Rule::in(['wrong_page', 'duplicate_page', 'upside_down', 'illegible'])],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}

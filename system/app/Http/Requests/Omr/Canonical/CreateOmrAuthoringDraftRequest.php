<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOmrAuthoringDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows(
            $this->user(),
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
    }
    protected function prepareForValidation(): void
    {
        $raw = (string) $this->input('code', '');
        $sanitized = strtoupper(trim($raw));
        $sanitized = preg_replace('/\s+/', '-', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/[^A-Z0-9._-]/', '', $sanitized) ?? $sanitized;

        $this->merge([
            'code' => $sanitized,
            'name' => trim((string) $this->input('name', '')),
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9._-]{2,63}$/'],
            'name' => ['required', 'string', 'max:190'],
            'source_kind' => ['required', 'string', Rule::in(OmrTemplateAuthoringDraft::SOURCE_KINDS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'The template code must be 3–64 characters using uppercase letters, numbers, hyphens, underscores, or dots (e.g. APTITUDE-2026-A).',
            'code.min' => 'The template code must be at least 3 characters.',
            'code.required' => 'A template code is required.',
        ];
    }
}

<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
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
            'registration_mode' => trim((string) $this->input('registration_mode', '')),
            'qr_mode' => trim((string) $this->input('qr_mode', '')),
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9._-]{2,63}$/'],
            'name' => ['required', 'string', 'max:190'],
            'registration_mode' => ['required', Rule::enum(OmrRegistrationMode::class)],
            'qr_mode' => ['required', Rule::enum(OmrQrMode::class)],
            'requested_page_count' => ['required', 'integer', 'between:1,4'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'The template code must be 3–64 characters using uppercase letters, numbers, hyphens, underscores, or dots (e.g. APTITUDE-2026-A).',
            'code.min' => 'The template code must be at least 3 characters.',
            'code.required' => 'A template code is required.',
            'registration_mode.required' => 'Choose how the scanner will align this answer sheet.',
            'qr_mode.required' => 'Choose whether production sheets include a signed QR.',
            'requested_page_count.required' => 'Choose how many pages this answer sheet has.',
            'requested_page_count.between' => 'Answer sheets can contain one to four pages.',
        ];
    }
}

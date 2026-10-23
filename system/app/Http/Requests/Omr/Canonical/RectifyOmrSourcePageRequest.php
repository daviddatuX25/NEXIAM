<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class RectifyOmrSourcePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'source' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:25600'],
            'points' => ['required', 'array', 'size:4'],
            'points.*.0' => ['required', 'numeric', 'min:0'],
            'points.*.1' => ['required', 'numeric', 'min:0'],
            'target_width' => ['required', 'integer', 'min:300', 'max:12000'],
            'target_height' => ['required', 'integer', 'min:300', 'max:12000'],
            'idempotency_key' => ['required', 'string', 'max:190'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'page_no' => ['nullable', 'integer', 'min:1', 'max:4'],
        ];
    }
}

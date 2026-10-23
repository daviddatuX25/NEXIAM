<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class SubmitOmrPhysicalCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:jpeg,png,webp,pdf', 'max:25600'],
            'method' => ['required', 'string', 'in:pre_shaded,hand_shaded'],
            'challenge_id' => ['required', 'integer', 'min:1'],
            'page_no' => ['required', 'integer', 'min:1', 'max:20'],
            'capture_source' => ['required', 'string', 'in:camera,upload'],
            'medium' => ['sometimes', 'nullable', 'string', 'in:hb_2_pencil,dark_black_pen', 'prohibited_if:method,pre_shaded'],
            'target_hash' => ['required_with:method', 'nullable', 'string', 'size:64'],
            'plan_hash' => ['required_with:method', 'nullable', 'string', 'size:64'],
            'expected_vector_hash' => ['required_with:method', 'nullable', 'string', 'size:64'],
            'crop_policy' => ['nullable', 'string', 'in:auto_document_quad,keep_original'],
            'qr_payload' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}

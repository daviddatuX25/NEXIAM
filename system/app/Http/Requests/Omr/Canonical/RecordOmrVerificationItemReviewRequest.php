<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordOmrVerificationItemReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(OmrReviewDecision::values())],
            'selected_option' => ['sometimes', 'nullable', 'string', 'max:32'],
            'reason_code' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z][a-z0-9_]{0,63}$/'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'expected_parent_version' => ['required', 'integer', 'min:0'],
            'expected_item_revision' => ['required', 'integer', 'min:0'],
            'reviewed_machine_evidence_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'expected' => ['prohibited'],
            'expected_option' => ['prohibited'],
            'expected_answer' => ['prohibited'],
            'correct' => ['prohibited'],
            'correct_option' => ['prohibited'],
            'correctness' => ['prohibited'],
            'is_correct' => ['prohibited'],
            'effective' => ['prohibited'],
            'effective_answer' => ['prohibited'],
            'effective_state' => ['prohibited'],
            'score' => ['prohibited'],
            'scores' => ['prohibited'],
        ];
    }
}

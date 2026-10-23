<?php

namespace App\Http\Requests\Assessment;

use App\Assessment\Omr\Review\OmrReviewDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewOmrDiagnosticItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'item_evidence_id' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'string', Rule::in(OmrReviewDecision::values())],
            'final_option' => [
                'nullable',
                'string',
                'max:32',
                'required_if:decision,confirm_machine,set_option',
                'prohibited_if:decision,blank,unresolved',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:decision,set_option,blank,unresolved',
            ],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'expected_run_revision' => ['required', 'integer', 'min:1'],
            'expected_capture_version' => ['required', 'integer', 'min:0'],
            'expected_item_revision' => ['required', 'integer', 'min:0'],
            'expected_evidence_version' => ['required', 'integer', 'min:1'],
            'reviewed_evidence_hash' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-f0-9]{64}$/i',
            ],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
            'expected' => ['prohibited'],
            'expected_option' => ['prohibited'],
            'expected_answer' => ['prohibited'],
            'answer_key' => ['prohibited'],
            'expected_vector' => ['prohibited'],
            'correct' => ['prohibited'],
            'correct_option' => ['prohibited'],
            'correctness' => ['prohibited'],
            'is_correct' => ['prohibited'],
            'effective' => ['prohibited'],
            'effective_answer' => ['prohibited'],
            'effective_state' => ['prohibited'],
            'score' => ['prohibited'],
            'scores' => ['prohibited'],
            'mode' => ['prohibited'],
            'session_id' => ['prohibited'],
            'execution_kind' => ['prohibited'],
            'kind' => ['prohibited'],
            'run_id' => ['prohibited'],
            'workbench_id' => ['prohibited'],
            'context' => ['prohibited'],
            'institution_id' => ['prohibited'],
            'academic_cycle_id' => ['prohibited'],
        ];
    }
}

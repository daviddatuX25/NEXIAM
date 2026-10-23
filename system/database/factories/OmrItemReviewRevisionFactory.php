<?php

namespace Database\Factories;

use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OmrItemReviewRevision>
 */
class OmrItemReviewRevisionFactory extends Factory
{
    protected $model = OmrItemReviewRevision::class;

    public function definition(): array
    {
        return [
            'verification_item_evidence_id' => null,
            'capture_sheet_id' => null,
            'item_key' => 'AREA_Q_1',
            'revision' => 1,
            'supersedes_revision_id' => null,
            'decision' => OmrReviewDecision::Unresolved,
            'selected_option' => null,
            'reason_code' => 'manual_review',
            'note' => null,
            'reviewed_machine_evidence_hash' => hash('sha256', 'omr-review-factory'),
            'parent_version' => 0,
            'reviewed_by_user_id' => User::factory(),
            'command_name' => 'omr.item_review.record',
            'idempotency_key' => (string) Str::uuid(),
            'correlation_id' => (string) Str::uuid(),
        ];
    }

    public function forVerificationItem(OmrTemplateVerificationItemEvidence|int $item): static
    {
        return $this->state([
            'verification_item_evidence_id' => $item instanceof OmrTemplateVerificationItemEvidence ? $item->getKey() : $item,
            'capture_sheet_id' => null,
        ]);
    }

    public function forGradingSheet(OmrCaptureSheet|int $sheet, string $itemKey = 'AREA_Q_1'): static
    {
        return $this->state([
            'verification_item_evidence_id' => null,
            'capture_sheet_id' => $sheet instanceof OmrCaptureSheet ? $sheet->getKey() : $sheet,
            'item_key' => $itemKey,
        ]);
    }

    public function confirmMachine(string $selectedOption = 'A'): static
    {
        return $this->state([
            'decision' => OmrReviewDecision::ConfirmMachine,
            'selected_option' => $selectedOption,
            'reason_code' => null,
        ]);
    }

    public function setOption(string $selectedOption = 'A', ?string $reasonCode = 'manual_correction'): static
    {
        return $this->state([
            'decision' => OmrReviewDecision::SetOption,
            'selected_option' => $selectedOption,
            'reason_code' => $reasonCode,
        ]);
    }

    public function blank(?string $reasonCode = null): static
    {
        return $this->state([
            'decision' => OmrReviewDecision::Blank,
            'selected_option' => null,
            'reason_code' => $reasonCode,
        ]);
    }

    public function unresolved(string $reasonCode = 'unable_to_determine'): static
    {
        return $this->state([
            'decision' => OmrReviewDecision::Unresolved,
            'selected_option' => null,
            'reason_code' => $reasonCode,
        ]);
    }
}

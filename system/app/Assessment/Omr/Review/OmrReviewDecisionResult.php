<?php

namespace App\Assessment\Omr\Review;

use App\Models\Assessment\OmrItemReviewRevision;

/**
 * Safe result returned after an item review append.
 *
 * The revision model remains available to domain consumers while this value
 * object gives HTTP/projection layers the updated parent and item versions.
 */
final readonly class OmrReviewDecisionResult
{
    public function __construct(
        public OmrItemReviewRevision $revision,
        public int $parentVersion,
        public int $itemRevision,
        public string $correlationId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'revision_id' => (int) $this->revision->getKey(),
            'context_type' => $this->revision->verification_item_evidence_id !== null
                ? OmrReviewContext::TYPE_VERIFICATION
                : OmrReviewContext::TYPE_GRADING,
            'parent_id' => $this->revision->verification_item_evidence_id !== null
                ? (int) $this->revision->verification_item_evidence_id
                : (int) $this->revision->capture_sheet_id,
            'item_key' => (string) $this->revision->item_key,
            'revision' => $this->itemRevision,
            'decision' => $this->revision->decision instanceof OmrReviewDecision
                ? $this->revision->decision->value
                : (string) $this->revision->decision,
            'selected_option' => $this->revision->selected_option,
            'evidence_hash' => (string) $this->revision->reviewed_machine_evidence_hash,
            'parent_version' => $this->parentVersion,
            'correlation_id' => $this->correlationId,
        ];
    }
}

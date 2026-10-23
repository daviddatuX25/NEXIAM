<?php

namespace App\Assessment\Omr\Review;

use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use InvalidArgumentException;

/**
 * Server-resolved snapshot used by the item-review command boundary.
 *
 * The context owns the item identity, pinned option set, and machine evidence
 * hash that a caller read. A command must resolve a fresh context while its
 * parent is locked before applying a decision.
 */
final readonly class OmrReviewContext
{
    public const TYPE_VERIFICATION = 'verification';

    public const TYPE_GRADING = 'grading';

    /**
     * @param  list<string>  $optionSet
     * @param  list<string>  $observedOptions
     * @param  list<string>  $machineIssueCodes
     * @param  array<string, mixed>  $resourceScope
     * @param  array<string, mixed>  $machineEnvelope
     */
    public function __construct(
        public string $type,
        public int $parentId,
        public string $itemKey,
        public int $parentVersion,
        public int $itemRevision,
        public array $optionSet,
        public string $evidenceHash,
        public string $machineState,
        public ?string $machineSelectedOption,
        public array $observedOptions,
        public string $lifecycle,
        public bool $reviewable,
        public array $resourceScope,
        public OmrTemplateVerificationCapture|OmrCaptureSheet $parent,
        public ?int $verificationItemEvidenceId = null,
        public ?array $gradingItem = null,
        public array $machineIssueCodes = [],
        public array $machineEnvelope = [],
    ) {
        if (! in_array($type, [self::TYPE_VERIFICATION, self::TYPE_GRADING], true)) {
            throw new InvalidArgumentException('omr_review_context_type_invalid');
        }
        if ($parentId < 1 || trim($itemKey) === '') {
            throw new InvalidArgumentException('omr_review_context_identity_invalid');
        }
        if ($parentVersion < 0 || $itemRevision < 0) {
            throw new InvalidArgumentException('omr_review_context_version_invalid');
        }
        if ($optionSet === [] || count($optionSet) !== count(array_unique($optionSet))) {
            throw new InvalidArgumentException('omr_review_context_option_set_invalid');
        }
        if (preg_match('/^[a-fA-F0-9]{64}$/', $evidenceHash) !== 1) {
            throw new InvalidArgumentException('omr_review_context_evidence_hash_invalid');
        }
        if ($type === self::TYPE_VERIFICATION && $verificationItemEvidenceId === null) {
            throw new InvalidArgumentException('omr_review_context_verification_item_missing');
        }
        if ($type === self::TYPE_GRADING && $gradingItem === null) {
            throw new InvalidArgumentException('omr_review_context_grading_item_missing');
        }
    }

    public function isVerification(): bool
    {
        return $this->type === self::TYPE_VERIFICATION;
    }

    public function isGrading(): bool
    {
        return $this->type === self::TYPE_GRADING;
    }

    public function supportsOption(?string $option): bool
    {
        return $option !== null && in_array($option, $this->optionSet, true);
    }

    /**
     * @return array{type:string,parent_id:int,item_key:string}
     */
    public function aggregateIdentity(): array
    {
        return [
            'type' => $this->type,
            'parent_id' => $this->parentId,
            'item_key' => $this->itemKey,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'type' => $this->type,
            'parent_id' => $this->parentId,
            'item_key' => $this->itemKey,
            'parent_version' => $this->parentVersion,
            'item_revision' => $this->itemRevision,
            'option_set' => $this->optionSet,
            'evidence_hash' => $this->evidenceHash,
            'machine_state' => $this->machineState,
            'machine_selected_option' => $this->machineSelectedOption,
            'observed_options' => $this->observedOptions,
            'lifecycle' => $this->lifecycle,
            'reviewable' => $this->reviewable,
            'resource_scope' => $this->resourceScope,
        ];
    }
}

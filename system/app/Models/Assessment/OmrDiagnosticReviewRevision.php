<?php

namespace App\Models\Assessment;

use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Append-only human review over one diagnostic machine-evidence item.
 *
 * A review binds the submitted parent revision and machine evidence hash so a
 * stale command cannot silently become the current diagnostic interpretation.
 */
class OmrDiagnosticReviewRevision extends Model
{
    protected $table = 'omr_diagnostic_review_revisions';

    protected $fillable = [
        'diagnostic_item_evidence_id',
        'revision',
        'parent_revision',
        'decision',
        'final_option',
        'reason',
        'note',
        'reviewed_evidence_hash',
        'actor_id',
        'idempotency_key',
    ];

    protected $hidden = [
        'note',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'diagnostic_item_evidence_id' => 'integer',
            'revision' => 'integer',
            'parent_revision' => 'integer',
            'decision' => OmrReviewDecision::class,
            'actor_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $revision): void {
            if ((int) $revision->diagnostic_item_evidence_id < 1
                || (int) $revision->revision < 1
                || (int) $revision->parent_revision < 0
                || (int) $revision->actor_id < 1
                || trim((string) $revision->idempotency_key) === '') {
                throw new RuntimeException('omr_diagnostic_review_revision_identity_invalid');
            }

            if (preg_match('/^[a-fA-F0-9]{64}$/', (string) $revision->reviewed_evidence_hash) !== 1) {
                throw new RuntimeException('omr_diagnostic_review_revision_evidence_hash_invalid');
            }

            $decision = $revision->decision instanceof OmrReviewDecision
                ? $revision->decision
                : OmrReviewDecision::tryFrom((string) $revision->decision);
            if ($decision === null) {
                throw new RuntimeException('omr_diagnostic_review_revision_decision_invalid');
            }

            if ($decision->acceptsSelectedOption() && trim((string) $revision->final_option) === '') {
                throw new RuntimeException('omr_diagnostic_review_revision_final_option_required');
            }
            if (! $decision->acceptsSelectedOption() && $revision->final_option !== null) {
                throw new RuntimeException('omr_diagnostic_review_revision_final_option_forbidden');
            }
            if ($decision !== OmrReviewDecision::ConfirmMachine
                && trim((string) $revision->reason) === '') {
                throw new RuntimeException('omr_diagnostic_review_revision_reason_required');
            }
            if ($revision->reason !== null && mb_strlen((string) $revision->reason) > 2000) {
                throw new RuntimeException('omr_diagnostic_review_revision_reason_too_long');
            }
            if ($revision->note !== null && mb_strlen((string) $revision->note) > 2000) {
                throw new RuntimeException('omr_diagnostic_review_revision_note_too_long');
            }
        });

        static::updating(static function (): void {
            throw new RuntimeException('omr_diagnostic_review_revision_immutable');
        });

        static::deleting(static function (): void {
            throw new RuntimeException('omr_diagnostic_review_revision_immutable');
        });
    }

    public function itemEvidence(): BelongsTo
    {
        return $this->belongsTo(OmrDiagnosticItemEvidence::class, 'diagnostic_item_evidence_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeForItem(Builder $query, int $itemEvidenceId): Builder
    {
        return $query->where('diagnostic_item_evidence_id', $itemEvidenceId);
    }

    public function scopeLatestRevision(Builder $query): Builder
    {
        $table = (new self)->getTable();

        return $query->whereNotExists(function (Builder $newer) use ($table): void {
            $newer->selectRaw('1')
                ->from($table.' as newer_review')
                ->whereColumn('newer_review.diagnostic_item_evidence_id', $table.'.diagnostic_item_evidence_id')
                ->whereColumn('newer_review.revision', '>', $table.'.revision');
        });
    }

    /**
     * Safe audit projection. Free-text notes and idempotency material are
     * intentionally omitted from serialized diagnostic receipts.
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => (int) $this->getKey(),
            'diagnostic_item_evidence_id' => (int) $this->diagnostic_item_evidence_id,
            'revision' => (int) $this->revision,
            'parent_revision' => (int) $this->parent_revision,
            'decision' => $this->decision instanceof OmrReviewDecision
                ? $this->decision->value
                : (string) $this->decision,
            'final_option' => $this->final_option,
            'reason' => $this->reason,
            'reviewed_evidence_hash' => (string) $this->reviewed_evidence_hash,
            'actor_id' => (int) $this->actor_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Models\Assessment;

use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Models\User;
use Database\Factories\OmrItemReviewRevisionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Append-only human decision over one immutable machine-evidence item.
 *
 * A row belongs to exactly one verification evidence item or grading sheet;
 * its context-specific resolver supplies the stable item key and evidence
 * hash. The model deliberately does not expose free-text notes through the
 * safe serialization method used by receipts and audit projections.
 */
class OmrItemReviewRevision extends Model
{
    use HasFactory;

    protected $table = 'omr_item_review_revisions';

    protected $fillable = [
        'verification_item_evidence_id',
        'capture_sheet_id',
        'item_key',
        'revision',
        'supersedes_revision_id',
        'decision',
        'selected_option',
        'reason_code',
        'note',
        'reviewed_machine_evidence_hash',
        'parent_version',
        'reviewed_by_user_id',
        'command_name',
        'idempotency_key',
        'correlation_id',
    ];

    protected $hidden = [
        'note',
        'idempotency_key',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'verification_item_evidence_id' => 'integer',
            'capture_sheet_id' => 'integer',
            'revision' => 'integer',
            'supersedes_revision_id' => 'integer',
            'decision' => OmrReviewDecision::class,
            'parent_version' => 'integer',
            'reviewed_by_user_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $revision): void {
            $hasVerificationParent = $revision->verification_item_evidence_id !== null;
            $hasGradingParent = $revision->capture_sheet_id !== null;

            if ($hasVerificationParent === $hasGradingParent) {
                throw new RuntimeException('omr_review_revision_context_invalid');
            }
            if ((int) $revision->revision < 1 || (int) $revision->parent_version < 0) {
                throw new RuntimeException('omr_review_revision_version_invalid');
            }
            if (trim((string) $revision->item_key) === '') {
                throw new RuntimeException('omr_review_revision_item_key_invalid');
            }
            if (preg_match('/^[a-fA-F0-9]{64}$/', (string) $revision->reviewed_machine_evidence_hash) !== 1) {
                throw new RuntimeException('omr_review_revision_evidence_hash_invalid');
            }

            $decision = $revision->decision instanceof OmrReviewDecision
                ? $revision->decision
                : OmrReviewDecision::tryFrom((string) $revision->decision);
            if ($decision === null) {
                throw new RuntimeException('omr_review_revision_decision_invalid');
            }

            if ($decision->acceptsSelectedOption() && trim((string) $revision->selected_option) === '') {
                throw new RuntimeException('omr_review_revision_selection_required');
            }
            if (! $decision->acceptsSelectedOption() && $revision->selected_option !== null) {
                throw new RuntimeException('omr_review_revision_selection_forbidden');
            }
            if ($decision->isUnresolved() && trim((string) $revision->reason_code) === '') {
                throw new RuntimeException('omr_review_revision_reason_required');
            }
            if ($revision->reason_code !== null
                && preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) $revision->reason_code) !== 1) {
                throw new RuntimeException('omr_review_revision_reason_code_invalid');
            }
            if ($revision->note !== null && mb_strlen((string) $revision->note) > 2000) {
                throw new RuntimeException('omr_review_revision_note_too_long');
            }
            if ((int) $revision->reviewed_by_user_id < 1
                || ! Str::isUuid((string) $revision->correlation_id)
                || trim((string) $revision->command_name) === ''
                || trim((string) $revision->idempotency_key) === '') {
                throw new RuntimeException('omr_review_revision_audit_identity_invalid');
            }
        });

        static::updating(static function (): void {
            throw new RuntimeException('omr_review_revision_immutable');
        });

        static::deleting(static function (): void {
            throw new RuntimeException('omr_review_revision_immutable');
        });
    }

    public function verificationItemEvidence(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateVerificationItemEvidence::class, 'verification_item_evidence_id');
    }

    public function captureSheet(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheet::class, 'capture_sheet_id');
    }

    public function supersedesRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_revision_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function scopeForVerificationItem(Builder $query, int $evidenceId): Builder
    {
        return $query->where('verification_item_evidence_id', $evidenceId);
    }

    public function scopeForGradingItem(Builder $query, int $sheetId, string $itemKey): Builder
    {
        return $query
            ->where('capture_sheet_id', $sheetId)
            ->where('item_key', $itemKey);
    }

    public function scopeLatestRevision(Builder $query): Builder
    {
        $table = (new self)->getTable();

        return $query->whereNotExists(function ($newer) use ($table): void {
            $newer->selectRaw('1')
                ->from($table.' as newer_review')
                ->whereColumn('newer_review.revision', '>', $table.'.revision')
                ->where(function ($identity) use ($table): void {
                    $identity
                        ->where(function ($verification) use ($table): void {
                            $verification
                                ->whereNotNull('newer_review.verification_item_evidence_id')
                                ->whereNotNull($table.'.verification_item_evidence_id')
                                ->whereColumn('newer_review.verification_item_evidence_id', $table.'.verification_item_evidence_id')
                                ->whereColumn('newer_review.item_key', $table.'.item_key');
                        })
                        ->orWhere(function ($grading) use ($table): void {
                            $grading
                                ->whereNotNull('newer_review.capture_sheet_id')
                                ->whereNotNull($table.'.capture_sheet_id')
                                ->whereColumn('newer_review.capture_sheet_id', $table.'.capture_sheet_id')
                                ->whereColumn('newer_review.item_key', $table.'.item_key');
                        });
                });
        });
    }

    /**
     * @param  list<int>  $evidenceIds
     * @return Collection<int, self>
     */
    public static function latestForVerificationItems(array $evidenceIds): Collection
    {
        if ($evidenceIds === []) {
            return static::query()->whereRaw('1 = 0')->get();
        }

        return static::query()
            ->whereIn('verification_item_evidence_id', array_values(array_unique(array_map('intval', $evidenceIds))))
            ->latestRevision()
            ->orderByDesc('revision')
            ->get()
            ->keyBy(static fn (self $revision): int => (int) $revision->verification_item_evidence_id);
    }

    /**
     * @param  list<string>  $itemKeys
     * @return Collection<int, self>
     */
    public static function latestForGradingItems(int $sheetId, array $itemKeys): Collection
    {
        if ($itemKeys === []) {
            return static::query()->whereRaw('1 = 0')->get();
        }

        return static::query()
            ->where('capture_sheet_id', $sheetId)
            ->whereIn('item_key', array_values(array_unique($itemKeys)))
            ->latestRevision()
            ->orderByDesc('revision')
            ->get()
            ->keyBy(static fn (self $revision): string => (string) $revision->item_key);
    }

    /**
     * Safe receipt/audit projection. Free-text note and idempotency material
     * are intentionally omitted.
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => (int) $this->getKey(),
            'verification_item_evidence_id' => $this->verification_item_evidence_id,
            'capture_sheet_id' => $this->capture_sheet_id,
            'item_key' => (string) $this->item_key,
            'revision' => (int) $this->revision,
            'supersedes_revision_id' => $this->supersedes_revision_id,
            'decision' => $this->decision instanceof OmrReviewDecision ? $this->decision->value : (string) $this->decision,
            'selected_option' => $this->selected_option,
            'reason_code' => $this->reason_code,
            'reviewed_machine_evidence_hash' => (string) $this->reviewed_machine_evidence_hash,
            'parent_version' => (int) $this->parent_version,
            'reviewed_by_user_id' => (int) $this->reviewed_by_user_id,
            'command_name' => (string) $this->command_name,
            'correlation_id' => (string) $this->correlation_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    protected static function newFactory(): Factory
    {
        return OmrItemReviewRevisionFactory::new();
    }
}

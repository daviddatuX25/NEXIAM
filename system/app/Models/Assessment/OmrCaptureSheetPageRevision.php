<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use RuntimeException;

final class OmrCaptureSheetPageRevision extends Model
{
    public const KIND_SOURCE = 'source';

    public const KIND_REPLACEMENT = 'replacement';

    public const KIND_ANALYSIS = 'analysis';

    public const KIND_SNAPSHOT = 'snapshot';

    protected $table = 'omr_capture_sheet_page_revisions';

    protected $fillable = [
        'page_id', 'revision', 'revision_kind', 'source_revision_id',
        'operation_id', 'idempotency_key', 'payload_fingerprint', 'replacement_reason', 'actor_user_id',
        'status', 'asset_path', 'asset_hmac', 'asset_hmac_key_id', 'asset_key_id',
        'asset_format_version', 'asset_state', 'original_mime', 'original_bytes',
        'client_quad_json', 'crop_policy', 'aligned_mime', 'aligned_bytes', 'aligned_width', 'aligned_height',
        'aligned_hash', 'aligned_asset_path', 'aligned_asset_hmac', 'aligned_asset_hmac_key_id',
        'aligned_asset_key_id', 'proposal_json', 'current_processing_run_id',
        'snapshot_sheet_version', 'snapshot_sheet_status', 'snapshot_admission_attempt_id',
        'snapshot_booklet_set_id', 'snapshot_sheet_confidence', 'snapshot_sheet_proposal_json',
        'snapshot_review_draft_json', 'snapshot_reviewed_json', 'snapshot_reviewed_by_user_id',
        'snapshot_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'page_id' => 'integer',
            'revision' => 'integer',
            'source_revision_id' => 'integer',
            'actor_user_id' => 'integer',
            'original_bytes' => 'integer',
            'aligned_bytes' => 'integer',
            'aligned_width' => 'integer',
            'aligned_height' => 'integer',
            'current_processing_run_id' => 'integer',
            'client_quad_json' => 'array',
            'proposal_json' => 'array',
            'snapshot_sheet_version' => 'integer',
            'snapshot_admission_attempt_id' => 'integer',
            'snapshot_booklet_set_id' => 'integer',
            'snapshot_sheet_confidence' => 'float',
            'snapshot_review_draft_json' => 'array',
            'snapshot_reviewed_json' => 'array',
            'snapshot_reviewed_by_user_id' => 'integer',
            'snapshot_reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(static function (OmrCaptureSheetPageRevision $revision): void {
            if ((int) $revision->revision < 1
                || ! in_array($revision->revision_kind, [self::KIND_SOURCE, self::KIND_REPLACEMENT, self::KIND_ANALYSIS, self::KIND_SNAPSHOT], true)) {
                throw new RuntimeException('omr_page_revision_invalid');
            }

            if ($revision->revision_kind === self::KIND_REPLACEMENT
                && ((int) $revision->revision < 2
                    || (int) $revision->source_revision_id < 1
                    || trim((string) $revision->replacement_reason) === ''
                    || $revision->actor_user_id === null
                    || trim((string) $revision->idempotency_key) === ''
                    || preg_match('/^[a-f0-9]{64}$/', (string) $revision->payload_fingerprint) !== 1)) {
                throw new RuntimeException('omr_page_replacement_revision_invalid');
            }

            if ($revision->revision_kind === self::KIND_SOURCE
                && ((int) $revision->revision !== 1 || $revision->source_revision_id !== null)) {
                throw new RuntimeException('omr_page_source_revision_invalid');
            }
        });

        self::updating(static function (): void {
            throw new LogicException('omr_capture_sheet_page_revision_immutable');
        });
        self::deleting(static function (): void {
            throw new LogicException('omr_capture_sheet_page_revision_immutable');
        });
    }

    public static function capture(
        OmrCaptureSheetPage $page,
        string $kind,
        ?int $sourceRevisionId = null,
        ?int $actorUserId = null,
        ?string $operationId = null,
        ?string $idempotencyKey = null,
        ?string $payloadFingerprint = null,
        ?string $replacementReason = null,
    ): self {

        $sheet = $page->sheet;

        return self::query()->create([
            'page_id' => $page->getKey(),
            'revision' => (int) $page->current_revision,
            'revision_kind' => $kind,
            'source_revision_id' => $sourceRevisionId,
            'operation_id' => $operationId,
            'idempotency_key' => $idempotencyKey,
            'payload_fingerprint' => $payloadFingerprint,
            'replacement_reason' => $replacementReason,
            'actor_user_id' => $actorUserId,
            'status' => $page->status,
            'asset_path' => $page->asset_path,
            'asset_hmac' => $page->asset_hmac,
            'asset_hmac_key_id' => $page->asset_hmac_key_id,
            'asset_key_id' => $page->asset_key_id,
            'asset_format_version' => $page->asset_format_version,
            'asset_state' => $page->asset_state,
            'original_mime' => $page->original_mime,
            'original_bytes' => $page->original_bytes,
            'client_quad_json' => $page->client_quad_json,
            'crop_policy' => $page->crop_policy,
            'aligned_mime' => $page->aligned_mime,
            'aligned_bytes' => $page->aligned_bytes,
            'aligned_width' => $page->aligned_width,
            'aligned_height' => $page->aligned_height,
            'aligned_hash' => $page->aligned_hash,
            'aligned_asset_path' => $page->aligned_asset_path,
            'aligned_asset_hmac' => $page->aligned_asset_hmac,
            'aligned_asset_hmac_key_id' => $page->aligned_asset_hmac_key_id,
            'aligned_asset_key_id' => $page->aligned_asset_key_id,
            'proposal_json' => $page->proposal_json,
            'current_processing_run_id' => $page->current_processing_run_id,
            'snapshot_sheet_version' => $sheet?->version,
            'snapshot_sheet_status' => $sheet?->status,
            'snapshot_admission_attempt_id' => $sheet?->admission_attempt_id,
            'snapshot_booklet_set_id' => $sheet?->booklet_set_id,
            'snapshot_sheet_confidence' => $sheet?->sheet_confidence,
            'snapshot_sheet_proposal_json' => $sheet?->proposal_json,
            'snapshot_review_draft_json' => $sheet?->review_draft_json,
            'snapshot_reviewed_json' => $sheet?->reviewed_json,
            'snapshot_reviewed_by_user_id' => $sheet?->reviewed_by_user_id,
            'snapshot_reviewed_at' => $sheet?->reviewed_at,
        ]);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheetPage::class, 'page_id');
    }

    public function sourceRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_revision_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function processingRun(): BelongsTo
    {
        return $this->belongsTo(OmrProcessingRun::class, 'current_processing_run_id');
    }
}

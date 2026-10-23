<?php

namespace App\Models\Assessment;

use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrCaptureSheet extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PENDING_PAGES = 'pending_pages';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_FAILED_TERMINAL = 'failed_terminal';

    public const MODE_MANUAL = 'manual';

    public const MODE_AUTO = 'auto';

    public const ASSET_PENDING_WRITE = 'pending_write';

    public const ASSET_LIVE = 'live';

    public const ASSET_CLEANUP_PENDING = 'cleanup_pending';

    public const ASSET_INTEGRITY_FAILED = 'integrity_failed';

    public const UNCOMMITTED_STATUSES = [self::STATUS_RECEIVED, self::STATUS_QUEUED, self::STATUS_PROCESSING, self::STATUS_NEEDS_REVIEW, self::STATUS_REVIEWED, self::STATUS_FAILED_TERMINAL];

    protected $fillable = [
        'batch_id',
        'admission_attempt_id',
        'booklet_set_id',
        'observed_set_code',
        'set_resolution_reason',
        'set_resolved_by_user_id',
        'set_resolved_at',
        'status',
        'expected_page_count',
        'capture_mode',
        'version',
        'client_capture_id',
        'detected_applicant_number',
        'detected_applicant_confidence',
        'detected_birthdate',
        'sheet_confidence',
        'proposal_json',
        'review_draft_json',
        'reviewed_json',
        'upload_claim_token',
        'upload_claimed_at',
        'upload_claim_expires_at',
        'original_mime',
        'original_bytes',
        'aligned_mime',
        'aligned_bytes',
        'aligned_width',
        'aligned_height',
        'aligned_hash',
        'asset_path',
        'aligned_asset_path',
        'asset_hmac',
        'aligned_asset_hmac',
        'asset_hmac_key_id',
        'aligned_asset_hmac_key_id',
        'asset_format_version',
        'asset_key_id',
        'aligned_asset_key_id',
        'asset_state',
        'captured_by_user_id',
        'captured_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'committed_by_user_id',
        'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'detected_applicant_confidence' => 'float',
            'sheet_confidence' => 'float',
            'proposal_json' => 'array',
            'review_draft_json' => 'array',
            'reviewed_json' => 'array',
            'upload_claimed_at' => 'datetime',
            'upload_claim_expires_at' => 'datetime',
            'set_resolved_at' => 'datetime',
            'aligned_bytes' => 'integer',
            'aligned_width' => 'integer',
            'aligned_height' => 'integer',
            'captured_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureBatch::class, 'batch_id');
    }

    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    public function bookletSet(): BelongsTo
    {
        return $this->belongsTo(OmrBookletSet::class, 'booklet_set_id');
    }

    public function currentProcessingRun(): BelongsTo
    {
        return $this->belongsTo(OmrProcessingRun::class, 'current_processing_run_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(OmrCaptureSheetPage::class, 'sheet_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function committedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committed_by_user_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(OmrCaptureSheetIssue::class, 'sheet_id');
    }

    public function processingRuns(): HasMany
    {
        return $this->hasMany(OmrProcessingRun::class, 'sheet_id');
    }

    public function isCommitted(): bool
    {
        return $this->status === self::STATUS_COMMITTED;
    }

    public function isTransient(): bool
    {
        return in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_QUEUED, self::STATUS_PROCESSING], true);
    }

    public function hasOpenRequiredIssues(): bool
    {
        return $this->issues()->where('state', 'open')->whereIn('severity', ['required', 'terminal'])->exists();
    }

    public function proposedScores(): array
    {
        $scores = $this->proposal_json['per_area_scores'] ?? [];

        return is_array($scores) ? $scores : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function proposalItems(): array
    {
        $items = $this->proposal_json['items'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function proposalMetadata(): array
    {
        $proposal = $this->proposal_json ?? [];

        return is_array($proposal)
            ? array_intersect_key($proposal, array_flip([
                'envelope', 'provider', 'input_hash', 'native_bundle_hash', 'manifest_hash',
                'config_hash', 'patch_identity', 'image',
            ]))
            : [];
    }

    public function proposalApplicantNumber(): ?string
    {
        $proposal = $this->proposal_json ?? [];

        return is_array($proposal) ? ($proposal['applicant_number_proposal'] ?? null) : null;
    }

    public function reviewedScores(): array
    {
        $scores = $this->reviewed_json['per_area_scores'] ?? [];

        return is_array($scores) ? $scores : [];
    }

    public function draftScores(): array
    {
        $scores = $this->review_draft_json['per_area_scores'] ?? [];

        return is_array($scores) ? $scores : [];
    }

    public function effectiveProvenance(): array
    {
        $provenance = $this->reviewed_json['provenance'] ?? ($this->review_draft_json['provenance'] ?? []);

        return is_array($provenance) ? $provenance : [];
    }

    public function wasAbstained(): bool
    {
        return (bool) ($this->reviewed_json['abstained'] ?? false);
    }
}

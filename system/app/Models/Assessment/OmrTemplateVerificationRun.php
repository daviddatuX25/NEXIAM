<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Durable template-verification run (OpenSpec 35.21, AD-6).
 *
 * The run row — phase, progress, pinned hashes, safe error, actor, and
 * optimistic version — is the SOLE resume/qualification authority. Progress
 * is never reconstructed from operations or job payloads.
 */
class OmrTemplateVerificationRun extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PREPARING_PROOF = 'preparing_proof';
    public const STATUS_CLEAN_RECOVERY = 'clean_recovery';
    public const STATUS_VARIANT_MATRIX = 'variant_matrix';
    public const STATUS_AWAITING_PRINT = 'awaiting_print';
    public const STATUS_AWAITING_CAPTURE = 'awaiting_capture';
    public const STATUS_ANALYZING_CAPTURE = 'analyzing_capture';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';

    public const MODE_DIGITAL = 'digital';
    public const MODE_QUICK_REHEARSAL = 'quick_rehearsal';
    public const MODE_FULL_QUALIFICATION = 'full_qualification';

    /** Non-terminal statuses; exactly one active run per target (DB partial unique index). */
    public const ACTIVE_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_PREPARING_PROOF,
        self::STATUS_CLEAN_RECOVERY,
        self::STATUS_VARIANT_MATRIX,
        self::STATUS_AWAITING_PRINT,
        self::STATUS_AWAITING_CAPTURE,
        self::STATUS_ANALYZING_CAPTURE,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_PASSED,
        self::STATUS_FAILED,
    ];

    protected $table = 'omr_template_verification_runs';

    protected $attributes = [
        'status' => self::STATUS_QUEUED,
        'mode' => self::MODE_DIGITAL,
        'attempt' => 1,
        'plan_version' => 'v1',
        'optimistic_version' => 0,
    ];

    protected $fillable = [
        'draft_id', 'template_version_id', 'status', 'mode', 'phase_detail',
        'attempt', 'plan_version', 'plan_hash', 'expected_vector_hash',
        'artifact_hash', 'native_bundle_hash', 'manifest_hash', 'config_hash',
        'mapping_hash', 'proof_hash', 'clean_vector_hash', 'challenge_code',
        'progress_done', 'progress_total', 'result_summary', 'error_code',
        'safe_error', 'optimistic_version', 'started_by_user_id',
        'started_at', 'finished_at', 'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'draft_id' => 'integer',
            'template_version_id' => 'integer',
            'phase_detail' => 'array',
            'attempt' => 'integer',
            'progress_done' => 'integer',
            'progress_total' => 'integer',
            'result_summary' => 'array',
            'optimistic_version' => 'integer',
            'started_by_user_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAuthoringDraft::class, 'draft_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function captures(): HasMany
    {
        return $this->hasMany(OmrTemplateVerificationCapture::class, 'run_id');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(OmrPhysicalChallenge::class, 'run_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isDraftTargeted(): bool
    {
        return $this->draft_id !== null;
    }

    /** @return array<string, string> the 5-key pinned target hash set */
    public function pinnedTargetHashes(): array
    {
        return [
            'artifact' => $this->artifact_hash,
            'native_bundle' => $this->native_bundle_hash,
            'manifest' => $this->manifest_hash,
            'config' => $this->config_hash,
            'mapping' => $this->mapping_hash,
        ];
    }
}

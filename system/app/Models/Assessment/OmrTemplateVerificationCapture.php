<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One normalized verification capture (OpenSpec 35.21, AD-5).
 *
 * Read-only evidence row for a digital matrix variant or a physical capture
 * submission: input/aligned/overlay asset references, hashes, expected vs
 * detected vectors, safe summary. No applicant binding and no score command.
 */
class OmrTemplateVerificationCapture extends Model
{
    protected static function booted(): void
    {

        static::creating(static function (self $capture): void {
            if ($capture->discarded_at !== null
                || $capture->discarded_by_user_id !== null
                || $capture->discard_reason !== null) {
                throw new RuntimeException('verification_capture_discard_invalid');
            }
            if ($capture->method === self::METHOD_HAND_SHADED
                && ! in_array($capture->medium, self::HAND_SHADED_MEDIA, true)) {
                throw new RuntimeException('verification_capture_hand_medium_required');
            }
            if ($capture->method === self::METHOD_PRE_SHADED && $capture->medium !== null) {
                throw new RuntimeException('verification_capture_pre_medium_forbidden');
            }
        });
        static::updating(static function (self $capture): void {
            $originalAttemptState = $capture->getRawOriginal('attempt_state');
            $physical = $originalAttemptState !== null
                || $capture->getRawOriginal('method_id') !== null
                || $capture->getRawOriginal('challenge_id') !== null
                || $capture->getRawOriginal('method') !== null
                || $capture->attempt_state !== null
                || $capture->method_id !== null
                || $capture->challenge_id !== null
                || $capture->method !== null;
            $wasDiscarded = $capture->getRawOriginal('discarded_at') !== null;
            $isDiscarded = $capture->discarded_at !== null;
            if (! $wasDiscarded && $isDiscarded) {
                if (! in_array($originalAttemptState, [
                    self::ATTEMPT_FAILED,
                    self::ATTEMPT_PASSED,
                    self::ATTEMPT_SUPERSEDED,
                ], true)
                    || $capture->discarded_by_user_id === null
                    || trim((string) $capture->discard_reason) === ''
                    || DB::table('omr_template_verification_method_pages')
                        ->where('selected_attempt_id', $capture->getKey())
                        ->exists()) {
                    throw new RuntimeException('verification_capture_discard_invalid');
                }
                foreach (array_keys($capture->getDirty()) as $field) {
                    if (! in_array($field, [
                        'discarded_at', 'discarded_by_user_id', 'discard_reason',
                        'lock_version', 'updated_at',
                    ], true)) {
                        throw new RuntimeException('verification_capture_discard_invalid');
                    }
                }

                return;
            }
            if ($wasDiscarded) {
                foreach (array_keys($capture->getDirty()) as $field) {
                    if ($field !== 'updated_at') {
                        throw new RuntimeException('verification_capture_discard_immutable');
                    }
                }

                return;
            }

            if (! $physical) {
                return;
            }

            $immutable = [
                'run_id', 'method_id', 'challenge_id', 'attempt', 'attempt_no',
                'page_no', 'method', 'mode', 'variant', 'target_kind', 'target_id',
                'target_version', 'target_hash', 'plan_version', 'plan_hash',
                'plan_seed', 'challenge_hash', 'renderer_version', 'renderer_hash',
                'marker_profile_id', 'marker_profile_version', 'marker_profile_hash',
                'page_manifest_hash', 'expected', 'input_hash', 'input_asset_hash',
                'input_asset_id', 'expected_vector_hash', 'medium',
                'captured_by_user_id', 'captured_at', 'created_at',
            ];
            foreach ($immutable as $field) {
                if ($capture->isDirty($field)) {
                    throw new RuntimeException('verification_capture_immutable');
                }
            }

            $mutable = ['updated_at'];
            $newState = (string) ($capture->attempt_state ?? '');
            if ($originalAttemptState === self::ATTEMPT_ACTIVE) {
                if (! in_array($newState, [self::ATTEMPT_PASSED, self::ATTEMPT_FAILED], true)) {
                    throw new RuntimeException('verification_capture_transition_invalid');
                }
                $mutable = [
                    ...$mutable,
                    'attempt_state', 'actual', 'status', 'error_code', 'failure_code',
                    'failure_detail', 'aligned_asset_hash', 'aligned_asset_id',
                    'overlay_asset_hash', 'overlay_asset_id', 'evidence_version',
                    'evidence_hash', 'detected_vector_hash', 'vector_match',
                    'orientation_tried', 'accepted_orientation', 'orientation_diagnostics',
                    'marker_result', 'layout_result', 'gate_result', 'summary',
                    'printer', 'device', 'paper', 'measurements',
                    'selected_by_user_id', 'selected_at', 'selection_reason',
                    'lock_version',
                ];
            } elseif ($originalAttemptState === self::ATTEMPT_FAILED) {
                if (! in_array($newState, [self::ATTEMPT_FAILED, self::ATTEMPT_PASSED], true)) {
                    throw new RuntimeException('verification_capture_transition_invalid');
                }
                $mutable = [
                    ...$mutable,
                    'attempt_state', 'actual', 'status', 'failure_code',
                    'failure_detail', 'lock_version',
                ];
            } elseif ($originalAttemptState === self::ATTEMPT_PASSED) {
                if (! in_array($newState, [self::ATTEMPT_PASSED, self::ATTEMPT_SUPERSEDED], true)) {
                    throw new RuntimeException('verification_capture_transition_invalid');
                }
                $mutable = [
                    ...$mutable,
                    'attempt_state', 'superseded_by_attempt_id', 'superseded_by_user_id',
                    'superseded_at', 'supersede_reason', 'selected_by_user_id',
                    'selected_at', 'selection_reason', 'lock_version',
                ];
            } elseif ($originalAttemptState === self::ATTEMPT_SUPERSEDED) {
                if ($newState !== self::ATTEMPT_SUPERSEDED) {
                    throw new RuntimeException('verification_capture_transition_invalid');
                }
            } else {
                throw new RuntimeException('verification_capture_transition_invalid');
            }

            foreach (array_keys($capture->getDirty()) as $field) {
                if (! in_array($field, $mutable, true)) {
                    throw new RuntimeException('verification_capture_transition_invalid');
                }
            }

            if ($capture->isDirty('attempt_state')
                && DB::table('omr_template_verification_method_pages')
                    ->where('selected_attempt_id', $capture->getKey())
                    ->exists()
                && $capture->attempt_state !== self::ATTEMPT_PASSED) {
                throw new RuntimeException('verification_capture_selected_state_invalid');
            }
            if ($capture->attempt_state === self::ATTEMPT_SUPERSEDED
                && ($capture->superseded_by_attempt_id === null
                    || trim((string) $capture->supersede_reason) === '')) {
                throw new RuntimeException('verification_capture_supersession_invalid');
            }
        });

        static::deleting(static function (self $capture): void {
            // Digital matrix variants predate the physical-attempt contract
            // and are still replaceable during a new verification run. Physical
            // attempts carry a method/page binding and remain append-only.
            if ($capture->attempt_state !== null
                || $capture->method_id !== null
                || $capture->challenge_id !== null
                || $capture->method !== null) {
                throw new RuntimeException('verification_capture_immutable');
            }
        });
    }

    public const STATUS_PASS = 'pass';

    public const STATUS_FAIL = 'fail';

    public const ACTUAL_PASS = 'pass';

    public const ACTUAL_FAILED = 'failed';

    public const ACTUAL_TYPED_REJECTION = 'typed_rejection';

    public const ACTUAL_ANSWER_VECTOR_MISMATCH = 'answer_vector_mismatch';

    public const ACTUAL_FALSE_ACCEPT = 'false_accept';

    public const METHOD_PRE_SHADED = 'pre_shaded';

    public const METHOD_HAND_SHADED = 'hand_shaded';

    public const METHODS = [self::METHOD_PRE_SHADED, self::METHOD_HAND_SHADED];

    public const ATTEMPT_ACTIVE = 'active';

    public const ATTEMPT_PASSED = 'passed';

    public const ATTEMPT_FAILED = 'failed';

    public const ATTEMPT_SUPERSEDED = 'superseded';

    public const ATTEMPT_STATES = [
        self::ATTEMPT_ACTIVE,
        self::ATTEMPT_PASSED,
        self::ATTEMPT_FAILED,
        self::ATTEMPT_SUPERSEDED,
    ];

    public const ATTEMPT_STATE_ACTIVE = self::ATTEMPT_ACTIVE;

    public const ATTEMPT_STATE_PASSED = self::ATTEMPT_PASSED;

    public const ATTEMPT_STATE_FAILED = self::ATTEMPT_FAILED;

    public const ATTEMPT_STATE_SUPERSEDED = self::ATTEMPT_SUPERSEDED;

    public const ORIENTATIONS = [0, 90, 180, 270];

    public const MEDIUM_HB_2_PENCIL = 'hb_2_pencil';

    public const MEDIUM_DARK_BLACK_PEN = 'dark_black_pen';

    public const HAND_SHADED_MEDIA = [
        self::MEDIUM_HB_2_PENCIL,
        self::MEDIUM_DARK_BLACK_PEN,
    ];

    protected $table = 'omr_template_verification_captures';

    protected $attributes = [
        'evidence_version' => 'v1',
        'lock_version' => 0,
    ];

    protected $fillable = [
        'run_id', 'method_id', 'challenge_id', 'attempt', 'attempt_no', 'page_no', 'method',
        'mode', 'variant', 'attempt_state', 'target_kind', 'target_id',
        'target_version', 'target_hash', 'plan_version', 'plan_hash', 'plan_seed',
        'challenge_hash', 'renderer_version', 'renderer_hash', 'marker_profile_id',
        'marker_profile_version', 'marker_profile_hash', 'page_manifest_hash',
        'expected', 'actual', 'status', 'error_code', 'failure_code',
        'failure_detail', 'input_hash', 'input_asset_hash', 'input_asset_id',
        'aligned_asset_hash', 'aligned_asset_id', 'overlay_asset_hash',
        'overlay_asset_id', 'evidence_version', 'evidence_hash',
        'expected_vector_hash', 'detected_vector_hash', 'vector_match',
        'orientation_tried', 'accepted_orientation', 'orientation_diagnostics',
        'marker_result', 'layout_result', 'gate_result', 'medium', 'summary',
        'superseded_by_attempt_id', 'superseded_by_user_id', 'superseded_at',
        'printer', 'device', 'paper', 'measurements', 'lock_version',
        'captured_by_user_id', 'captured_at', 'discarded_at',
        'discarded_by_user_id', 'discard_reason',
    ];

    protected function casts(): array
    {
        return [
            'run_id' => 'integer',
            'method_id' => 'integer',
            'challenge_id' => 'integer',
            'attempt' => 'integer',
            'attempt_no' => 'integer',
            'page_no' => 'integer',
            'target_id' => 'integer',
            'marker_profile_id' => 'integer',
            'marker_profile_version' => 'integer',
            'input_asset_id' => 'integer',
            'aligned_asset_id' => 'integer',
            'overlay_asset_id' => 'integer',
            'vector_match' => 'boolean',
            'orientation_tried' => 'array',
            'accepted_orientation' => 'integer',
            'orientation_diagnostics' => 'array',
            'marker_result' => 'array',
            'layout_result' => 'array',
            'gate_result' => 'array',
            'summary' => 'array',
            'measurements' => 'array',
            'superseded_by_attempt_id' => 'integer',
            'superseded_by_user_id' => 'integer',
            'superseded_at' => 'datetime',
            'selected_by_user_id' => 'integer',
            'selected_at' => 'datetime',
            'lock_version' => 'integer',
            'discarded_at' => 'datetime',
            'discarded_by_user_id' => 'integer',
            'discard_reason' => 'string',
            'captured_by_user_id' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateVerificationRun::class, 'run_id');
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(OmrPhysicalChallenge::class, 'challenge_id');
    }

    public function inputAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'input_asset_id');
    }

    public function alignedAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'aligned_asset_id');
    }

    public function overlayAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'overlay_asset_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_attempt_id');
    }

    public function supersededByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superseded_by_user_id');
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_user_id');
    }

    public function evidenceItems(): HasMany
    {
        return $this->hasMany(OmrTemplateVerificationItemEvidence::class, 'capture_id');
    }

    public function scopeForMethod(Builder $query, string $method): Builder
    {
        return $query->where('method', $method);
    }

    public function scopeForPage(Builder $query, int $pageNo): Builder
    {
        return $query->where('page_no', $pageNo);
    }

    public function scopePassed(Builder $query): Builder
    {
        return $query->where(function (Builder $nested): void {
            $nested->where('attempt_state', self::ATTEMPT_PASSED)
                ->orWhere(function (Builder $legacy): void {
                    $legacy->whereNull('attempt_state')->where('status', self::STATUS_PASS);
                });
        });
    }

    public function isPass(): bool
    {
        return $this->attempt_state === self::ATTEMPT_PASSED
            || ($this->attempt_state === null && $this->status === self::STATUS_PASS);
    }

    public function isPassed(): bool
    {
        return $this->isPass();
    }

    public function isActive(): bool
    {
        return $this->attempt_state === self::ATTEMPT_ACTIVE;
    }

    public function isSuperseded(): bool
    {
        return $this->attempt_state === self::ATTEMPT_SUPERSEDED;
    }

    public function isDiscarded(): bool
    {
        return $this->discarded_at !== null;
    }
}

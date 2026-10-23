<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * One method-bound physical-assurance lifecycle for a verification run.
 *
 * A run owns exactly one row for each required method. Attempts and page
 * selections remain child evidence; this model never derives state from a
 * transient worker response.
 */
class OmrTemplateVerificationMethod extends Model
{
    protected static function booted(): void
    {
        static::updating(static function (self $method): void {
            foreach ([
                'run_id', 'method', 'target_kind', 'target_id', 'target_version',
                'target_hash', 'plan_version', 'plan_seed', 'plan_hash',
                'expected_vector_hash', 'required_page_count',
            ] as $immutable) {
                if ($method->isDirty($immutable)) {
                    throw new RuntimeException('verification_method_immutable');
                }
            }
            // State is transition-authorized here as well as in the
            // coordinator, so direct model writers cannot bypass the gate.
            $from = (string) $method->getRawOriginal('method_state');
            $to = (string) $method->method_state;
            $allowed = [
                self::STATE_NOT_STARTED => [self::STATE_CHALLENGE_READY],
                self::STATE_CHALLENGE_READY => [self::STATE_AWAITING_CAPTURE, self::STATE_FAILED],
                self::STATE_AWAITING_CAPTURE => [self::STATE_ANALYZING, self::STATE_PASSED, self::STATE_FAILED],
                self::STATE_ANALYZING => [self::STATE_NEEDS_REVIEW, self::STATE_PASSED, self::STATE_FAILED, self::STATE_AWAITING_CAPTURE],
                self::STATE_NEEDS_REVIEW => [self::STATE_PASSED, self::STATE_FAILED, self::STATE_AWAITING_CAPTURE],
                self::STATE_PASSED => [self::STATE_AWAITING_CAPTURE],
                self::STATE_FAILED => [self::STATE_AWAITING_CAPTURE],
            ];
            if ($method->isDirty('method_state')
                && $from !== $to
                && ! in_array($to, $allowed[$from] ?? [], true)) {
                throw new RuntimeException('verification_method_transition_invalid');
            }
        });
    }

    public const METHOD_PRE_SHADED = 'pre_shaded';

    public const METHOD_HAND_SHADED = 'hand_shaded';

    public const METHODS = [
        self::METHOD_PRE_SHADED,
        self::METHOD_HAND_SHADED,
    ];

    public const STATE_NOT_STARTED = 'not_started';

    public const STATE_CHALLENGE_READY = 'challenge_ready';

    public const STATE_AWAITING_CAPTURE = 'awaiting_capture';

    public const STATE_ANALYZING = 'analyzing';

    public const STATE_NEEDS_REVIEW = 'needs_review';

    public const STATE_PASSED = 'passed';

    public const STATE_FAILED = 'failed';

    public const STATES = [
        self::STATE_NOT_STARTED,
        self::STATE_CHALLENGE_READY,
        self::STATE_AWAITING_CAPTURE,
        self::STATE_ANALYZING,
        self::STATE_NEEDS_REVIEW,
        self::STATE_PASSED,
        self::STATE_FAILED,
    ];

    public const TARGET_CANDIDATE = 'candidate';

    public const TARGET_COMPILED_VERSION = 'compiled_version';

    protected $table = 'omr_template_verification_methods';

    protected $attributes = [
        'method_state' => self::STATE_NOT_STARTED,
        'plan_version' => 'v1',
        'lock_version' => 0,
    ];

    protected $fillable = [
        'run_id', 'method', 'method_state',
        'target_kind', 'target_id', 'target_version', 'target_hash',
        'plan_version', 'plan_seed', 'plan_hash', 'expected_vector_hash',
        'challenge_id', 'required_page_count',
        'challenge_ready_at', 'started_at', 'completed_at', 'failed_at',
        'last_attempt_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'run_id' => 'integer',
            'target_id' => 'integer',
            'challenge_id' => 'integer',
            'required_page_count' => 'integer',
            'lock_version' => 'integer',
            'challenge_ready_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'last_attempt_at' => 'datetime',
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

    public function pages(): HasMany
    {
        return $this->hasMany(OmrTemplateVerificationMethodPage::class, 'method_id');
    }

    public function captures(): HasMany
    {
        return $this->hasMany(OmrTemplateVerificationCapture::class, 'method_id');
    }

    public function scopeForMethod(Builder $query, string $method): Builder
    {
        return $query->where('method', $method);
    }

    public function scopeInState(Builder $query, string $state): Builder
    {
        return $query->where('method_state', $state);
    }

    public function isPassed(): bool
    {
        return $this->method_state === self::STATE_PASSED;
    }

    public function isCandidate(): bool
    {
        return $this->target_kind === self::TARGET_CANDIDATE;
    }

    public function isQualificationCapable(): bool
    {
        return $this->target_kind === self::TARGET_COMPILED_VERSION;
    }
}

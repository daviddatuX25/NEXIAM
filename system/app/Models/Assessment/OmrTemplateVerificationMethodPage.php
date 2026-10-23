<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** One required or optional page projection for a method-bound assurance run. */
class OmrTemplateVerificationMethodPage extends Model
{
    protected static function booted(): void
    {
        static::creating(static function (self $page): void {
            if ($page->selected_attempt_id !== null) {
                $page->assertSelectedAttempt();
            }
        });

        static::updating(static function (self $page): void {
            foreach (['method_id', 'page_no', 'required'] as $immutable) {
                if ($page->isDirty($immutable)) {
                    throw new RuntimeException('verification_method_page_immutable');
                }
            }

            foreach (array_keys($page->getDirty()) as $attribute) {
                if (! in_array($attribute, ['page_state', 'page_version', 'selected_attempt_id', 'updated_at'], true)) {
                    throw new RuntimeException('verification_method_page_transition_invalid');
                }
            }

            $from = (string) $page->getRawOriginal('page_state');
            $to = (string) $page->page_state;
            $allowed = [
                self::STATE_NOT_STARTED => [self::STATE_AWAITING_CAPTURE],
                self::STATE_AWAITING_CAPTURE => [self::STATE_ANALYZING, self::STATE_PASSED, self::STATE_FAILED],
                self::STATE_ANALYZING => [self::STATE_NEEDS_REVIEW, self::STATE_PASSED, self::STATE_FAILED, self::STATE_AWAITING_CAPTURE],
                self::STATE_NEEDS_REVIEW => [self::STATE_PASSED, self::STATE_FAILED, self::STATE_AWAITING_CAPTURE],
                self::STATE_PASSED => [self::STATE_AWAITING_CAPTURE],
                self::STATE_FAILED => [self::STATE_AWAITING_CAPTURE],
            ];
            if ($page->isDirty('page_state')
                && $from !== $to
                && ! in_array($to, $allowed[$from] ?? [], true)) {
                throw new RuntimeException('verification_method_page_transition_invalid');
            }

            if ($page->isDirty('selected_attempt_id') && $page->selected_attempt_id !== null) {
                $page->assertSelectedAttempt();
            }
        });
    }

    public const STATE_NOT_STARTED = 'not_started';

    public const STATE_AWAITING_CAPTURE = 'awaiting_capture';

    public const STATE_ANALYZING = 'analyzing';

    public const STATE_NEEDS_REVIEW = 'needs_review';

    public const STATE_PASSED = 'passed';

    public const STATE_FAILED = 'failed';

    public const STATES = [
        self::STATE_NOT_STARTED,
        self::STATE_AWAITING_CAPTURE,
        self::STATE_ANALYZING,
        self::STATE_NEEDS_REVIEW,
        self::STATE_PASSED,
        self::STATE_FAILED,
    ];

    public const PAGE_STATE_NOT_STARTED = self::STATE_NOT_STARTED;

    public const PAGE_STATE_AWAITING_CAPTURE = self::STATE_AWAITING_CAPTURE;

    public const PAGE_STATE_ANALYZING = self::STATE_ANALYZING;

    public const PAGE_STATE_NEEDS_REVIEW = self::STATE_NEEDS_REVIEW;

    public const PAGE_STATE_PASSED = self::STATE_PASSED;

    public const PAGE_STATE_FAILED = self::STATE_FAILED;

    protected $table = 'omr_template_verification_method_pages';

    protected $attributes = [
        'required' => true,
        'page_state' => self::STATE_NOT_STARTED,
        'page_version' => 0,
    ];

    protected $fillable = [
        'method_id', 'page_no', 'required', 'page_state', 'page_version',
        'selected_attempt_id',
    ];

    protected function casts(): array
    {
        return [
            'method_id' => 'integer',
            'page_no' => 'integer',
            'required' => 'boolean',
            'page_version' => 'integer',
            'selected_attempt_id' => 'integer',
        ];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateVerificationMethod::class, 'method_id');
    }

    public function selectedAttempt(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateVerificationCapture::class, 'selected_attempt_id');
    }

    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('required', true);
    }

    public function scopeInState(Builder $query, string $state): Builder
    {
        return $query->where('page_state', $state);
    }

    public function hasSelectedAttempt(): bool
    {
        return $this->selected_attempt_id !== null;
    }

    public function isPassed(): bool
    {
        return $this->page_state === self::STATE_PASSED && $this->selected_attempt_id !== null;
    }

    private function assertSelectedAttempt(): void
    {
        $method = DB::table('omr_template_verification_methods')
            ->select(['run_id', 'method', 'target_kind', 'target_id', 'target_version', 'target_hash', 'plan_hash', 'expected_vector_hash', 'challenge_id'])
            ->where('id', $this->method_id)
            ->first();
        $attempt = DB::table('omr_template_verification_captures')
            ->select(['method_id', 'run_id', 'method', 'page_no', 'challenge_id', 'target_kind', 'target_id', 'target_version', 'attempt_state', 'target_hash', 'plan_hash', 'expected_vector_hash', 'discarded_at'])
            ->where('id', $this->selected_attempt_id)
            ->first();

        if ($method === null
            || $attempt === null
            || (int) $attempt->method_id !== (int) $this->method_id
            || (int) $attempt->run_id !== (int) $method->run_id
            || (string) $attempt->method !== (string) $method->method
            || (int) $attempt->page_no !== (int) $this->page_no
            || (int) $attempt->challenge_id !== (int) $method->challenge_id
            || (string) $attempt->target_kind !== (string) $method->target_kind
            || (int) $attempt->target_id !== (int) $method->target_id
            || (string) $attempt->target_version !== (string) $method->target_version
            || (string) $attempt->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_PASSED
            || $attempt->discarded_at !== null
            || ! hash_equals((string) $method->target_hash, (string) $attempt->target_hash)
            || ! hash_equals((string) $method->plan_hash, (string) $attempt->plan_hash)
            || ! hash_equals((string) $method->expected_vector_hash, (string) $attempt->expected_vector_hash)) {
            throw new RuntimeException('verification_method_page_selected_attempt_invalid');
        }
    }
}

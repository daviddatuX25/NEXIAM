<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicPolicy extends Model
{
    protected $attributes = [
        'kind' => 'policy',
        'is_designated_intake' => false,
    ];

    protected $fillable = [
        'institution_id', 'publisher_key', 'publisher_label', 'program_id', 'kind', 'title', 'content',
        'revision', 'published_revision_id', 'status', 'is_designated_intake', 'created_by', 'published_by', 'published_at',
        'withdrawn_by', 'withdrawn_at', 'withdrawal_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
            'is_designated_intake' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new \LogicException('Public policies are withdrawn instead of deleted.');
        });
    }

    public static function currentIntakeSelection(int $institutionId, bool $lockForUpdate = false): ?self
    {
        $query = self::query()
            ->where('institution_id', $institutionId)
            ->where('is_designated_intake', true)
            ->with('publishedRevision')
            ->orderBy('id');
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $selections = $query->get();
        if ($selections->count() > 1) {
            throw new \LogicException('An institution cannot have multiple designated intake policies.');
        }

        return $selections->first();
    }

    public static function designatedIntake(int $institutionId, bool $lockForUpdate = false): ?self
    {
        $policy = self::currentIntakeSelection($institutionId, $lockForUpdate);
        if ($policy === null
            || ! str_starts_with($policy->publisher_key, 'office:')
            || $policy->kind !== 'privacy'
            || $policy->program_id !== null
            || $policy->status !== 'published') {
            return null;
        }

        $revision = $policy->publishedRevision;
        if ($revision === null
            || (int) $revision->public_policy_id !== (int) $policy->getKey()
            || (int) $revision->institution_id !== $institutionId
            || $revision->publisher_key !== $policy->publisher_key
            || $revision->publisher_label !== $policy->publisher_label
            || $revision->program_id !== null
            || $revision->state !== 'published'
            || $revision->kind !== 'privacy') {
            return null;
        }

        return $policy;
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<PublicPolicyRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PublicPolicyRevision::class, 'published_revision_id');
    }

    /** @return HasMany<PublicPolicyRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PublicPolicyRevision::class, 'public_policy_id')->orderBy('revision');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

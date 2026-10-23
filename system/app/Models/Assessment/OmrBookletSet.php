<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class OmrBookletSet extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    public const SET_CODES = ['A', 'B', 'C', 'D'];

    protected $table = 'omr_booklet_sets';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'generation_version' => 'booklet-set-v1',
    ];

    protected $fillable = [
        'booklet_version_id',
        'set_code',
        'status',
        'generation_version',
        'generation_seed',
        'source_content_hash',
        'order_hash',
        'answer_key_hash',
        'diagnostics_json',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'diagnostics_json' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $set): void {
            if (self::hasSealedBookletVersion($set->getRawOriginal('booklet_version_id'))
                || self::hasSealedBookletVersion($set->booklet_version_id)) {
                throw new RuntimeException('Finalized booklet Sets are immutable.');
            }
        });

        static::deleting(static function (self $set): void {
            if (self::hasSealedBookletVersion($set->getRawOriginal('booklet_version_id'))
                || self::hasSealedBookletVersion($set->booklet_version_id)) {
                throw new RuntimeException('Finalized booklet Sets are immutable.');
            }
        });
    }

    private static function hasSealedBookletVersion(?int $versionId): bool
    {
        if ($versionId === null) {
            return false;
        }

        return OmrQuestionBookletVersion::query()
            ->whereKey($versionId)
            ->where(static function ($query): void {
                $query->where('status', OmrQuestionBookletVersion::STATUS_FINALIZED)
                    ->orWhereNotNull('finalized_at');
            })
            ->exists();
    }

    public function bookletVersion(): BelongsTo
    {
        return $this->belongsTo(OmrQuestionBookletVersion::class, 'booklet_version_id');
    }

    /**
     * @return HasMany<OmrBookletSetItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OmrBookletSetItem::class, 'booklet_set_id');
    }

    public function snapshotSets(): HasMany
    {
        return $this->hasMany(ExamSessionMaterialSet::class, 'booklet_set_id');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED || $this->finalized_at !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT && ! $this->isFinalized();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFinalized(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('status', self::STATUS_FINALIZED)->orWhereNotNull('finalized_at');
        });
    }

    /**
     * @return array<int, string>
     */
    public function answerKeyByPosition(): array
    {
        return $this->items()
            ->orderBy('global_position')
            ->get(['global_position', 'correct_option_code'])
            ->mapWithKeys(fn (OmrBookletSetItem $item): array => [(int) $item->global_position => (string) $item->correct_option_code])
            ->all();
    }
}

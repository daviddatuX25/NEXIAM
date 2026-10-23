<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AptitudeArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class OmrBookletSetItem extends Model
{
    protected $table = 'omr_booklet_set_items';

    protected $fillable = [
        'booklet_set_id',
        'booklet_version_id',
        'booklet_item_id',
        'booklet_item_uid',
        'booklet_section_uid',
        'aptitude_area_id',
        'test_instrument_item_id',
        'area_position',
        'global_position',
        'correct_option_code',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'booklet_version_id' => 'integer',
            'area_position' => 'integer',
            'global_position' => 'integer',
            'test_instrument_item_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $item): void {
            if (self::hasSealedBookletVersion($item->getRawOriginal('booklet_set_id'))
                || self::hasSealedBookletVersion($item->booklet_set_id)
                || self::hasSealedBookletItem($item->getRawOriginal('booklet_item_id'))
                || self::hasSealedBookletItem($item->booklet_item_id)) {
                throw new RuntimeException('Finalized booklet Set items are immutable.');
            }
        });

        static::deleting(static function (self $item): void {
            if (self::hasSealedBookletVersion($item->getRawOriginal('booklet_set_id'))
                || self::hasSealedBookletVersion($item->booklet_set_id)
                || self::hasSealedBookletItem($item->getRawOriginal('booklet_item_id'))
                || self::hasSealedBookletItem($item->booklet_item_id)) {
                throw new RuntimeException('Finalized booklet Set items are immutable.');
            }
        });
    }

    private static function hasSealedBookletVersion(?int $setId): bool
    {
        if ($setId === null) {
            return false;
        }

        return OmrQuestionBookletVersion::query()
            ->whereHas('sets', static fn ($query) => $query->whereKey($setId))
            ->where(static function ($query): void {
                $query->where('status', OmrQuestionBookletVersion::STATUS_FINALIZED)
                    ->orWhereNotNull('finalized_at');
            })
            ->exists();
    }

    private static function hasSealedBookletItem(?int $itemId): bool
    {
        if ($itemId === null) {
            return false;
        }

        return OmrQuestionBookletVersion::query()
            ->whereHas('items', static fn ($query) => $query->whereKey($itemId))
            ->where(static function ($query): void {
                $query->where('status', OmrQuestionBookletVersion::STATUS_FINALIZED)
                    ->orWhereNotNull('finalized_at');
            })
            ->exists();
    }

    public function bookletSet(): BelongsTo
    {
        return $this->belongsTo(OmrBookletSet::class, 'booklet_set_id');
    }

    public function bookletItem(): BelongsTo
    {
        return $this->belongsTo(OmrBookletItem::class, 'booklet_item_id');
    }

    /**
     * @return BelongsTo<AptitudeArea, $this>
     */
    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class);
    }

    public function testInstrumentItem(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentItem::class, 'test_instrument_item_id');
    }
}

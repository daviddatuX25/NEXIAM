<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AptitudeArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class OmrBookletItem extends Model
{
    protected $table = 'omr_booklet_items';

    protected $attributes = [
        'key_required' => true,
    ];

    protected $fillable = [
        'booklet_version_id',
        'booklet_item_uid',
        'booklet_section_uid',
        'booklet_section_id',
        'aptitude_area_id',
        'test_instrument_item_id',
        'authored_position',
        'option_tokens',
        'key_required',
        'encrypted_content',
        'correct_option_code',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'authored_position' => 'integer',
            'test_instrument_item_id' => 'integer',
            'option_tokens' => 'array',
            'key_required' => 'boolean',
            'encrypted_content' => 'encrypted:array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(OmrBookletSection::class, 'booklet_section_id');
    }

    public function getOptionTokensAttribute($value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }
        if (is_array($value) && $value !== []) {
            return $value;
        }
        $content = $this->encrypted_content;
        if (is_array($content) && is_array($content['choices'] ?? null) && ! empty($content['choices'])) {
            return array_keys($content['choices']);
        }

        return ['A', 'B', 'C', 'D'];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $item): void {
            if (self::hasSealedBookletVersion($item->getRawOriginal('booklet_version_id'))
                || self::hasSealedBookletVersion($item->booklet_version_id)) {
                throw new RuntimeException('Finalized booklet questions are immutable.');
            }
        });

        static::deleting(static function (self $item): void {
            if (self::hasSealedBookletVersion($item->getRawOriginal('booklet_version_id'))
                || self::hasSealedBookletVersion($item->booklet_version_id)) {
                throw new RuntimeException('Finalized booklet questions are immutable.');
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

    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class);
    }

    public function testInstrumentItem(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentItem::class, 'test_instrument_item_id');
    }

    public function setItems(): HasMany
    {
        return $this->hasMany(OmrBookletSetItem::class, 'booklet_item_id');
    }
}

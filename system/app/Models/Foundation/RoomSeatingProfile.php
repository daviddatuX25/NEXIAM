<?php

namespace App\Models\Foundation;

use Database\Factories\Foundation\RoomSeatingProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomSeatingProfile extends Model
{
    /** @use HasFactory<RoomSeatingProfileFactory> */
    use HasFactory;

    public const PATTERN_UNIFORM = 'uniform';

    public const PATTERN_CHECKERBOARD = 'checkerboard';

    public const PATTERN_LATIN_FOUR = 'latin_four';

    public const PATTERN_LINEAR_ALTERNATING = 'linear_alternating';

    public const PATTERNS = [
        self::PATTERN_UNIFORM,
        self::PATTERN_CHECKERBOARD,
        self::PATTERN_LATIN_FOUR,
        self::PATTERN_LINEAR_ALTERNATING,
    ];

    protected $fillable = [
        'room_id',
        'name',
        'normalized_name',
        'rows',
        'columns',
        'booklet_pattern',
        'is_active',
        'is_default',
    ];

    protected $appends = ['desk_count'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rows' => 'integer',
            'columns' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function getDeskCountAttribute(): int
    {
        return $this->rows * $this->columns;
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}

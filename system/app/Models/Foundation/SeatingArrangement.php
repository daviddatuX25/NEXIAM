<?php

namespace App\Models\Foundation;

use Database\Factories\Foundation\SeatingArrangementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingArrangement extends Model
{
    /** @use HasFactory<SeatingArrangementFactory> */
    use HasFactory;

    public const AVAILABILITY_ALL_ROOMS = 'all_rooms';

    public const AVAILABILITY_SELECTED_ROOMS = 'selected_rooms';

    /**
     * @var list<string>
     */
    public const AVAILABILITY_MODES = [
        self::AVAILABILITY_ALL_ROOMS,
        self::AVAILABILITY_SELECTED_ROOMS,
    ];

    public const PATTERN_UNIFORM = 'uniform';

    public const PATTERN_CHECKERBOARD = 'checkerboard';

    public const PATTERN_LATIN_FOUR = 'latin_four';

    public const PATTERN_LINEAR_ALTERNATING = 'linear_alternating';

    /**
     * @var list<string>
     */
    public const PATTERNS = [
        self::PATTERN_UNIFORM,
        self::PATTERN_CHECKERBOARD,
        self::PATTERN_LATIN_FOUR,
        self::PATTERN_LINEAR_ALTERNATING,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'normalized_name',
        'rows',
        'columns',
        'booklet_pattern',
        'availability_mode',
        'is_active',
        'lock_version',
        'legacy_room_seating_profile_id',
    ];

    /**
     * @var list<string>
     */
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
            'lock_version' => 'integer',
        ];
    }

    public function getDeskCountAttribute(): int
    {
        return $this->rows * $this->columns;
    }

    /**
     * @return BelongsToMany<Room, $this>
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'seating_arrangement_rooms')->withTimestamps();
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function defaultForRooms(): HasMany
    {
        return $this->hasMany(Room::class, 'default_seating_arrangement_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models\Foundation;

use Database\Factories\Foundation\RoomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'code',
        'name',
        'space_kind',
        'capacity',
        'latitude',
        'longitude',
        'building_name',
        'floor_level',
        'photo_path',
        'directions_hint',
        'is_active',
        'default_seating_arrangement_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'space_kind' => SpaceKind::class,
            'capacity' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return BelongsToMany<SeatingArrangement, $this>
     */
    public function seatingArrangements(): BelongsToMany
    {
        return $this->belongsToMany(SeatingArrangement::class, 'seating_arrangement_rooms')->withTimestamps();
    }

    /**
     * @return BelongsTo<SeatingArrangement, $this>
     */
    public function defaultSeatingArrangement(): BelongsTo
    {
        return $this->belongsTo(SeatingArrangement::class, 'default_seating_arrangement_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isExamRoom(): bool
    {
        return $this->space_kind === null || $this->space_kind === SpaceKind::Room;
    }

    public function allowsSeatingArrangement(): bool
    {
        return $this->space_kind === null || $this->space_kind->allowsSeatingArrangement();
    }

    public function hasSpatialCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfKind(Builder $query, SpaceKind|string $kind): Builder
    {
        return $query->where('space_kind', $kind instanceof SpaceKind ? $kind->value : $kind);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExamRooms(Builder $query): Builder
    {
        return $query->where('space_kind', SpaceKind::Room->value);
    }
}

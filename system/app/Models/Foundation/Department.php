<?php

namespace App\Models\Foundation;

use App\Models\Staff\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'institution_id',
        'code',
        'name',
        'dean_user_id',
        'office_room_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return BelongsTo<User, $this>
     */
    public function dean(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dean_user_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function officeRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'office_room_id');
    }

    /**
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * @return HasMany<StaffProfile, $this>
     */
    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
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

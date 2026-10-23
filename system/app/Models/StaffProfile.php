<?php

namespace App\Models;

use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class StaffProfile extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'user_id',
        'institution_id',
        'department_id',
        'office',
        'avatar_type',
        'avatar_preset',
        'avatar_path',
        'phone_extension',
        'room_number',
        'consultation_hours',
        'program_codes',
        'active_cycle_code',
        'status',
        'deactivated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'program_codes' => 'array',
            'deactivated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $profile): void {
            if ($profile->office !== 'program') {
                $profile->department_id = null;
                $profile->program_codes = [];
            }
        });
    }

    public function isAcademic(): bool
    {
        return $this->office === 'program';
    }

    /**
     * @return array{type: string, preset: ?string, url: ?string}
     */
    public function avatarProjection(): array
    {
        return [
            'type' => $this->avatar_type ?? 'initials',
            'preset' => $this->avatar_preset,
            'url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
        ];
    }

    /**
     * @return array{phone_extension: ?string, room_number: ?string, consultation_hours: ?string}
     */
    public function directoryProjection(): array
    {
        return [
            'phone_extension' => $this->phone_extension,
            'room_number' => $this->room_number,
            'consultation_hours' => $this->consultation_hours,
        ];
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<StaffAuthenticationState, $this>
     */
    public function authenticationState(): HasOne
    {
        return $this->hasOne(StaffAuthenticationState::class, 'user_id', 'user_id');
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProgramPublicProfileRevision extends Model
{
    protected $fillable = [
        'profile_id',
        'revision',
        'short_description',
        'description',
        'photos',
        'is_published',
        'operation',
        'actor_id',
        'actor_name',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /** @return BelongsTo<ProgramPublicProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProgramPublicProfile::class, 'profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Communications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProgramPublicProfile extends Model
{
    protected $fillable = [
        'program_id',
        'institution_id',
        'current_revision',
        'published_revision_id',
    ];

    /** @return HasMany<ProgramPublicProfileRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ProgramPublicProfileRevision::class, 'profile_id');
    }

    /** @return BelongsTo<ProgramPublicProfileRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(ProgramPublicProfileRevision::class, 'published_revision_id');
    }
}

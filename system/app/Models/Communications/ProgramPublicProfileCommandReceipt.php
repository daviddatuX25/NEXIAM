<?php

declare(strict_types=1);

namespace App\Models\Communications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProgramPublicProfileCommandReceipt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'request_id',
        'operation',
        'required_capability',
        'program_id',
        'profile_id',
        'expected_revision',
        'revision_id',
        'payload_hash',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'expected_revision' => 'integer',
            'result' => 'array',
        ];
    }

    /** @return BelongsTo<ProgramPublicProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProgramPublicProfile::class, 'profile_id');
    }

    /** @return BelongsTo<ProgramPublicProfileRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ProgramPublicProfileRevision::class, 'revision_id');
    }
}

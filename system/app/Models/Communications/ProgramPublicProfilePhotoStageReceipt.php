<?php

declare(strict_types=1);

namespace App\Models\Communications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProgramPublicProfilePhotoStageReceipt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'program_id',
        'photo_id',
        'request_id',
        'required_capability',
        'source_sha256',
        'payload_hash',
        'result',
    ];

    protected function casts(): array
    {
        return ['result' => 'array'];
    }

    /** @return BelongsTo<ProgramPublicProfilePhoto, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(ProgramPublicProfilePhoto::class, 'photo_id');
    }
}

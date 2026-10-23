<?php

declare(strict_types=1);

namespace App\Models\Communications;

use App\Models\Foundation\Program;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProgramPublicProfilePhoto extends Model
{
    public const STATUS_READY = 'ready';

    public const UPDATED_AT = null;

    protected $fillable = [
        'program_id',
        'institution_id',
        'storage_disk',
        'storage_path',
        'sha256',
        'mime_type',
        'byte_size',
        'width',
        'height',
        'status',
        'created_by',
    ];

    protected $hidden = [
        'storage_disk',
        'storage_path',
        'sha256',
    ];

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}

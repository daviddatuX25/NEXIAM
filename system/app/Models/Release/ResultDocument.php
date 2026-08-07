<?php

namespace App\Models\Release;

use App\Models\Admission\AdmissionAttempt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultDocument extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    protected $fillable = [
        'admission_attempt_id',
        'template_version',
        'checksum',
        'status',
        'storage_path',
        'preview_path',
        'version',
    ];

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    /**
     * @return HasMany<ResultRelease, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(ResultRelease::class);
    }
}

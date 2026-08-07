<?php

namespace App\Models\Release;

use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultRelease extends Model
{
    protected $fillable = [
        'admission_attempt_id',
        'result_document_id',
        'released_by_user_id',
        'release_version',
        'is_current',
        'released_at',
        'unreleased_at',
        'unrelease_reason',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'released_at' => 'datetime',
            'unreleased_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    /**
     * @return BelongsTo<ResultDocument, $this>
     */
    public function resultDocument(): BelongsTo
    {
        return $this->belongsTo(ResultDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }
}

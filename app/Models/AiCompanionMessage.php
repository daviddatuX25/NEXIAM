<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCompanionMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public $timestamps = false;

    protected $fillable = ['applicant_id', 'role', 'content', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Scope: last N messages for an applicant (newest first). Caller should reverse for chronological API order.
     */
    public function scopeLastForApplicant($query, int $applicantId, int $limit = 20)
    {
        return $query
            ->where('applicant_id', $applicantId)
            ->orderByDesc('created_at')
            ->limit($limit);
    }
}

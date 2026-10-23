<?php

namespace App\Models\Assessment;

use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrPaperReproductionAuthorization extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_VOID = 'void';

    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'used_claims' => 0,
        'lock_version' => 0,
    ];

    protected $fillable = [
        'exam_session_id',
        'blank_instance_page_id',
        'maximum_claims',
        'used_claims',
        'status',
        'reason',
        'expires_at',
        'lock_version',
        'authorized_by_user_id',
        'reconciled_by_user_id',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'exam_session_id' => 'integer',
            'blank_instance_page_id' => 'integer',
            'maximum_claims' => 'integer',
            'used_claims' => 'integer',
            'lock_version' => 'integer',
            'expires_at' => 'datetime',
            'authorized_by_user_id' => 'integer',
            'reconciled_by_user_id' => 'integer',
            'reconciled_at' => 'datetime',
        ];
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function blankInstancePage(): BelongsTo
    {
        return $this->belongsTo(OmrBlankInstancePage::class, 'blank_instance_page_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(OmrBlankInstancePageClaim::class, 'reproduction_authorization_id');
    }

    public function hasRemainingClaims(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->expires_at->isFuture()
            && $this->used_claims < $this->maximum_claims;
    }
}

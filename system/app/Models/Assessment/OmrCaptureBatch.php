<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrCaptureBatch extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSING = 'closing';

    public const STATUS_REJECTING = 'rejecting';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const ACTIVE_STATUSES = [self::STATUS_OPEN, self::STATUS_CLOSING, self::STATUS_REJECTING];

    protected $fillable = [
        'grading_session_id',
        'template_version_id',
        'booklet_version_id',
        'status',
        'version',
        'uploaded_by_user_id',
        'opened_by_user_id',
        'closing_by_user_id',
        'rejecting_by_user_id',
        'confirmed_by_user_id',
        'opened_at',
        'closing_at',
        'rejecting_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'opened_at' => 'datetime',
            'closing_at' => 'datetime',
            'rejecting_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function gradingSession(): BelongsTo
    {
        return $this->belongsTo(GradingSession::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    public function bookletVersion(): BelongsTo
    {
        return $this->belongsTo(OmrQuestionBookletVersion::class, 'booklet_version_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closingBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closing_by_user_id');
    }

    public function rejectingBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejecting_by_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function sheets(): HasMany
    {
        return $this->hasMany(OmrCaptureSheet::class, 'batch_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isImmutable(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_REJECTED], true);
    }
}

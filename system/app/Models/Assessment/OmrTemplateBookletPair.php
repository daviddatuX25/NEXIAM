<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrTemplateBookletPair extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RETIRED = 'retired';

    protected $table = 'omr_template_booklet_pairs';

    protected $attributes = ['status' => self::STATUS_ACTIVE];

    protected $fillable = ['template_version_id', 'booklet_version_id', 'status', 'approved_by_user_id', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    public function bookletVersion(): BelongsTo
    {
        return $this->belongsTo(OmrQuestionBookletVersion::class, 'booklet_version_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->templateVersion?->isUsable() === true
            && $this->bookletVersion?->isUsable() === true;
    }
}

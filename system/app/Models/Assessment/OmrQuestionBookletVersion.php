<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrQuestionBookletVersion extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RETIRED = 'retired';

    protected $table = 'omr_question_booklet_versions';

    protected $attributes = ['status' => self::STATUS_DRAFT];

    protected $fillable = ['booklet_id', 'version', 'status', 'answer_key_hash', 'activated_by_user_id', 'activated_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'activated_at' => 'datetime'];
    }

    public function booklet(): BelongsTo
    {
        return $this->belongsTo(OmrQuestionBooklet::class, 'booklet_id');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(OmrQuestionBookletQuestion::class, 'booklet_version_id');
    }

    public function pairs(): HasMany
    {
        return $this->hasMany(OmrTemplateBookletPair::class, 'booklet_version_id');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}

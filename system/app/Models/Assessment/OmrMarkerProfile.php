<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrMarkerProfile extends Model
{
    public const STATUS_CANDIDATE = 'candidate';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_RETIRED = 'retired';

    protected $table = 'omr_marker_profiles';

    protected $attributes = [
        'status' => self::STATUS_CANDIDATE,
        'matching_config' => '{}',
    ];

    protected $fillable = [
        'code', 'version', 'family', 'asset_path', 'asset_sha256',
        'size_mm', 'quiet_zone_mm', 'matching_config', 'status',
        'qualification_evidence_hash', 'created_by_user_id',
        'qualified_by_user_id', 'qualified_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size_mm' => 'decimal:3',
            'quiet_zone_mm' => 'decimal:3',
            'matching_config' => 'array',
            'qualified_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function qualifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qualified_by_user_id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(OmrTemplateAuthoringDraft::class, 'marker_profile_id');
    }

    public function isQualified(): bool
    {
        return $this->status === self::STATUS_QUALIFIED;
    }
}

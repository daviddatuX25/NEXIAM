<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical challenge pack bound to one run attempt (OpenSpec 35.21).
 * Sheet/guide assets are real FK references into the private asset store.
 * Challenge creation/rendering lands in Cut F; this model and its table
 * are the schema contract for that cut.
 */
class OmrPhysicalChallenge extends Model
{
    public const MODE_QUICK = 'quick';
    public const MODE_FULL = 'full';

    public const STATUS_CREATED = 'created';
    public const STATUS_PRINTED = 'printed';
    public const STATUS_AWAITING_CAPTURE = 'awaiting_capture';
    public const STATUS_ANALYZING_CAPTURE = 'analyzing_capture';
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $table = 'omr_physical_challenges';

    protected $attributes = [
        'status' => self::STATUS_CREATED,
    ];

    protected $fillable = [
        'run_id', 'attempt', 'mode', 'challenge_code', 'plan_hash',
        'expected_vector_hash', 'sheet_asset_id', 'guide_asset_id',
        'qr_payload_hash', 'status', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'run_id' => 'integer',
            'attempt' => 'integer',
            'sheet_asset_id' => 'integer',
            'guide_asset_id' => 'integer',
            'created_by_user_id' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateVerificationRun::class, 'run_id');
    }

    public function sheetAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'sheet_asset_id');
    }

    public function guideAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'guide_asset_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function captures(): HasMany
    {
        return $this->hasMany(OmrTemplateVerificationCapture::class, 'challenge_id');
    }
}

<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One normalized verification capture (OpenSpec 35.21, AD-5).
 *
 * Read-only evidence row for a digital matrix variant or a physical capture
 * submission: input/aligned/overlay asset references, hashes, expected vs
 * detected vectors, safe summary. No applicant binding and no score command.
 */
class OmrTemplateVerificationCapture extends Model
{
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';

    public const ACTUAL_PASS = 'pass';
    public const ACTUAL_TYPED_REJECTION = 'typed_rejection';
    public const ACTUAL_ANSWER_VECTOR_MISMATCH = 'answer_vector_mismatch';
    public const ACTUAL_FAILED = 'failed';

    protected $table = 'omr_template_verification_captures';

    protected $fillable = [
        'run_id', 'challenge_id', 'attempt', 'page_no', 'mode', 'variant',
        'expected', 'actual', 'status', 'error_code', 'input_hash',
        'input_asset_id', 'aligned_asset_id', 'overlay_asset_id',
        'evidence_hash', 'expected_vector_hash', 'detected_vector_hash',
        'vector_match', 'summary', 'captured_by_user_id', 'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'run_id' => 'integer',
            'challenge_id' => 'integer',
            'attempt' => 'integer',
            'page_no' => 'integer',
            'input_asset_id' => 'integer',
            'aligned_asset_id' => 'integer',
            'overlay_asset_id' => 'integer',
            'vector_match' => 'boolean',
            'summary' => 'array',
            'captured_by_user_id' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateVerificationRun::class, 'run_id');
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(OmrPhysicalChallenge::class, 'challenge_id');
    }

    public function inputAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'input_asset_id');
    }

    public function alignedAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'aligned_asset_id');
    }

    public function overlayAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'overlay_asset_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function isPass(): bool
    {
        return $this->status === self::STATUS_PASS;
    }
}

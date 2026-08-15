<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrProcessingRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_RETRYABLE_FAILED = 'retryable_failed';
    public const STATUS_TERMINAL_FAILED = 'terminal_failed';

    protected $table = 'omr_processing_runs';

    protected $fillable = [
        'sheet_id', 'actor_id', 'run_no', 'run_uuid', 'status',
        'provider_name', 'provider_version', 'provider_commit', 'request_version',
        'expected_sheet_version', 'input_hash', 'template_hash', 'booklet_hash',
        'native_bundle_hash', 'manifest_hash', 'config_hash', 'patch_identity',
        'page_no', 'response_version', 'claim_token', 'claimed_at', 'claim_expires_at',
        'attempts', 'next_retry_at', 'diagnostic_code', 'duration_ms',
        'timings_json', 'output_json',
    ];

    protected function casts(): array
    {
        return [
            'run_no' => 'integer',
            'expected_sheet_version' => 'integer',
            'claimed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
            'page_no' => 'integer',
            'attempts' => 'integer',
            'next_retry_at' => 'datetime',
            'duration_ms' => 'integer',
            'timings_json' => 'array',
            'output_json' => 'array',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheet::class, 'sheet_id');

    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CLAIMED, self::STATUS_RETRYABLE_FAILED], true);
    }
}

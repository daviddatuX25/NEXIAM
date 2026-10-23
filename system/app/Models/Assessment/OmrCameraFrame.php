<?php

declare(strict_types=1);

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OmrCameraFrame extends Model
{
    public const STATE_ACQUIRED = 'acquired';

    public const STATE_UPLOADING = 'uploading';

    public const STATE_STAGED = 'staged';

    public const STATE_FINALIZING = 'finalizing';

    public const STATE_CONSUMED = 'consumed';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_EXPIRED = 'expired';

    protected $fillable = [
        'public_id', 'pairing_id', 'command_id', 'node_generation', 'target_json', 'target_key', 'config_revision',
        'source_generation', 'input_sha256', 'byte_count', 'mime_type', 'width', 'height', 'orientation', 'client_quad_json',
        'asset_disk', 'asset_path', 'asset_hmac', 'asset_deleted_at', 'preview_mime', 'state', 'acquired_at', 'upload_expires_at', 'staged_at',
        'finalization_fingerprint', 'finalization_lease_token', 'finalization_lease_expires_at', 'finalization_absolute_expires_at',
        'domain_receipt_json', 'consumed_at', 'cancelled_at',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $frame): void {
            if (in_array($frame->state, [self::STATE_UPLOADING, self::STATE_STAGED], true)) {
                if ($frame->asset_disk !== 'sensitive' || ! is_string($frame->asset_path)
                    || ! preg_match('#^omr/camera/[0-9a-f-]{36}/[0-9a-f-]{36}$#i', $frame->asset_path)
                    || ! is_string($frame->asset_hmac) || ! preg_match('/^[a-f0-9]{64}$/i', $frame->asset_hmac)) {
                    throw new \LogicException('Uploading or staged OMR camera frames require an encrypted private asset and HMAC.');
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'target_json' => 'array', 'client_quad_json' => 'array', 'domain_receipt_json' => 'array',
            'asset_deleted_at' => 'immutable_datetime', 'acquired_at' => 'immutable_datetime',
            'upload_expires_at' => 'immutable_datetime', 'staged_at' => 'immutable_datetime',
            'finalization_lease_expires_at' => 'immutable_datetime', 'finalization_absolute_expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
            'byte_count' => 'integer', 'width' => 'integer', 'height' => 'integer',
            'orientation' => 'integer', 'config_revision' => 'integer', 'source_generation' => 'integer', 'node_generation' => 'integer',
        ];
    }

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(OmrCameraPairing::class, 'pairing_id');
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(OmrCameraCommand::class, 'command_id');
    }
}

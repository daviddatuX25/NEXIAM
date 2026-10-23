<?php

declare(strict_types=1);

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class OmrCameraCommand extends Model
{
    protected $fillable = [
        'pairing_id', 'operation_id', 'command_id', 'issuer_role', 'sequence', 'kind', 'workbench_key',
        'actor_id', 'receipt_key', 'payload_json', 'payload_fingerprint', 'target_json', 'config_revision', 'source_generation',
        'controller_generation', 'issued_at', 'execution_expires_at', 'device_outcome', 'device_outcome_json',
        'domain_outcome', 'domain_receipt_json', 'finalization_lease_token', 'finalization_lease_expires_at',
        'finalization_absolute_expires_at', 'finalization_lease_generation', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array', 'target_json' => 'array', 'device_outcome_json' => 'array',
            'domain_receipt_json' => 'array', 'issued_at' => 'immutable_datetime',
            'execution_expires_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
            'finalization_lease_expires_at' => 'immutable_datetime', 'finalization_absolute_expires_at' => 'immutable_datetime',
            'controller_generation' => 'integer',
            'finalization_lease_generation' => 'integer',
        ];
    }

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(OmrCameraPairing::class, 'pairing_id');
    }

    public function frame(): HasOne
    {
        return $this->hasOne(OmrCameraFrame::class, 'command_id');
    }
}

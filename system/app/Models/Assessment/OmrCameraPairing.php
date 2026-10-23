<?php

declare(strict_types=1);

namespace App\Models\Assessment;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OmrCameraPairing extends Model
{
    protected $fillable = [
        'public_id', 'operator_id', 'institution_id', 'academic_cycle_id', 'production_batch_id', 'diagnostic_run_id',
        'workbench_id', 'workbench_key', 'controller_generation', 'controller_token_hash', 'controller_heartbeat_at',
        'node_id', 'node_token_hash', 'node_generation', 'node_heartbeat_at', 'pair_code_hash', 'pair_code_expires_at',
        'pair_code_consumed_at', 'absolute_expires_at', 'revoked_at', 'revoke_reason', 'config_json', 'config_revision',
        'source_generation', 'target_generation', 'active_lease_key', 'observation_sequence', 'observations_json',
        'signals_json', 'last_ack_json',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array', 'observations_json' => 'array', 'signals_json' => 'array', 'last_ack_json' => 'array',
            'pair_code_expires_at' => 'immutable_datetime', 'pair_code_consumed_at' => 'immutable_datetime',
            'absolute_expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime',
            'controller_heartbeat_at' => 'immutable_datetime', 'node_heartbeat_at' => 'immutable_datetime',
            'controller_generation' => 'integer', 'node_generation' => 'integer', 'config_revision' => 'integer',
            'source_generation' => 'integer', 'target_generation' => 'integer', 'observation_sequence' => 'integer',
        ];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureBatch::class, 'production_batch_id');
    }

    public function diagnosticRun(): BelongsTo
    {
        return $this->belongsTo(OmrDiagnosticRun::class, 'diagnostic_run_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(OmrCameraCommand::class, 'pairing_id');
    }

    public function frames(): HasMany
    {
        return $this->hasMany(OmrCameraFrame::class, 'pairing_id');
    }

    public function isLive(): bool
    {
        return $this->revoked_at === null && $this->absolute_expires_at?->isFuture() === true;
    }
}

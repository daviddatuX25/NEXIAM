<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Camera;

use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class OmrCameraProtocol
{
    /** @param array<string, mixed> $target */
    public function create(User $actor, string $workbenchId, int $institutionId, int $cycleId, ?int $batchId, ?int $runId, array $target, bool $takeover = false, ?int $expectedGeneration = null): array
    {
        if (($batchId === null) === ($runId === null)) {
            throw new RuntimeException('omr_camera_workbench_context_invalid');
        }

        return DB::transaction(function () use ($actor, $workbenchId, $institutionId, $cycleId, $batchId, $runId, $target, $takeover, $expectedGeneration): array {
            if ($batchId !== null) {
                DB::table('omr_capture_batches')->where('id', $batchId)->lockForUpdate()->first();
            } else {
                $this->lockDiagnosticRun((int) $runId);
            }
            $workbenchKey = hash('sha256', $workbenchId);
            OmrCameraPairing::query()->where('workbench_key', $workbenchKey)->whereNull('revoked_at')
                ->where('absolute_expires_at', '<=', now())->lockForUpdate()
                ->update(['revoked_at' => now(), 'revoke_reason' => 'absolute_expiry', 'active_lease_key' => null]);
            $live = OmrCameraPairing::query()->where('workbench_key', $workbenchKey)->whereNull('revoked_at')
                ->where('absolute_expires_at', '>', now())->lockForUpdate()->first();
            $controllerGeneration = 1;
            if ($live !== null) {
                abort_unless($takeover && $expectedGeneration === (int) $live->controller_generation, 409);
                $controllerGeneration = (int) $live->controller_generation + 1;
                $live->forceFill(['revoked_at' => now(), 'revoke_reason' => 'explicit_controller_takeover', 'active_lease_key' => null,
                    'controller_generation' => $controllerGeneration, 'node_generation' => (int) $live->node_generation + 1,
                    'controller_token_hash' => null, 'node_token_hash' => null])->save();
            } elseif ($takeover) {
                abort(409);
            }

            $pairCode = strtoupper(Str::random(6));
            $controllerToken = Str::random(64);
            $id = (string) Str::uuid();
            $pairing = OmrCameraPairing::query()->create([
                'public_id' => $id,
                'operator_id' => (int) $actor->getAuthIdentifier(),
                'institution_id' => $institutionId,
                'academic_cycle_id' => $cycleId,
                'production_batch_id' => $batchId,
                'diagnostic_run_id' => $runId,
                'workbench_id' => $workbenchId,
                'workbench_key' => $workbenchKey,
                'controller_generation' => $controllerGeneration,
                'controller_token_hash' => hash('sha256', $controllerToken),
                'controller_heartbeat_at' => now(),
                'pair_code_hash' => hash('sha256', $pairCode),
                'pair_code_expires_at' => now()->addSeconds((int) config('omr-camera.pair_code_ttl_seconds', 300)),
                'absolute_expires_at' => now()->addSeconds((int) config('omr-camera.pairing_ttl_seconds', 3600)),
                'config_json' => ['mode' => 'manual', 'batchTrigger' => 'auto_steady', 'steadyDelayMs' => 300,
                    'target' => $target, 'configRevision' => 1, 'sourceGeneration' => 1],
                'config_revision' => 1,
                'source_generation' => 1,
                'target_generation' => 1,
                'active_lease_key' => $workbenchKey,
                'observations_json' => [],
                'signals_json' => [],
            ]);

            return ['pairing' => $pairing, 'pair_code' => $pairCode, 'controller_token' => $controllerToken];
        }, 3);
    }

    /** @param array<string, mixed> $target */
    public function persistTargetGrant(User $actor, string $workbenchId, array $target): OmrCameraCommand
    {
        $workbenchKey = hash('sha256', $workbenchId);
        $targetKey = (string) ($target['targetKey'] ?? '');
        $expectedKey = json_encode([
            $target['workbenchId'] ?? null, $target['clientCaptureId'] ?? null, $target['pageNo'] ?? null,
            $target['captureRevision'] ?? null, $target['instanceId'] ?? null, $target['sheetId'] ?? null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        abort_unless($targetKey !== '' && $targetKey === $expectedKey, 422);
        $receiptKey = hash('sha256', 'target|'.$actor->id.'|'.$workbenchKey.'|'.$targetKey);
        $fingerprint = $this->targetGrantFingerprint($target);

        return DB::transaction(function () use ($actor, $workbenchId, $workbenchKey, $target, $receiptKey, $fingerprint): OmrCameraCommand {
            $this->lockDiagnosticWorkbench($workbenchId);
            $grant = OmrCameraCommand::query()->where('receipt_key', $receiptKey)->lockForUpdate()->first();
            if ($grant !== null) {
                abort_unless(hash_equals($this->targetGrantFingerprint((array) $grant->target_json), $fingerprint), 409);

                return $grant;
            }

            return OmrCameraCommand::query()->create([
                'pairing_id' => null, 'operation_id' => (string) Str::uuid(), 'command_id' => (string) Str::uuid(),
                'issuer_role' => 'controller', 'sequence' => null, 'kind' => 'CAPTURE_TARGET_GRANT',
                'workbench_key' => $workbenchKey, 'actor_id' => $actor->id, 'receipt_key' => $receiptKey,
                'payload_json' => ['workbench_id' => $workbenchId], 'payload_fingerprint' => $fingerprint,
                'target_json' => $target, 'issued_at' => now(), 'domain_outcome' => 'granted',
            ]);
        }, 3);
    }

    private function lockDiagnosticWorkbench(string $workbenchId): void
    {
        if (! Str::startsWith($workbenchId, 'diagnostic:')) {
            return;
        }

        $publicId = substr($workbenchId, strlen('diagnostic:'));
        $run = OmrDiagnosticRun::query()->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
        $this->assertDiagnosticRunNotCancelled($run);
    }

    private function lockDiagnosticRun(int $runId): void
    {
        $run = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($runId);
        $this->assertDiagnosticRunNotCancelled($run);
    }

    private function assertDiagnosticRunNotCancelled(OmrDiagnosticRun $run): void
    {
        abort_unless($run->status !== OmrDiagnosticRun::STATUS_CANCELLED, 409, 'omr_diagnostic_run_terminal');
    }

    /** @param array<string, mixed> $target */
    private function targetGrantFingerprint(array $target): string
    {
        $authority = [];
        foreach ([
            'workbenchId', 'clientCaptureId', 'pageNo', 'captureRevision', 'instanceId', 'sheetId',
            'execution_kind', 'session_id', 'batch_id', 'run_id', 'template_version_id', 'booklet_version_id',
            'batch_version', 'registration_mode', 'qr_mode', 'page_count', 'material_identity',
            'diagnostic_purpose', 'pending_capture_id', 'pending_capture_revision', 'instance_no', 'instance_count',
            'supplier_stock_allocation_id', 'identity_mode', 'redo_authorization_id', 'redo_reason',
            'redo_operation_id', 'redo_source_page_id', 'redo_source_page_revision', 'redo_source_evidence_id',
            'redo_accepted_target_key', 'replaces_capture_id',
        ] as $field) {
            $authority[$field] = $target[$field] ?? null;
        }

        return hash('sha256', json_encode(Arr::sortRecursive($authority),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed>|null */
    public function findTargetGrant(User $actor, string $workbenchId, string $targetKey): ?array
    {
        $workbenchKey = hash('sha256', $workbenchId);
        $receiptKey = hash('sha256', 'target|'.$actor->id.'|'.$workbenchKey.'|'.$targetKey);
        $grant = OmrCameraCommand::query()->where('receipt_key', $receiptKey)->where('kind', 'CAPTURE_TARGET_GRANT')
            ->where('actor_id', $actor->id)->where('workbench_key', $workbenchKey)->first();

        return $grant === null ? null : (array) $grant->target_json;
    }

    public function findTargetGrantByAuthorization(User $actor, string $workbenchId, string $authorizationId): ?array
    {
        $grant = OmrCameraCommand::query()
            ->where('kind', 'CAPTURE_TARGET_GRANT')
            ->where('actor_id', $actor->id)
            ->where('workbench_key', hash('sha256', $workbenchId))
            ->where('target_json->redo_authorization_id', $authorizationId)
            ->first();

        return $grant === null ? null : (array) $grant->target_json;
    }

    public function join(User $actor, string $code): array
    {
        return DB::transaction(function () use ($actor, $code): array {
            $pairing = OmrCameraPairing::query()->where('pair_code_hash', hash('sha256', strtoupper($code)))
                ->lockForUpdate()->first();
            if ($pairing === null || ! $pairing->isLive() || $pairing->pair_code_consumed_at !== null
                || $pairing->pair_code_expires_at?->isPast() || (int) $pairing->operator_id !== (int) $actor->getAuthIdentifier()) {
                throw new RuntimeException('omr_camera_pair_code_invalid');
            }
            $nodeToken = Str::random(64);
            $sourceGeneration = (int) $pairing->source_generation + 1;
            $nodeGeneration = (int) $pairing->node_generation + 1;
            $config = (array) $pairing->config_json;
            $config['sourceGeneration'] = $sourceGeneration;
            $config['configRevision'] = (int) $pairing->config_revision;
            $pairing->forceFill([
                'pair_code_consumed_at' => now(), 'pair_code_hash' => null, 'node_id' => (string) Str::uuid(),
                'node_token_hash' => hash('sha256', $nodeToken), 'node_generation' => $nodeGeneration,
                'node_heartbeat_at' => now(), 'source_generation' => $sourceGeneration, 'config_json' => $config,
            ])->save();

            return ['pairing' => $pairing->fresh(), 'node_token' => $nodeToken];
        }, 3);
    }

    /** @param array<string, mixed>|null $target */
    public function reissueNodePairCode(OmrCameraPairing $pairing, ?array $target = null): string
    {
        return DB::transaction(function () use ($pairing, $target): string {
            $locked = OmrCameraPairing::query()->whereKey($pairing->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isLive(), 410);
            $code = strtoupper(Str::random(6));
            $sourceGeneration = (int) $locked->source_generation + 1;
            $nodeGeneration = (int) $locked->node_generation + 1;
            $config = (array) $locked->config_json;
            if ($target !== null) {
                $oldTargetKey = (string) ($config['target']['targetKey'] ?? '');
                $newTargetKey = (string) ($target['targetKey'] ?? '');
                abort_unless($newTargetKey !== '', 422);
                if ($newTargetKey !== $oldTargetKey) {
                    $locked->target_generation = (int) $locked->target_generation + 1;
                }
                $config['target'] = $target;
            }
            $config['sourceGeneration'] = $sourceGeneration;
            $config['configRevision'] = (int) $locked->config_revision;
            $locked->forceFill([
                'pair_code_hash' => hash('sha256', $code),
                'pair_code_expires_at' => now()->addSeconds((int) config('omr-camera.pair_code_ttl_seconds', 300)),
                'pair_code_consumed_at' => null, 'node_id' => null, 'node_token_hash' => null,
                'node_generation' => $nodeGeneration, 'node_heartbeat_at' => null, 'source_generation' => $sourceGeneration,
                'config_json' => $config, 'signals_json' => [],
            ])->save();

            return $code;
        }, 3);
    }

    public function authenticate(User $actor, string $id, string $role, string $token, int $generation): OmrCameraPairing
    {
        $pairing = OmrCameraPairing::query()->where('public_id', $id)->firstOrFail();
        abort_unless($pairing->isLive() && (int) $pairing->operator_id === (int) $actor->getAuthIdentifier(), 404);
        $this->assertStoredToken($pairing, $role, $token, $generation);

        return $pairing;
    }

    public function assertStoredToken(OmrCameraPairing $pairing, string $role, string $token, int $generation): void
    {
        $expected = match ($role) {
            'controller' => $pairing->controller_token_hash,
            'node' => $pairing->node_token_hash,
            default => null,
        };
        $storedGeneration = $role === 'controller' ? (int) $pairing->controller_generation : (int) $pairing->node_generation;
        abort_unless(is_string($expected) && hash_equals($expected, hash('sha256', $token)) && $generation === $storedGeneration, 403);
        abort_unless($role === 'controller' || $pairing->node_id !== null, 403);
    }

    /** @param array<string, mixed> $payload */
    public function command(OmrCameraPairing $pairing, string $kind, string $commandId, string $operationId, array $payload): OmrCameraCommand
    {
        return DB::transaction(function () use ($pairing, $kind, $commandId, $operationId, $payload): OmrCameraCommand {
            if ($pairing->diagnostic_run_id !== null) {
                $this->lockDiagnosticRun((int) $pairing->diagnostic_run_id);
            }
            $locked = OmrCameraPairing::query()->whereKey($pairing->id)->lockForUpdate()->firstOrFail();
            $canonical = ['kind' => $kind, 'command_id' => $commandId, 'operation_id' => $operationId, 'payload' => $payload];
            $fingerprint = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $old = OmrCameraCommand::query()->where('pairing_id', $locked->id)->where('command_id', $commandId)->first();
            if ($old !== null) {
                abort_unless(hash_equals((string) $old->payload_fingerprint, $fingerprint), 409);

                return $old;
            }
            abort_unless((int) ($payload['config_revision'] ?? 0) === (int) $locked->config_revision
                && (int) ($payload['source_generation'] ?? 0) === (int) $locked->source_generation, 409);
            $sequence = (int) $locked->commands()->max('sequence') + 1;
            $revision = (int) $locked->config_revision;
            $currentTarget = (array) ($locked->config_json['target'] ?? []);
            $nextTarget = (array) ($payload['target'] ?? []);
            if (($nextTarget['targetKey'] ?? null) !== ($currentTarget['targetKey'] ?? null)) {
                $locked->target_generation = (int) $locked->target_generation + 1;
                $locked->source_generation = (int) $locked->source_generation + 1;
            }
            $config = (array) $locked->config_json;
            $config['target'] = $nextTarget ?: $currentTarget;
            if ($kind === 'SET_CONFIG') {
                $config = array_replace($config, (array) ($payload['config'] ?? []));
                $revision++;
                $locked->config_revision = $revision;
                $locked->source_generation = (int) $locked->source_generation + 1;
            }
            $config['configRevision'] = $revision;
            $config['sourceGeneration'] = (int) $locked->source_generation;
            $locked->config_json = $config;
            $locked->save();

            return $locked->commands()->create([
                'operation_id' => $operationId, 'command_id' => $commandId, 'issuer_role' => 'controller',
                'sequence' => $sequence, 'kind' => $kind, 'workbench_key' => $locked->workbench_key,
                'actor_id' => $locked->operator_id, 'payload_json' => $canonical, 'payload_fingerprint' => $fingerprint,
                'target_json' => $config['target'], 'config_revision' => $revision,
                'source_generation' => $locked->source_generation,
                'controller_generation' => $locked->controller_generation, 'issued_at' => now(),
                'execution_expires_at' => $kind === 'CAPTURE' ? now()->addSeconds((int) config('omr-camera.command_execution_ttl_seconds', 10)) : null,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $ack */
    public function acknowledgeAcquired(OmrCameraPairing $pairing, array $ack): OmrCameraFrame
    {
        return DB::transaction(function () use ($pairing, $ack): OmrCameraFrame {
            if ($pairing->diagnostic_run_id !== null) {
                $this->lockDiagnosticRun((int) $pairing->diagnostic_run_id);
            }
            $locked = OmrCameraPairing::query()->whereKey($pairing->id)->lockForUpdate()->firstOrFail();
            $command = $locked->commands()->where('command_id', $ack['command_id'])->lockForUpdate()->firstOrFail();
            abort_unless($command->kind === 'CAPTURE'
                && (int) $command->source_generation === (int) $ack['source_generation']
                && (int) $command->config_revision === (int) $ack['config_revision'], 409);
            $frame = OmrCameraFrame::query()->where('command_id', $command->id)->lockForUpdate()->first();
            if ($frame !== null) {
                abort_unless(hash_equals((string) $frame->input_sha256, (string) $ack['input_hash'])
                    && (int) $frame->byte_count === (int) $ack['byte_count']
                    && (string) $frame->mime_type === (string) $ack['mime']
                    && (int) $frame->width === (int) $ack['width']
                    && (int) $frame->height === (int) $ack['height']
                    && (int) $frame->orientation === (int) ($ack['orientation'] ?? 1)
                    && (string) $frame->target_key === (string) $ack['target_key']
                    && (int) $frame->node_generation === (int) $locked->node_generation, 409);

                return $frame;
            }
            abort_unless($command->execution_expires_at?->isFuture()
                && (int) $command->controller_generation === (int) $locked->controller_generation
                && (int) $command->source_generation === (int) $locked->source_generation, 409);
            $target = (array) $command->target_json;
            abort_unless(hash_equals((string) ($target['targetKey'] ?? $target['target_key'] ?? ''), (string) $ack['target_key']), 409);
            $frame = OmrCameraFrame::query()->create([
                'public_id' => (string) Str::uuid(), 'pairing_id' => $locked->id, 'command_id' => $command->id,
                'node_generation' => $locked->node_generation, 'target_json' => $target, 'target_key' => $ack['target_key'],
                'config_revision' => $command->config_revision, 'source_generation' => $command->source_generation,
                'input_sha256' => $ack['input_hash'], 'byte_count' => $ack['byte_count'], 'mime_type' => $ack['mime'],
                'width' => $ack['width'], 'height' => $ack['height'], 'orientation' => $ack['orientation'] ?? 1,
                'state' => OmrCameraFrame::STATE_ACQUIRED, 'acquired_at' => now(),
                'upload_expires_at' => now()->addSeconds((int) config('omr-camera.frame_upload_ttl_seconds', 600)),
            ]);
            $command->forceFill(['device_outcome' => 'acquired', 'device_outcome_json' => $ack])->save();
            $locked->forceFill(['last_ack_json' => ['command_id' => $command->command_id, 'status' => 'acquired', 'frame_id' => $frame->public_id]])->save();

            return $frame;
        }, 3);
    }

    /** @param array<string, mixed> $ack */
    public function acknowledgeApplied(OmrCameraPairing $pairing, array $ack): OmrCameraCommand
    {
        return DB::transaction(function () use ($pairing, $ack): OmrCameraCommand {
            if ($pairing->diagnostic_run_id !== null) {
                $this->lockDiagnosticRun((int) $pairing->diagnostic_run_id);
            }
            $locked = OmrCameraPairing::query()->whereKey($pairing->id)->lockForUpdate()->firstOrFail();
            $command = $locked->commands()->where('command_id', $ack['command_id'])->lockForUpdate()->firstOrFail();
            abort_unless(in_array($command->kind, ['SET_CONFIG', 'PAUSE', 'STOP'], true)
                && (int) $command->controller_generation === (int) $locked->controller_generation
                && (int) $command->source_generation === (int) $ack['source_generation']
                && (int) $command->config_revision === (int) $ack['config_revision']
                && (int) $locked->source_generation === (int) $ack['source_generation']
                && (int) $locked->config_revision === (int) $ack['config_revision']
                && (! isset($ack['target_key']) || hash_equals(
                    (string) ($command->target_json['targetKey'] ?? ''), (string) $ack['target_key'])), 409);
            if ($command->device_outcome !== null) {
                $stored = (array) $command->device_outcome_json;
                abort_unless($command->device_outcome === 'applied'
                    && hash_equals(hash('sha256', json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                        hash('sha256', json_encode($ack, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))), 409);

                return $command;
            }
            $command->forceFill(['device_outcome' => 'applied', 'device_outcome_json' => $ack])->save();
            $locked->forceFill(['last_ack_json' => ['command_id' => $command->command_id, 'status' => 'applied',
                'source_generation' => (int) $ack['source_generation'], 'config_revision' => (int) $ack['config_revision']]])->save();

            return $command;
        }, 3);
    }

    /** @param array<string, mixed> $ack */
    public function acknowledgeRejected(OmrCameraPairing $pairing, array $ack): OmrCameraCommand
    {
        return DB::transaction(function () use ($pairing, $ack): OmrCameraCommand {
            if ($pairing->diagnostic_run_id !== null) {
                $this->lockDiagnosticRun((int) $pairing->diagnostic_run_id);
            }
            $locked = OmrCameraPairing::query()->whereKey($pairing->id)->lockForUpdate()->firstOrFail();
            $command = $locked->commands()->where('command_id', $ack['command_id'])->lockForUpdate()->firstOrFail();
            abort_unless($locked->isLive() && (int) $command->controller_generation === (int) $locked->controller_generation
                && (int) $command->source_generation === (int) $ack['source_generation']
                && (int) $command->config_revision === (int) $ack['config_revision']
                && ! $command->frame()->exists(), 409);
            if ($command->device_outcome !== null) {
                abort_unless($command->device_outcome === 'rejected' &&
                    (string) ($command->device_outcome_json['reason'] ?? '') === $ack['reason'], 409);

                return $command;
            }
            $command->forceFill(['device_outcome' => 'rejected', 'device_outcome_json' => $ack])->saveOrFail();
            $locked->forceFill(['last_ack_json' => [
                'command_id' => $command->command_id, 'status' => 'rejected',
                'source_generation' => (int) $command->source_generation,
                'config_revision' => (int) $command->config_revision,
                'reason' => $ack['reason'],
            ]])->saveOrFail();

            return $command;
        }, 3);
    }

    public function heartbeat(OmrCameraPairing $pairing, string $role, array $observation): void
    {
        DB::transaction(function () use ($pairing, $role, $observation): void {
            $locked = OmrCameraPairing::query()->whereKey($pairing->id)->lockForUpdate()->firstOrFail();
            $column = $role === 'node' ? 'node_heartbeat_at' : 'controller_heartbeat_at';
            if ($role === 'node') {
                abort_unless((int) ($observation['source_generation'] ?? 0) === (int) $locked->source_generation
                    && (int) ($observation['config_revision'] ?? 0) === (int) $locked->config_revision
                    && ($observation['target_key'] ?? null) === ($locked->config_json['target']['targetKey'] ?? null), 409);
                $sequence = (int) ($observation['sequence'] ?? 0);
                abort_unless($sequence > (int) $locked->observation_sequence, 409);
                if (($observation['kind'] ?? null) === 'quality') {
                    $result = (array) ($observation['result'] ?? []);
                    $quality = (array) ($observation['quality'] ?? []);
                    $gates = (array) ($result['gates'] ?? []);
                    abort_unless((int) ($observation['frame_sequence'] ?? -1) === (int) ($result['frameId'] ?? -2)
                        && $quality === array_intersect_key($gates, $quality)
                        && (bool) ($observation['ready'] ?? false) === (bool) (
                            ($gates['in_bounds'] ?? false) && ($gates['angle_ok'] ?? false)
                            && ($gates['steady_ok'] ?? false) && ($gates['lighting_ok'] ?? false)), 422);
                } elseif (($observation['kind'] ?? null) === 'qr_card_removed') {
                    abort_unless(($observation['qr_card_removed'] ?? false) === true, 422);
                }
                if (($observation['kind'] ?? null) === 'qr_intent') {
                    abort_unless(isset($observation['command'], $observation['command_id']), 422);
                }
                $locked->observation_sequence = $sequence;
                $observation['received_at'] = now()->format('Y-m-d\\TH:i:s.uP');
                $locked->observations_json = array_values(array_slice([...(array) $locked->observations_json, $observation],
                    -(int) config('omr-camera.max_observations_per_pair', 32)));
            } else {
                abort_unless((int) ($observation['source_generation'] ?? 0) === (int) $locked->source_generation
                    && ($observation['target_key'] ?? null) === ($locked->config_json['target']['targetKey'] ?? null), 409);
            }
            $locked->{$column} = now();
            $locked->save();
        }, 3);
    }

    public function revoke(OmrCameraPairing $pairing, string $reason): void
    {
        DB::transaction(function () use ($pairing, $reason): void {
            $locked = OmrCameraPairing::query()->whereKey($pairing->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['revoked_at' => now(), 'revoke_reason' => $reason, 'active_lease_key' => null,
                'controller_generation' => (int) $locked->controller_generation + 1,
                'node_generation' => (int) $locked->node_generation + 1,
                'controller_token_hash' => null, 'node_token_hash' => null, 'pair_code_hash' => null])->save();
            $locked->frames()->whereIn('state', [OmrCameraFrame::STATE_ACQUIRED, OmrCameraFrame::STATE_UPLOADING, OmrCameraFrame::STATE_STAGED])
                ->update(['state' => OmrCameraFrame::STATE_CANCELLED, 'cancelled_at' => now()]);
        }, 3);
    }
}

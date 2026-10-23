<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr;

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Camera\OmrCameraProtocol;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\User;

final class ResolveOmrStagedPreflightImage
{
    public function __construct(
        private readonly OmrCameraProtocol $protocol,
        private readonly OmrCameraFrameFinalization $originals,
    ) {}

    /** @return array{bytes: string, mime: string} */
    public function handle(OmrCaptureBatch $batch, User $actor, string $pairingId, string $commandId,
        string $frameId, string $role, string $token, int $generation): array
    {
        abort_unless($role === 'controller', 403);
        $pairing = $this->protocol->authenticate($actor, $pairingId, $role, $token, $generation);
        abort_unless((int) $pairing->production_batch_id === (int) $batch->id && $pairing->diagnostic_run_id === null, 404);
        $command = $pairing->commands()->where('command_id', $commandId)->firstOrFail();
        $frame = OmrCameraFrame::query()->where('public_id', $frameId)
            ->where('pairing_id', $pairing->id)->where('command_id', $command->id)->firstOrFail();
        abort_unless($command->kind === 'CAPTURE' && $command->device_outcome === 'acquired'
            && $frame->state === OmrCameraFrame::STATE_STAGED && $frame->upload_expires_at?->isFuture()
            && (int) $frame->node_generation === (int) $pairing->node_generation
            && (int) $frame->source_generation === (int) $pairing->source_generation
            && (int) $frame->config_revision === (int) $command->config_revision, 409);

        return ['bytes' => $this->originals->resolveFrameOriginal($frame), 'mime' => (string) $frame->mime_type];
    }
}

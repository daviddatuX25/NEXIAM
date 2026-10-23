<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Camera;

use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraFrame;

final readonly class OmrCameraFrameReservation
{
    /** @param array<string, mixed>|null $replayReceipt */
    public function __construct(
        public OmrCameraCommand $operation,
        public ?OmrCameraFrame $frame,
        public string $leaseToken,
        public string $payloadFingerprint,
        public ?array $replayReceipt = null,
        public int $leaseGeneration = 0,
    ) {}

    public function isReplay(): bool
    {
        return $this->replayReceipt !== null;
    }
}

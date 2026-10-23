<?php

declare(strict_types=1);

namespace App\Release\Contracts;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultRelease;
use App\Models\User;

interface ResultDeliveryLedgerInterface
{
    /**
     * Issues an official result document via an explicit channel.
     * Channels: 'physical', 'portal', 'email'.
     */
    public function deliver(
        AdmissionAttempt $attempt,
        string $channel,
        User $actor,
        string $idempotencyKey,
        ?string $printReceiptId = null,
        ?string $consultationEncounterId = null
    ): ResultRelease;

    public function retract(
        ResultRelease $release,
        User $actor,
        string $reason,
        string $idempotencyKey
    ): ResultRelease;
}

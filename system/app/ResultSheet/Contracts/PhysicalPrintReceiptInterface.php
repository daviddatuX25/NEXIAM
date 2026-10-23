<?php

declare(strict_types=1);

namespace App\ResultSheet\Contracts;

use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;

interface PhysicalPrintReceiptInterface
{
    public function confirmPrint(
        ResultDocument $document,
        User $actor,
        string $idempotencyKey,
        ?string $jobItemId = null,
    ): ResultDocumentPrintReceipt;

    public function correctPrintConfirmation(
        ResultDocumentPrintReceipt $receipt,
        User $actor,
        string $reason,
        string $idempotencyKey,
    ): ResultDocumentPrintReceipt;
}

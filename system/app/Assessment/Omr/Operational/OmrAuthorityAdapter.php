<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

interface OmrAuthorityAdapter
{
    public function project(
        string $workbenchId,
        int $actorId,
        ?int $captureSheetId = null,
        ?string $captureOperationId = null,
        ?string $captureIdempotencyKey = null,
        ?int $diagnosticPageNo = null,
    ): OmrWorkbenchProjection;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        string $workbenchId,
        OmrWorkbenchCommand $command,
        array $payload,
        int $actorId,
    ): OmrWorkbenchProjection;
}

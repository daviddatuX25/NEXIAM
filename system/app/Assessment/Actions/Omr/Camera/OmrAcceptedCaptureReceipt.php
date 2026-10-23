<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Camera;

use InvalidArgumentException;

final readonly class OmrAcceptedCaptureReceipt
{
    /** @param array<string, mixed> $target */
    public function __construct(
        public string $operationId,
        public array $target,
        public string $targetKey,
        public int $pageCount,
        public ?int $captureId,
        public ?int $pageId,
        public ?int $sheetId,
    ) {
        foreach (['workbenchId', 'clientCaptureId', 'pageNo', 'captureRevision', 'instanceId', 'sheetId'] as $field) {
            if (! array_key_exists($field, $target)) {
                throw new InvalidArgumentException('Accepted OMR capture target is incomplete.');
            }
        }

        if ($operationId === '' || $targetKey === '' || $pageCount < 1
            || (int) $target['pageNo'] < 1 || (int) $target['pageNo'] > $pageCount
            || ($captureId !== null && $captureId < 1) || ($pageId !== null && $pageId < 1)
            || ($sheetId !== null && $sheetId < 1)) {
            throw new InvalidArgumentException('Accepted OMR capture receipt is invalid.');
        }
    }
}

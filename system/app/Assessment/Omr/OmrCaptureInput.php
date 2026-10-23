<?php

namespace App\Assessment\Omr;

/**
 * Immutable bytes and binding metadata crossing the shared capture boundary.
 *
 * The DTO intentionally carries no model, score, applicant, or workflow state.
 * A PDF's source page count is resolved by the preflight service; image
 * captures are single-page inputs. `expectedPageCount` is the pinned workflow
 * page count used only to reject an out-of-range requested page.
 */
final readonly class OmrCaptureInput
{
    public int $byteCount;

    public string $sha256;

    public function __construct(
        public string $bytes,
        public string $mime,
        public int $pageNo = 1,
        public int $pageCount = 1,
        public ?int $expectedPageCount = null,
        public ?string $assetId = null,
        public ?string $method = null,
        public ?string $challengeId = null,
        public ?string $targetRevision = null,
        public ?string $originalHash = null,
        ?int $width = null,
        ?int $height = null,
        public ?OmrClientQuad $clientQuad = null,
        public ?string $cropPolicy = 'auto_document_quad',
    ) {
        $this->byteCount = strlen($bytes);
        $this->sha256 = hash('sha256', $bytes);
        $this->width = $width;
        $this->height = $height;
    }

    public readonly ?int $width;

    public readonly ?int $height;

    public function hash(): string
    {
        return $this->sha256;
    }

    public function sourceHash(): string
    {
        return $this->originalHash ?? $this->sha256;
    }

    public function isPdf(): bool
    {
        return strtolower(trim($this->mime)) === 'application/pdf';
    }
}

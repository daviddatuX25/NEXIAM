<?php

namespace App\ValueObjects;

use App\Models\ResultSheetTemplate;

readonly class RenderResult
{
    private const PAGE_SIZES = [
        'a4' => ['width' => 210, 'height' => 297],
        'legal' => ['width' => 216, 'height' => 356],
        'letter' => ['width' => 216, 'height' => 279],
    ];

    public function __construct(
        public readonly string $html,
        public readonly string $mode,
        public readonly string $paperSize,
        public readonly string $orientation,
        public readonly string $logicalUnit,
        public readonly ?string $watermarkText = null,
    ) {}

    public static function fromTemplate(ResultSheetTemplate $template, string $html = ''): self
    {
        return new self(
            html: $html,
            mode: $template->mode,
            paperSize: $template->paper_size ?? ResultSheetTemplate::PAPER_A4,
            orientation: $template->orientation ?? ResultSheetTemplate::ORIENTATION_PORTRAIT,
            logicalUnit: $template->logical_unit ?? ResultSheetTemplate::LOGICAL_FULL,
            watermarkText: $template->watermark_text,
        );
    }

    public function isHalf(): bool
    {
        return str_starts_with($this->logicalUnit, 'half_');
    }

    /**
     * @return array{width: int, height: int} Dimensions in mm
     */
    public function pageDimensions(): array
    {
        $dims = self::PAGE_SIZES[$this->paperSize] ?? self::PAGE_SIZES['a4'];

        if ($this->orientation === 'landscape') {
            $dims = ['width' => $dims['height'], 'height' => $dims['width']];
        }

        if ($this->isHalf()) {
            $dims['height'] = (int) ($dims['height'] / 2);
        }

        return $dims;
    }

    /**
     * Returns the CSS @page size string, e.g. "a4 portrait".
     */
    public function cssPageSize(): string
    {
        return "{$this->paperSize} {$this->orientation}";
    }
}

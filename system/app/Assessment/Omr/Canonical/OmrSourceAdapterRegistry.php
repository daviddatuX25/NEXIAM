<?php

namespace App\Assessment\Omr\Canonical;

use InvalidArgumentException;

final class OmrSourceAdapterRegistry
{
    /** @var array<string, class-string<OmrSourceAdapter>> */
    private const ADAPTERS = [
        'html' => OmrHtmlSourceAdapter::class,
        'docx' => OmrDocxSourceAdapter::class,
        'pdf' => OmrPdfSourceAdapter::class,
        'image' => OmrImageSourceAdapter::class,
        'preset' => OmrPresetSourceAdapter::class,
    ];

    public function get(string $kind): OmrSourceAdapter
    {
        $class = self::ADAPTERS[$kind] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException("Unsupported canonical source kind: {$kind}.");
        }

        return app($class);
    }

    /** @return list<string> */
    public function kinds(): array
    {
        return array_keys(self::ADAPTERS);
    }
}

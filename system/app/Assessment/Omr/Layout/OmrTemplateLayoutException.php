<?php

namespace App\Assessment\Omr\Layout;

use RuntimeException;

/**
 * Raised by the layout solver when a blueprint cannot be solved. Carries
 * typed diagnostics with authoring context (section/range/page/column).
 */
final class OmrTemplateLayoutException extends RuntimeException
{
    /** @param list<LayoutDiagnostic> $diagnostics */
    public function __construct(public readonly array $diagnostics)
    {
        $first = $diagnostics[0] ?? new LayoutDiagnostic('invalid_blueprint', 'Invalid layout blueprint.');

        parent::__construct($first->message, 0, null);
    }

    /** @return list<array<string, mixed>> */
    public function diagnosticsToArray(): array
    {
        return array_map(static fn (LayoutDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics);
    }
}

<?php

namespace App\ResultSheet\Rendering;

use RuntimeException;

/**
 * Raised when every item of a result-sheet job failed to render; carries the
 * per-item manifest so the job persists it before marking failed.
 */
final class AllItemsFailedException extends RuntimeException
{
    /**
     * @param  array<int, array<string, mixed>>  $manifest
     */
    public function __construct(public readonly array $manifest)
    {
        parent::__construct('all_items_failed');
    }
}

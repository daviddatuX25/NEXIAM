<?php

namespace App\Assessment\Omr\Diagnostics;

use RuntimeException;

/**
 * Typed diagnostic-target failure (OpenSpec 35.12/35.16).
 *
 * `errorCode` is a stable machine-readable code (e.g.
 * `candidate_artifact_stale`, `compiled_version_not_marker_bearing`,
 * `diagnostic_page_out_of_range`) that callers may surface to staff without
 * leaking internals.
 */
final class OmrDiagnosticTargetException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($errorCode.': '.$message);
    }
}

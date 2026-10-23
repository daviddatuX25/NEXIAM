<?php

namespace App\Assessment\Omr\Queries;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

use function hash;
use function json_encode;
use function now;

/**
 * Cache boundary for immutable OMR evidence projections.
 *
 * Workflow status, selected pointers, permissions, and qualification readiness
 * are deliberately not represented here. Capture evidence plus projected asset
 * metadata identify every cached representation.
 */
final class OmrEvidenceCache
{
    public const KEY_VERSION = 'v4';

    public const TTL_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $bindings
     */
    public function key(array $bindings): string
    {
        $normalized = [
            'key_version' => self::KEY_VERSION,
            'institution_id' => (int) ($bindings['institution_id'] ?? 0),
            'actor_id' => (int) ($bindings['actor_id'] ?? 0),
            'authority' => (string) ($bindings['authority'] ?? 'guidance.scoring.omr'),
            'resource_scope_digest' => (string) ($bindings['resource_scope_digest'] ?? ''),
            'run_id' => (int) ($bindings['run_id'] ?? 0),
            'method' => (string) ($bindings['method'] ?? ''),
            'capture_id' => (int) ($bindings['capture_id'] ?? 0),
            'evidence_hash' => (string) ($bindings['evidence_hash'] ?? ''),
            'evidence_version' => (string) ($bindings['evidence_version'] ?? ''),
            'asset_projection_digest' => (string) ($bindings['asset_projection_digest'] ?? ''),
            'document_fields_hash' => (string) ($bindings['document_fields_hash'] ?? ''),
            'review_revision' => (int) ($bindings['review_revision'] ?? 0),
            'review_eligibility' => $bindings['review_eligibility'] ?? null,
            'parent_version' => (int) ($bindings['parent_version'] ?? 0),
            'filter' => (string) ($bindings['filter'] ?? 'all'),
            'page' => (int) ($bindings['page'] ?? 1),
            'per_page' => (int) ($bindings['per_page'] ?? OmrEvidencePage::DEFAULT_PER_PAGE),
        ];

        return 'omr:evidence:'.hash(
            'sha256',
            json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * Cache failures are non-authoritative. A failing cache backend falls back
     * to the authorized database projection rather than claiming no evidence.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $resolver
     * @return TValue
     */
    public function remember(string $key, Closure $resolver): mixed
    {
        try {
            return Cache::remember($key, now()->addSeconds(self::TTL_SECONDS), $resolver);
        } catch (Throwable) {
            return $resolver();
        }
    }

    /** ETag identifies the complete authorized representation, including repaired asset metadata. */
    public function etag(OmrEvidencePage $page): string
    {
        return '"'.hash(
            'sha256',
            json_encode($page->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ).'"';
    }
}

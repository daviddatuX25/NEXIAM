<?php

namespace App\Http\Middleware;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse request gate that delegates to CapabilityAuthorizer.
 * This is not legacy role middleware and does not treat role labels as authority.
 */
class EnsureCapability
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $decision = $this->authorizer->decide($request->user(), $capability);

        if (! $decision->allowed) {
            $this->recordAuditEvent->handle('authorization.denied', [
                'capability' => $capability,
                'reason' => $decision->reason,
                'path' => $request->path(),
            ], $request->user()?->getAuthIdentifier() ? (string) $request->user()->getAuthIdentifier() : 'anonymous');

            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}

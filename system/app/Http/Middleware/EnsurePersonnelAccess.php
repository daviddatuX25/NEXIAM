<?php

namespace App\Http\Middleware;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows either full HR personnel manage or office support-staff manage.
 */
class EnsurePersonnelAccess
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowed = $this->authorizer->allows($user, CapabilityCatalog::HR_STAFF_MANAGE)
            || $this->authorizer->allows($user, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE);

        if (! $allowed) {
            $this->recordAuditEvent->handle('authorization.denied', [
                'capability' => 'personnel.access',
                'reason' => 'capability_missing',
                'path' => $request->path(),
            ], $user?->getAuthIdentifier() ? (string) $user->getAuthIdentifier() : 'anonymous');

            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}

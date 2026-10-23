<?php

namespace App\Http\Controllers\Staff;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Foundation\AuditEvent;
use App\Staff\Queries\AuditLogProjection;
use App\Staff\Queries\StaffPresenceProjection;
use App\Staff\Support\AuditLogEventCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AuditLogProjection $projection,
        private readonly StaffPresenceProjection $presenceProjection,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        $canSupervise = $this->authorizer->allows($user, CapabilityCatalog::AUDIT_LOGS_VIEW);
        $canViewOwn = $this->authorizer->allows($user, CapabilityCatalog::AUDIT_LOGS_VIEW_OWN);

        if (! $canSupervise && ! $canViewOwn) {
            abort(403, 'You do not have capability to view system audit logs.');
        }

        $audit = $this->projection->for($request);

        $presence = null;
        if ($canSupervise && in_array($user->actorScope()->office, ['admin', 'registrar', 'guidance'], true)) {
            $presence = $this->presenceProjection->for($request);
        }

        return Inertia::render('Staff/Logs/Index', [
            'audit' => $audit,
            'presence' => $presence,
            'can_export_audit' => $this->authorizer->decideFresh($user, CapabilityCatalog::AUDIT_EXPORT, ['institution_id' => $user->actorScope()->institutionId])->allowed,
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if (! $this->authorizer->decideFresh($user, CapabilityCatalog::AUDIT_EXPORT, ['institution_id' => $user->actorScope()->institutionId])->allowed) {
            abort(403, 'You do not have capability to export system audit logs.');
        }

        $baseQuery = $this->projection->query($request);
        $selectedIdsParam = $request->query('ids');
        $selectedIds = is_string($selectedIdsParam) && trim($selectedIdsParam) !== ''
            ? array_filter(array_map('trim', explode(',', $selectedIdsParam)))
            : (is_array($selectedIdsParam) ? $selectedIdsParam : []);

        if ($selectedIds !== []) {
            $baseQuery->whereIn('id', $selectedIds);
        }

        $count = (clone $baseQuery)->count();

        $this->recordAuditEvent->handle('audit.logs_exported', [
            'actor_id' => $user->id,
            'office' => $user->actorScope()->office,
            'count' => $count,
            'selective' => $selectedIds !== [],
        ], (string) $user->id);

        $filename = 'audit-logs-export-'.now()->format('Y-m-d').'.csv';

        $institutionId = $user->actorScope()->institutionId;

        return response()->streamDownload(function () use ($baseQuery, $institutionId): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Timestamp',
                'Domain',
                'Event Key',
                'Event Title',
                'Actor Name',
                'Actor Token',
                'Summary',
                'Payload (JSON)',
                'Event Hash',
                'Prev Hash',
            ]);

            $baseQuery->chunk(250, function (Collection $chunk) use ($handle, $institutionId): void {
                $actorTokens = $chunk->pluck('actor')->filter()->unique()->values()->all();
                $users = $this->projection->actorsFor($actorTokens, $institutionId);

                /** @var AuditEvent $e */
                foreach ($chunk as $e) {
                    $actorToken = (string) $e->actor;
                    $actorInfo = $this->projection->resolveActor($actorToken, $users->get($actorToken));
                    $payload = $this->projection->redact(is_array($e->payload) ? $e->payload : []);
                    $domain = AuditLogEventCatalog::resolveDomain((string) $e->event_type);

                    $values = [
                        $e->id,
                        $e->created_at->toIso8601String(),
                        $domain,
                        $e->event_type,
                        AuditLogEventCatalog::resolveTitle((string) $e->event_type),
                        $actorInfo['name'],
                        $actorInfo['id'],
                        AuditLogEventCatalog::synthesizeSummary((string) $e->event_type, $payload),
                        json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        $e->event_hash ?? '',
                        $e->prev_hash ?? '',
                    ];

                    $sanitized = array_map(static function (mixed $value): string {
                        $string = is_string($value) ? $value : (string) $value;
                        $trimmed = ltrim($string);

                        return preg_match('/^[=+@\-\t\r]/', $trimmed) === 1 ? "'".$string : $string;
                    }, $values);

                    fputcsv($handle, $sanitized);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

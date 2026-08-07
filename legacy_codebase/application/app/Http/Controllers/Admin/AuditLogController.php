<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('actor')->orderBy('created_at', 'desc');

        if ($request->filled('event')) {
            $query->where('event', $request->get('event'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->get('category'));
        }
        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->integer('actor_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $logs = $query->paginate(25)->withQueryString();

        // Use friendly labels from AuditService (falls back to raw values for unknown events)
        $events = AuditService::getEventOptions();
        $categories = AuditService::getCategoryOptions();

        // Add any events/categories that exist in DB but aren't in our mapping
        $knownEvents = array_column($events, 'value');
        $knownCategories = array_column($categories, 'value');

        $dbEvents = AuditLog::query()->select('event')->distinct()->pluck('event')->filter(fn ($v) => ! in_array($v, $knownEvents, true))->values();
        $dbCategories = AuditLog::query()->select('category')->distinct()->whereNotNull('category')->pluck('category')->filter(fn ($v) => ! in_array($v, $knownCategories, true))->values();

        foreach ($dbEvents as $event) {
            $events[] = ['value' => $event, 'label' => $event];
        }
        foreach ($dbCategories as $category) {
            $categories[] = ['value' => $category, 'label' => $category];
        }

        return Inertia::render('Admin/Logs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['event', 'category', 'actor_id', 'date_from', 'date_to']),
            'events' => $events,
            'categories' => $categories,
            'scopeLabel' => 'System audit trail and user activity logs',
            'showActorFilter' => true,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('actor')->orderBy('created_at', 'desc');

        if ($request->filled('event')) {
            $query->where('event', $request->get('event'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->get('category'));
        }
        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->integer('actor_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-log-'.now()->format('Y-m-d').'.csv"',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'created_at', 'event', 'category', 'actor_id', 'actor_type', 'summary']);

            $query->cursor()->each(function (AuditLog $log) use ($handle) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at?->toIso8601String(),
                    AuditService::getEventLabel($log->event),
                    AuditService::getCategoryLabel($log->category ?? 'other'),
                    $log->actor_id,
                    $log->actor_type,
                    $log->summary,
                ]);
            });
            fclose($handle);
        }, 'audit-log-'.now()->format('Y-m-d').'.csv', $headers);
    }
}

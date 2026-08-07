<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * List notifications for the current applicant.
     * Shape: { id, type, message, read, created_at } per 08-API-SPEC-PHASE1.
     */
    public function index(Request $request): JsonResponse
    {
        $applicant = Auth::guard('applicant')->user();
        if (! $applicant) {
            abort(403, 'Unauthorized.');
        }

        $limit = min((int) $request->get('limit', 20), 100);
        $notifications = $applicant->notifications()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $items = $notifications->map(fn (DatabaseNotification $n) => $this->toPortalShape($n));

        return response()->json(['notifications' => $items]);
    }

    /**
     * Mark a notification as read. Applicant must own the notification.
     */
    public function markRead(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $applicant = Auth::guard('applicant')->user();
        if (! $applicant) {
            abort(403, 'Unauthorized.');
        }

        $notification = DatabaseNotification::query()
            ->where('id', $id)
            ->where('notifiable_type', $applicant->getMorphClass())
            ->where('notifiable_id', $applicant->getKey())
            ->first();

        if (! $notification) {
            abort(403, 'Notification not found or access denied.');
        }

        $notification->markAsRead();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('portal.dashboard')->with('success', 'Notification marked as read.');
    }

    private function toPortalShape(DatabaseNotification $n): array
    {
        $data = $n->data ?? [];
        $message = $data['message'] ?? $data['title'] ?? class_basename($n->type);

        return [
            'id' => $n->id,
            'type' => $n->type,
            'message' => $message,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}

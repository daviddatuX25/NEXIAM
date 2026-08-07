<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user.
     * Sorted newest first (D-03 discretion: newest first).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $limit = min((int) $request->get('limit', 20), 100);

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $items = $notifications->map(fn (DatabaseNotification $n) => $this->toShape($n));

        return response()->json([
            'notifications' => $items,
            'unread_count' => $items->where('read', false)->count(),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $notification = DatabaseNotification::query()
            ->where('id', $id)
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->first();

        if (! $notification) {
            abort(403, 'Notification not found or access denied.');
        }

        Gate::authorize('markAsRead', $notification);

        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $user->unreadNotifications->each->markAsRead();

        return response()->json(['ok' => true]);
    }

    private function toShape(DatabaseNotification $n): array
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

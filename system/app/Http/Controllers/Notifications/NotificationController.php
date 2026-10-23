<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Admission\ApplicantIdentity;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Own-data notification bell (GF-22 §5.5, GF-21). Every action is scoped to
 * the authenticated user's own delivery rows; attempting to read/mark another
 * user's row is a hard 403 (own-row gate).
 *
 * All routes are gated by the `notify.read.own` capability, which is granted
 * to every staff role AND applicant portal users.
 */
class NotificationController extends Controller
{
    public function index(Request $request, NotificationProjection $projection): Response
    {
        $user = $this->userOrAbort($request);

        return Inertia::render('Notifications/Index', [
            'page_state' => 'ready',
            'notifications' => $projection->forUser($user, 50),
        ]);
    }

    public function markRead(Request $request, NotificationDelivery $delivery): RedirectResponse|JsonResponse
    {
        $user = $this->userOrAbort($request);

        if (! $this->belongsToUser($delivery, $user)) {
            abort(403, 'Forbidden');
        }

        // Idempotent — marking an already-read row is a no-op.
        $delivery->update([
            'read_at' => $delivery->read_at ?? now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->userOrAbort($request);

        NotificationDelivery::forUser($user)
            ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function unreadCount(Request $request, NotificationProjection $projection): JsonResponse
    {
        $user = $this->userOrAbort($request);

        return response()->json(['count' => $projection->unreadCount($user)]);
    }

    private function belongsToUser(NotificationDelivery $delivery, User $user): bool
    {
        if ($delivery->channel !== NotificationDelivery::CHANNEL_IN_APP) {
            return false;
        }

        if ($delivery->recipient_type === User::class) {
            return (int) $delivery->recipient_id === $user->id;
        }

        $applicantIdentityId = $user->applicantAccount()->value('applicant_identity_id');

        return $delivery->recipient_type === ApplicantIdentity::class
            && $applicantIdentityId !== null
            && (int) $delivery->recipient_id === (int) $applicantIdentityId;
    }

    private function userOrAbort(Request $request): User
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        return $user;
    }
}

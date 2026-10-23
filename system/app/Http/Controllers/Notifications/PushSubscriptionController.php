<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Notifications\PushSubscription;
use App\Models\User;
use App\Notifications\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function __construct(private readonly WebPushService $webPush) {}

    public function vapidKey(): JsonResponse
    {
        try {
            return response()->json([
                'public_key' => $this->webPush->vapidPublicKey(),
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 503);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'starts_with:https://'],
            'p256dh' => ['required', 'string'],
            'auth' => ['required', 'string'],
            'device_label' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $recipient = $this->resolveRecipient($user);
        $endpoint = $validated['endpoint'];
        $endpointHash = hash('sha256', $endpoint);

        $subscription = PushSubscription::query()->updateOrCreate(
            [
                'recipient_type' => $recipient instanceof User ? User::class : ApplicantIdentity::class,
                'recipient_id' => $recipient->getKey(),
                'endpoint_hash' => $endpointHash,
            ],
            [
                'institution_id' => $recipient->institution_id ?? $user->staffProfile?->institution_id ?? null,
                'endpoint' => $endpoint,
                'p256dh' => $validated['p256dh'],
                'auth_token' => $validated['auth'],
                'vapid_key_id' => 'v1',
                'device_label' => $validated['device_label'] ?? $this->inferDeviceLabel($request->userAgent()),
                'user_agent' => $request->userAgent(),
                'revoked_at' => null,
            ]
        );

        return response()->json([
            'status' => 'ok',
            'id' => $subscription->id,
            'device_label' => $subscription->device_label,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $recipient = $this->resolveRecipient($user);
        $subscription = PushSubscription::query()->whereKey($id)->first();

        if ($subscription !== null) {
            $expectedType = $recipient instanceof User ? User::class : ApplicantIdentity::class;
            if ($subscription->recipient_type !== $expectedType || (int) $subscription->recipient_id !== (int) $recipient->getKey()) {
                abort(403, 'Unauthorized device subscription.');
            }

            $subscription->revoke();
        }

        return response()->json([
            'status' => 'revoked',
        ]);
    }

    private function resolveRecipient(User $user): User|ApplicantIdentity
    {
        $applicantIdentity = $user->applicantAccount?->applicantIdentity;
        if ($applicantIdentity !== null) {
            return $applicantIdentity;
        }

        return $user;
    }

    private function inferDeviceLabel(?string $userAgent): string
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return 'Web Browser';
        }

        if (str_contains($userAgent, 'iPhone')) {
            return 'iPhone';
        }
        if (str_contains($userAgent, 'iPad')) {
            return 'iPad';
        }
        if (str_contains($userAgent, 'Android')) {
            return 'Android Device';
        }
        if (str_contains($userAgent, 'Macintosh')) {
            return 'Mac';
        }
        if (str_contains($userAgent, 'Windows')) {
            return 'Windows PC';
        }

        return 'Web Browser';
    }
}

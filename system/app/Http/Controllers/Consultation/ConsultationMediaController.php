<?php

declare(strict_types=1);

namespace App\Http\Controllers\Consultation;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Services\JitsiMediaAdapter;
use App\Consultation\Services\JitsiTokenService;
use App\Consultation\Support\ConsultationMediaReadiness;
use App\Http\Controllers\Controller;
use App\Models\Consultation\ConsultationEncounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsultationMediaController extends Controller
{
    public function generateToken(
        Request $request,
        ConsultationEncounter $encounter,
        JitsiMediaAdapter $media,
        JitsiTokenService $tokens,
        CapabilityAuthorizer $authorizer,
        ConsultationMediaReadiness $readiness,
    ): JsonResponse {
        $readinessReport = $readiness->report();
        if (! $readinessReport['ready']) {
            throw ValidationException::withMessages([
                'online' => $readinessReport['reason'] ?? 'Online consultation media is unavailable.',
            ]);
        }

        $user = $request->user();
        abort_unless($user !== null && $encounter->medium === ConsultationEncounter::MEDIUM_ONLINE, 404);
        abort_unless($encounter->room_name !== null && $encounter->room_name !== '', 409);
        abort_unless(in_array($encounter->status, [
            ConsultationEncounter::STATUS_INVITED,
            ConsultationEncounter::STATUS_WAITING,
            ConsultationEncounter::STATUS_IN_PROGRESS,
        ], true), 409);

        $isCounselor = (int) $encounter->counselor_user_id === (int) $user->getKey();
        if ($isCounselor) {
            abort_unless($authorizer->decideFresh($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, [
                'institution_id' => (int) $encounter->institution_id,
                'office' => 'guidance',
            ])->allowed, 403);
        } else {
            abort_unless($media->isParticipantAuthorized($encounter, $user), 403);
        }

        $encounter = DB::transaction(function () use ($encounter): ConsultationEncounter {
            $lockedEncounter = ConsultationEncounter::query()->lockForUpdate()->findOrFail($encounter->getKey());
            abort_unless(in_array($lockedEncounter->status, [
                ConsultationEncounter::STATUS_INVITED,
                ConsultationEncounter::STATUS_WAITING,
                ConsultationEncounter::STATUS_IN_PROGRESS,
            ], true), 409);
            abort_unless($lockedEncounter->room_name !== null && $lockedEncounter->room_name !== '', 409);

            return $lockedEncounter;
        });
        $expiresAt = $media->accessWindowEnd($encounter);
        abort_if($expiresAt === null, 403);

        $origin = parse_url((string) config('jitsi.origin', ''));
        $browserDomain = is_array($origin) && isset($origin['host'])
            ? $origin['host'].(isset($origin['port']) ? ':'.$origin['port'] : '')
            : null;

        return response()->json([
            'token' => $tokens->issue($encounter, $user, $isCounselor ? 'moderator' : 'participant', $expiresAt),
            'room_name' => $encounter->room_name,
            'domain' => $browserDomain,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function admissionCallback(Request $request, JitsiMediaAdapter $media): JsonResponse
    {
        $secret = (string) config('jitsi.admission_secret', '');
        abort_unless(strlen($secret) >= 32
            && hash_equals($secret, (string) $request->header('X-Jitsi-Admission-Secret')), 403);

        $validated = $request->validate([
            'room_name' => ['required', 'string', 'max:64'],
            'participant_id' => ['required', 'string', 'max:255'],
            'token' => ['required', 'string'],
        ]);

        $allowed = $media->verifyAdmission(
            $validated['room_name'],
            $validated['participant_id'],
            $validated['token'],
        );

        return $this->serviceJsonResponse(['allowed' => $allowed]);
    }

    public function presenceCallback(Request $request, JitsiMediaAdapter $media): JsonResponse
    {
        $secret = (string) config('jitsi.admission_secret', '');
        abort_unless(strlen($secret) >= 32
            && hash_equals($secret, (string) $request->header('X-Jitsi-Admission-Secret')), 403);

        $validated = $request->validate([
            'room_name' => ['required', 'string', 'max:64'],
            'participant_id' => ['required', 'string', 'max:255'],
            'token' => ['required', 'string'],
            'event' => ['required', 'in:joined,left'],
            'schedule_generation' => ['required', 'integer', 'min:1'],
        ]);

        if (! $media->recordRoomPresence(
            $validated['room_name'],
            $validated['participant_id'],
            $validated['token'],
            $validated['event'],
            (int) $validated['schedule_generation'],
        )) {
            return $this->serviceJsonResponse(['accepted' => false], 403);
        }

        return $this->serviceJsonResponse([
            'accepted' => true,
            'room_name' => $validated['room_name'],
            'generation' => (int) $validated['schedule_generation'],
        ]);
    }

    public function closeEncounter(
        Request $request,
        ConsultationEncounter $encounter,
        JitsiMediaAdapter $media,
        CapabilityAuthorizer $authorizer,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null
            && (int) $encounter->counselor_user_id === (int) $user->getKey()
            && $authorizer->decideFresh($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, [
                'institution_id' => (int) $encounter->institution_id,
                'office' => 'guidance',
            ])->allowed, 403);
        abort_unless($encounter->medium === ConsultationEncounter::MEDIUM_ONLINE, 404);

        $fenced = DB::transaction(function () use ($encounter): ConsultationEncounter {
            $lockedEncounter = ConsultationEncounter::query()->lockForUpdate()->findOrFail($encounter->getKey());
            if (in_array($lockedEncounter->status, [
                ConsultationEncounter::STATUS_CLOSED_WITHOUT_OUTCOME,
                ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME,
            ], true)) {
                return $lockedEncounter;
            }

            abort_unless(in_array($lockedEncounter->status, [
                ConsultationEncounter::STATUS_INVITED,
                ConsultationEncounter::STATUS_WAITING,
                ConsultationEncounter::STATUS_IN_PROGRESS,
                ConsultationEncounter::STATUS_OUTCOME_RECORDED,
            ], true), 409);

            $hasOutcome = $lockedEncounter->status === ConsultationEncounter::STATUS_OUTCOME_RECORDED;
            $lockedEncounter->update([
                'status' => $hasOutcome
                    ? ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME
                    : ConsultationEncounter::STATUS_CLOSED_WITHOUT_OUTCOME,
                'schedule_generation' => (int) $lockedEncounter->schedule_generation + 1,
                'closed_at' => $lockedEncounter->closed_at ?? now(),
                'media_close_status' => ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING,
            ]);

            return $lockedEncounter->fresh();
        });

        if ($fenced->media_close_status === ConsultationEncounter::MEDIA_CLOSE_STATUS_ACKNOWLEDGED) {
            return response()->json(['status' => $fenced->status, 'media_close_status' => $fenced->media_close_status]);
        }

        $requestId = hash('sha256', implode(':', [
            'consultation-room-close-v1',
            (string) $fenced->getKey(),
            (string) $fenced->room_name,
            (string) $fenced->schedule_generation,
        ]));
        if ($media->terminateConference($fenced, $requestId)) {
            DB::transaction(function () use ($fenced): void {
                $lockedEncounter = ConsultationEncounter::query()->lockForUpdate()->findOrFail($fenced->getKey());
                if ($lockedEncounter->room_name === $fenced->room_name
                    && (int) $lockedEncounter->schedule_generation === (int) $fenced->schedule_generation
                    && in_array($lockedEncounter->status, [
                        ConsultationEncounter::STATUS_CLOSED_WITHOUT_OUTCOME,
                        ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME,
                    ], true)
                    && $lockedEncounter->media_close_status === ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING) {
                    $lockedEncounter->update(['media_close_status' => ConsultationEncounter::MEDIA_CLOSE_STATUS_ACKNOWLEDGED]);
                }
            });
        }

        $current = $fenced->fresh();
        $acknowledged = $current->media_close_status === ConsultationEncounter::MEDIA_CLOSE_STATUS_ACKNOWLEDGED;

        return response()->json([
            'status' => $current->status,
            'media_close_status' => $current->media_close_status,
            'pending' => ! $acknowledged,
        ], $acknowledged ? 200 : 502);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function serviceJsonResponse(array $payload, int $status = 200): JsonResponse
    {
        $response = response()->json($payload, $status);
        $response->headers->set('Content-Length', (string) strlen((string) $response->getContent()));

        return $response->header('Cache-Control', 'no-store');
    }
}

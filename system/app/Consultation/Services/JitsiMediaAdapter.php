<?php

declare(strict_types=1);

namespace App\Consultation\Services;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\ConsultationMediaReadiness;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class JitsiMediaAdapter
{
    public function __construct(
        private readonly ConsultationMediaReadiness $readiness,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function verifyAdmission(string $roomName, string $participantId, ?string $token): bool
    {
        $secret = (string) config('jitsi.app_secret', '');
        if ($token === null || $token === '' || $participantId === '' || strlen($secret) < 32) {
            return false;
        }

        try {
            $claims = (array) JWT::decode($token, new Key($secret, 'HS256'));
            $context = (array) ($claims['context'] ?? []);
            $participant = (array) ($context['user'] ?? []);
            $nexiam = (array) ($context['nexiam'] ?? []);
        } catch (Throwable) {
            return false;
        }

        $now = now()->timestamp;
        $issuedAt = $claims['iat'] ?? null;
        $notBefore = $claims['nbf'] ?? null;
        $expiresAt = $claims['exp'] ?? null;
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        $maximumTtl = min(900, max(1, (int) config('jitsi.jwt_ttl_seconds', 900)));
        if (($claims['room'] ?? null) !== $roomName
            || ($claims['sub'] ?? null) !== config('jitsi.domain')
            || ($claims['iss'] ?? null) !== config('jitsi.app_id')
            || ! in_array(config('jitsi.audience'), $audiences, true)
            || ! is_int($issuedAt)
            || ! is_int($notBefore)
            || ! is_int($expiresAt)
            || $issuedAt > $now + 15
            || $notBefore > $now
            || $notBefore > $issuedAt
            || $expiresAt <= $now
            || $expiresAt <= $issuedAt
            || ($expiresAt - $issuedAt) > $maximumTtl
            || ($claims['schedule_generation'] ?? null) !== ($nexiam['schedule_generation'] ?? null)
            || ($nexiam['version'] ?? null) !== 1
            || ($participant['id'] ?? null) !== $participantId) {
            return false;
        }

        $isModerator = ($participant['moderator'] ?? null) === true
            && ($participant['affiliation'] ?? null) === 'owner';
        $isParticipant = ($participant['moderator'] ?? null) === false
            && ($participant['affiliation'] ?? null) === 'member';
        if (! $isModerator && ! $isParticipant) {
            return false;
        }

        $encounter = ConsultationEncounter::query()
            ->where('room_name', $roomName)
            ->where('medium', ConsultationEncounter::MEDIUM_ONLINE)
            ->whereIn('status', [
                ConsultationEncounter::STATUS_INVITED,
                ConsultationEncounter::STATUS_WAITING,
                ConsultationEncounter::STATUS_IN_PROGRESS,
            ])
            ->first();
        if ($encounter === null
            || (string) $encounter->getKey() !== (string) ($nexiam['encounter_id'] ?? '')
            || (string) $encounter->institution_id !== (string) ($nexiam['institution_id'] ?? '')
            || (string) $encounter->admission_attempt_id !== (string) ($nexiam['attempt_id'] ?? '')
            || $encounter->consultation_policy_version === null
            || (int) $encounter->schedule_generation !== ($nexiam['schedule_generation'] ?? null)
            || (int) $encounter->consultation_policy_version !== ($nexiam['consultation_policy_version'] ?? null)
            || $this->accessWindowEnd($encounter) === null) {
            return false;
        }
        if (! $isModerator
            && $encounter->admitted_at !== null
            && $encounter->media_host_joined_at === null) {
            return false;
        }

        $counselor = User::query()->find($encounter->counselor_user_id);
        if ($counselor === null
            || ! $this->authorizer->decideFresh($counselor, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, [
                'institution_id' => (int) $encounter->institution_id,
                'office' => 'guidance',
            ])->allowed) {
            return false;
        }
        if ($isModerator) {
            return hash_equals($this->opaqueParticipantId($counselor->getKey()), $participantId);
        }

        $attempt = AdmissionAttempt::query()
            ->with('identity.applicantAccount')
            ->find($encounter->admission_attempt_id);
        $applicantUserId = $attempt?->identity?->applicantAccount?->user_id;

        return $applicantUserId !== null
            && hash_equals($this->opaqueParticipantId($applicantUserId), $participantId);
    }

    private function verifyDeparture(string $roomName, string $participantId, string $token): bool
    {
        $secret = (string) config('jitsi.app_secret', '');
        if ($participantId === '' || strlen($secret) < 32) {
            return false;
        }

        try {
            $claims = (array) JWT::decode($token, new Key($secret, 'HS256'));
        } catch (ExpiredException $exception) {
            $claims = (array) $exception->getPayload();
        } catch (Throwable) {
            return false;
        }

        $context = (array) ($claims['context'] ?? []);
        $participant = (array) ($context['user'] ?? []);
        $nexiam = (array) ($context['nexiam'] ?? []);
        $issuedAt = $claims['iat'] ?? null;
        $notBefore = $claims['nbf'] ?? null;
        $expiresAt = $claims['exp'] ?? null;
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        $maximumTtl = min(900, max(1, (int) config('jitsi.jwt_ttl_seconds', 900)));
        $now = now()->timestamp;

        if (($claims['room'] ?? null) !== $roomName
            || ($claims['sub'] ?? null) !== config('jitsi.domain')
            || ($claims['iss'] ?? null) !== config('jitsi.app_id')
            || ! in_array(config('jitsi.audience'), $audiences, true)
            || ! is_int($issuedAt)
            || ! is_int($notBefore)
            || ! is_int($expiresAt)
            || $issuedAt > $now + 15
            || $notBefore > $issuedAt
            || $expiresAt <= $issuedAt
            || ($expiresAt - $issuedAt) > $maximumTtl
            || ($claims['schedule_generation'] ?? null) !== ($nexiam['schedule_generation'] ?? null)
            || ($nexiam['version'] ?? null) !== 1
            || ($participant['id'] ?? null) !== $participantId) {
            return false;
        }

        $isHost = ($participant['moderator'] ?? null) === true
            && ($participant['affiliation'] ?? null) === 'owner';
        $isApplicant = ($participant['moderator'] ?? null) === false
            && ($participant['affiliation'] ?? null) === 'member';
        if (! $isHost && ! $isApplicant) {
            return false;
        }

        $encounter = ConsultationEncounter::query()
            ->where('room_name', $roomName)
            ->where('medium', ConsultationEncounter::MEDIUM_ONLINE)
            ->whereIn('status', [
                ConsultationEncounter::STATUS_INVITED,
                ConsultationEncounter::STATUS_WAITING,
                ConsultationEncounter::STATUS_IN_PROGRESS,
                ConsultationEncounter::STATUS_OUTCOME_RECORDED,
            ])
            ->first();
        if ($encounter === null
            || (string) $encounter->getKey() !== (string) ($nexiam['encounter_id'] ?? '')
            || (string) $encounter->institution_id !== (string) ($nexiam['institution_id'] ?? '')
            || (string) $encounter->admission_attempt_id !== (string) ($nexiam['attempt_id'] ?? '')
            || $encounter->consultation_policy_version === null
            || (int) $encounter->schedule_generation !== ($nexiam['schedule_generation'] ?? null)
            || (int) $encounter->consultation_policy_version !== ($nexiam['consultation_policy_version'] ?? null)) {
            return false;
        }

        if ($isHost) {
            return hash_equals($this->opaqueParticipantId($encounter->counselor_user_id), $participantId);
        }

        $attempt = AdmissionAttempt::query()
            ->with('identity.applicantAccount')
            ->find($encounter->admission_attempt_id);
        $applicantUserId = $attempt?->identity?->applicantAccount?->user_id;

        return $applicantUserId !== null
            && hash_equals($this->opaqueParticipantId($applicantUserId), $participantId);
    }

    public function roleForVerifiedToken(string $token): ?string
    {
        try {
            $claims = (array) JWT::decode(
                $token,
                new Key((string) config('jitsi.app_secret'), 'HS256'),
            );
        } catch (ExpiredException $exception) {
            $claims = (array) $exception->getPayload();
        } catch (Throwable) {
            return null;
        }
        $context = (array) ($claims['context'] ?? []);
        $participant = (array) ($context['user'] ?? []);

        if (($participant['moderator'] ?? null) === true
            && ($participant['affiliation'] ?? null) === 'owner') {
            return 'host';
        }

        if (($participant['moderator'] ?? null) === false
            && ($participant['affiliation'] ?? null) === 'member') {
            return 'applicant';
        }

        return null;
    }

    public function recordRoomPresence(string $roomName, string $participantId, string $token, string $event, int $generation): bool
    {
        if (! in_array($event, ['joined', 'left'], true)) {
            return false;
        }

        $role = $this->roleForVerifiedToken($token);
        $validToken = $event === 'left'
            ? $this->verifyDeparture($roomName, $participantId, $token)
            : $this->verifyAdmission($roomName, $participantId, $token);
        if ($role === null || ! $validToken) {
            return false;
        }

        $encounter = ConsultationEncounter::query()
            ->where('room_name', $roomName)
            ->where('medium', ConsultationEncounter::MEDIUM_ONLINE)
            ->first();
        $attempt = $encounter === null
            ? null
            : AdmissionAttempt::query()->find($encounter->admission_attempt_id);
        if ($encounter === null
            || $attempt === null
            || $generation !== (int) $encounter->schedule_generation) {
            return false;
        }

        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $attempt->academic_cycle_id,
            function () use ($encounter, $attempt, $roomName, $participantId, $token, $event, $generation, $role): bool {
                $snapshot = ConsultationEncounter::query()->find($encounter->getKey());
                if ($snapshot === null
                    || $snapshot->room_name !== $roomName
                    || (int) $snapshot->schedule_generation !== $generation
                    || (int) $snapshot->admission_attempt_id !== (int) $attempt->getKey()) {
                    return false;
                }
                if ($snapshot->consultation_session_id !== null
                    && ConsultationSession::query()->lockForUpdate()->find($snapshot->consultation_session_id) === null) {
                    return false;
                }
                $lockedAttempt = AdmissionAttempt::query()->lockForUpdate()->find($attempt->getKey());
                if ($lockedAttempt === null
                    || (int) $lockedAttempt->academic_cycle_id !== (int) $attempt->academic_cycle_id) {
                    return false;
                }
                if ($snapshot->consultation_assignment_id !== null
                    && ConsultationAssignment::query()->lockForUpdate()->find($snapshot->consultation_assignment_id) === null) {
                    return false;
                }
                $lockedEncounter = ConsultationEncounter::query()->lockForUpdate()->find($encounter->getKey());
                if ($lockedEncounter === null
                    || $lockedEncounter->room_name !== $roomName
                    || (int) $lockedEncounter->schedule_generation !== $generation
                    || (int) $lockedEncounter->admission_attempt_id !== (int) $lockedAttempt->getKey()
                    || ! ($event === 'left'
                        ? $this->verifyDeparture($roomName, $participantId, $token)
                        : $this->verifyAdmission($roomName, $participantId, $token))) {
                    return false;
                }
                if ($event === 'left') {
                    if ($role !== 'host') {
                        return $role === 'applicant';
                    }

                    if ($lockedEncounter->media_host_joined_at !== null) {
                        $lockedEncounter->update(['media_host_joined_at' => null]);
                        $this->audit->handle('consultation.media_host_left', [
                            'encounter_id' => $lockedEncounter->getKey(),
                            'room_name' => $lockedEncounter->room_name,
                            'schedule_generation' => $generation,
                            'counselor_user_id' => (int) $lockedEncounter->counselor_user_id,
                        ]);
                    }

                    return true;
                }

                if ($role === 'host') {
                    if (! in_array($lockedEncounter->status, [
                        ConsultationEncounter::STATUS_INVITED,
                        ConsultationEncounter::STATUS_WAITING,
                        ConsultationEncounter::STATUS_IN_PROGRESS,
                    ], true)) {
                        return false;
                    }

                    $isFirstHostJoin = $lockedEncounter->media_host_joined_at === null;
                    $lockedEncounter->update([
                        'status' => $lockedEncounter->status === ConsultationEncounter::STATUS_INVITED
                            ? ConsultationEncounter::STATUS_WAITING
                            : $lockedEncounter->status,
                        'media_host_joined_at' => $lockedEncounter->media_host_joined_at ?? now(),
                    ]);
                    if ($isFirstHostJoin) {
                        $this->audit->handle('consultation.media_host_joined', [
                            'encounter_id' => $lockedEncounter->getKey(),
                            'room_name' => $lockedEncounter->room_name,
                            'schedule_generation' => $generation,
                            'counselor_user_id' => (int) $lockedEncounter->counselor_user_id,
                        ]);
                    }

                    return true;
                }

                if ($role !== 'applicant'
                    || $lockedEncounter->media_host_joined_at === null
                    || ! in_array($lockedEncounter->status, [
                        ConsultationEncounter::STATUS_WAITING,
                        ConsultationEncounter::STATUS_IN_PROGRESS,
                    ], true)) {
                    return false;
                }

                $isFirstApplicantAdmission = $lockedEncounter->admitted_at === null;
                $lockedEncounter->update([
                    'status' => ConsultationEncounter::STATUS_IN_PROGRESS,
                    'admitted_at' => $lockedEncounter->admitted_at ?? now(),
                ]);
                if ($isFirstApplicantAdmission) {
                    $this->audit->handle('consultation.media_applicant_admitted', [
                        'encounter_id' => $lockedEncounter->getKey(),
                        'room_name' => $lockedEncounter->room_name,
                        'schedule_generation' => $generation,
                        'attempt_id' => (int) $lockedAttempt->getKey(),
                    ]);
                }

                return true;
            },
        );
    }

    public function terminationRequestId(ConsultationEncounter $encounter): string
    {
        return hash('sha256', implode(':', [
            'consultation-room-close-v1',
            (string) $encounter->getKey(),
            (string) $encounter->room_name,
            (string) $encounter->schedule_generation,
        ]));
    }

    public function terminateConference(ConsultationEncounter $encounter, string $requestId): bool
    {
        if (! $this->readiness->report()['ready']
            || ! is_string($encounter->room_name)
            || $encounter->room_name === ''
            || ! is_string($token = config('jitsi.control_token'))
            || $token === ''
            || ! is_string($url = config('jitsi.control_url'))
            || $url === '') {
            return false;
        }

        try {
            $response = Http::connectTimeout(2)
                ->timeout(5)
                ->acceptJson()
                ->withToken($token)
                ->post(rtrim($url, '/').'/terminate', [
                    'room_name' => $encounter->room_name,
                    'request_id' => $requestId,
                    'generation' => (int) $encounter->schedule_generation,
                ]);
        } catch (Throwable) {
            return false;
        }

        $body = $response->json();

        return $response->status() === 200
            && is_array($body)
            && ($body['room_name'] ?? null) === $encounter->room_name
            && ($body['request_id'] ?? null) === $requestId
            && ($body['generation'] ?? null) === (int) $encounter->schedule_generation
            && ($body['terminated'] ?? null) === true;
    }

    public function isParticipantAuthorized(ConsultationEncounter $encounter, User $user): bool
    {
        if ($this->accessWindowEnd($encounter) === null) {
            return false;
        }

        if ((int) $encounter->counselor_user_id === (int) $user->getKey()) {
            return $this->authorizer->decideFresh($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, [
                'institution_id' => (int) $encounter->institution_id,
                'office' => 'guidance',
            ])->allowed;
        }

        return AdmissionAttempt::query()
            ->whereKey($encounter->admission_attempt_id)
            ->whereHas('identity.applicantAccount', fn ($query) => $query->where('user_id', $user->getKey()))
            ->exists();
    }

    public function accessWindowEnd(ConsultationEncounter $encounter): ?Carbon
    {
        $attempt = $encounter->admissionAttempt;
        $policy = $attempt === null
            ? null
            : ConsultationPolicy::query()
                ->where('institution_id', $encounter->institution_id)
                ->where('academic_cycle_id', $attempt->academic_cycle_id)
                ->first();
        if ($attempt === null
            || (int) $attempt->institution_id !== (int) $encounter->institution_id
            || $encounter->consultation_policy_version === null
            || ($policy === null && (int) $encounter->consultation_policy_version !== 0)
            || ! ($policy?->allowsOnlineConsultation() ?? true)
            || ! in_array($encounter->status, [
                ConsultationEncounter::STATUS_INVITED,
                ConsultationEncounter::STATUS_WAITING,
                ConsultationEncounter::STATUS_IN_PROGRESS,
            ], true)
            || $encounter->media_close_status !== ConsultationEncounter::MEDIA_CLOSE_STATUS_NONE
            || $encounter->access_starts_at === null
            || $encounter->access_ends_at === null
            || now()->lt($encounter->access_starts_at)
            || now()->gte($encounter->access_ends_at)) {
            return null;
        }

        if ($encounter->booking_path === ConsultationEncounter::BOOKING_PATH_SCHEDULED) {
            $assignment = $encounter->consultationAssignment;
            $session = $encounter->consultationSession;
            if ($assignment === null
                || $session === null
                || (int) $assignment->admission_attempt_id !== (int) $attempt->getKey()
                || (int) $assignment->consultation_session_id !== (int) $session->getKey()
                || ! in_array($assignment->status, ConsultationAssignment::ACTIVE_STATUSES, true)
                || ! in_array($session->status, [
                    ConsultationSession::STATUS_PUBLISHED,
                    ConsultationSession::STATUS_IN_PROGRESS,
                ], true)
                || (int) $session->academic_cycle_id !== (int) $attempt->academic_cycle_id) {
                return null;
            }
        } elseif ($encounter->booking_path !== ConsultationEncounter::BOOKING_PATH_DIRECT) {
            return null;
        }

        return $encounter->access_ends_at;
    }

    private function opaqueParticipantId(int|string $userId): string
    {
        return hash_hmac('sha256', 'jitsi-user:'.$userId, (string) config('jitsi.app_secret'));
    }
}

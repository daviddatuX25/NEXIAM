<?php

namespace App\Notifications;

use App\Admission\Queries\CurrentIntakeQuery;
use App\Admission\Support\ApplicantOfficeInquiryNotice;
use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\RecordThreadService;
use App\Applicant\Support\ApplicantResultAccess;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionDocumentCorrectionRequest;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicationOpeningAvailabilityState;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Admission\ApplicationOpeningToken;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\Notifications\Templates\CanonicalReleasedResultBinding;
use App\Notifications\Templates\NoticeDeliverySchema3;
use App\ProgramHead\Support\AcademicReferralNotice;
use App\Release\Support\ReleaseDayDecisionNotice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class NotificationBusinessValidity
{
    public function isCurrent(NotificationDelivery $delivery): bool
    {
        if (! app(PracticeNotificationSafety::class)->deliveryIsSafe($delivery)) {
            return false;
        }
        $snapshotSchema = $delivery->payload['snapshot_schema_version'] ?? null;
        if (in_array($snapshotSchema, ['notice-delivery-2', 'notice-delivery-3'], true)) {
            $snapshot = $delivery->payload;
            if (($snapshot['event_type'] ?? null) !== $delivery->event_type
                || ($snapshot['template_key'] ?? null) !== $delivery->template_key
                || ($snapshot['recipient']['type'] ?? null) !== $delivery->recipient_type
                || (string) ($snapshot['recipient']['id'] ?? '') !== (string) $delivery->recipient_id
                || (int) ($snapshot['institution_id'] ?? 0) < 1
                || ! is_array($snapshot['event_resource'] ?? null)
                || $this->containsScoreData(['context' => $snapshot['context'] ?? [], 'email' => $snapshot['email'] ?? [], 'portal' => $snapshot['portal'] ?? []])
                || ($snapshotSchema === 'notice-delivery-3'
                    && (! NoticeDeliverySchema3::valid($snapshot)
                        || (int) ($snapshot['template']['version'] ?? 0) !== (int) $delivery->template_version))) {
                return false;
            }
        }

        return match ($delivery->event_type) {
            'application.opening_alert.verify.v1', 'application.opening_alert.opened.v1' => $this->isCurrentApplicationOpeningAlert($delivery),
            'consultation.online_invited.v1' => $this->isCurrentOnlineInvite($delivery),
            'exam.reminder.v1' => $this->isCurrentExamReminder($delivery),
            'exam.session_started_early.v1' => $this->isCurrentEarlyExamStart($delivery),
            'exam.session_expected_end_changed.v1' => $this->isCurrentExpectedEndChange($delivery),
            'exam.session_reassigned.v1' => $this->isCurrentExamReassignment($delivery),
            'exam.session_start_reverted.v1' => $this->isCurrentExamStartRevert($delivery),
            'result.paper_ready.v1' => $this->isCurrentPaperReady($delivery),
            'result.digital_issued.v1' => $this->isCurrentDigitalIssuance($delivery),
            'result.released.v1' => $this->isCurrentResultRelease($delivery),
            'admission.case_message_sent.v1' => $this->isCurrentCaseMessageSent($delivery),
            ReleaseDayDecisionNotice::EVENT => app(ReleaseDayDecisionNotice::class)->isCurrent($delivery),
            AcademicReferralNotice::EVENT => app(AcademicReferralNotice::class)->isCurrent($delivery),
            'admission.document_correction_requested.v1' => $this->isCurrentDocumentCorrectionRequest($delivery),
            'admission.office_inquiry_message.v1' => app(ApplicantOfficeInquiryNotice::class)->isCurrent($delivery),
            'admission.clearance.confirmed.v1' => $this->isCurrentClearanceConfirmed($delivery),
            default => true,
        };
    }

    private function isCurrentApplicationOpeningAlert(NotificationDelivery $delivery): bool
    {
        $payload = $delivery->payload;
        if ($delivery->channel !== NotificationDelivery::CHANNEL_MAIL
            || $delivery->recipient_type !== ApplicationOpeningSubscription::class
            || ($payload['snapshot_schema_version'] ?? null) !== 'application-opening-alert-1'
            || ($payload['recipient']['type'] ?? null) !== ApplicationOpeningSubscription::class
            || (string) ($payload['recipient']['id'] ?? '') !== (string) $delivery->recipient_id
            || ! is_array($payload['event_resource'] ?? null)
            || (int) ($payload['subscription_id'] ?? 0) !== (int) $delivery->recipient_id
            || (int) ($payload['event_resource']['subscription_id'] ?? 0) !== (int) $delivery->recipient_id
            || ($payload['event_type'] ?? null) !== $delivery->event_type
            || ($payload['template_key'] ?? null) !== $delivery->template_key
            || (int) ($payload['template']['version'] ?? 0) !== (int) $delivery->template_version) {
            return false;
        }
        $subscription = ApplicationOpeningSubscription::query()->find($delivery->recipient_id);
        if ($subscription === null || $subscription->email === null || $subscription->retention_hold
            || (int) $subscription->institution_id !== (int) ($payload['institution_id'] ?? 0)
            || (int) $subscription->generation !== (int) ($payload['event_resource']['generation'] ?? 0)
            || $subscription->unsubscribed_at !== null || $subscription->address_erased_at !== null
            || ($subscription->expires_at !== null && $subscription->expires_at->isPast())) {
            return false;
        }

        if ($delivery->event_type === 'application.opening_alert.verify.v1') {
            $tokenId = $payload['handoff']['verify_token_id'] ?? null;

            return config('application-opening-alerts.enabled')
                && $subscription->state === ApplicationOpeningSubscription::STATE_PENDING
                && is_string($tokenId)
                && ApplicationOpeningToken::query()->whereKey($tokenId)
                    ->where('subscription_id', $subscription->id)
                    ->where('generation', $subscription->generation)
                    ->where('purpose', 'verify')
                    ->whereNull('consumed_at')
                    ->whereNull('superseded_at')
                    ->whereNotNull('encrypted_token')
                    ->where('expires_at', '>', now())->exists();
        }

        if (! config('application-opening-alerts.enabled')
            || $subscription->state !== ApplicationOpeningSubscription::STATE_CONSUMED) {
            return false;
        }
        $occurrenceId = (int) ($payload['event_resource']['occurrence_id'] ?? 0);
        if (! $subscription->receipts()->where('occurrence_id', $occurrenceId)
            ->where('generation', $subscription->generation)->where('delivery_id', $delivery->id)
            ->where('status', 'claimed')->exists()) {
            return false;
        }
        $availability = ApplicationOpeningAvailabilityState::query()->whereKey($subscription->institution_id)->first();
        if ($availability === null || ! $availability->is_available
            || (int) $availability->generation !== (int) ($payload['event_resource']['availability_generation'] ?? 0)) {
            return false;
        }
        $institution = $subscription->institution;
        $period = $institution === null
            ? null
            : app(CurrentIntakeQuery::class)->forInstitution($institution);

        return $period !== null
            && (int) $period['cycle']->id === (int) ($payload['event_resource']['cycle_id'] ?? 0)
            && (int) $period['window']->id === (int) ($payload['event_resource']['window_id'] ?? 0)
            && $period['window']->closes_at->toIso8601String() === ($payload['event_resource']['closes_at'] ?? null)
            && $period['window']->opens_at->toIso8601String() === ($payload['event_resource']['opens_at'] ?? null);
    }

    private function isCurrentOnlineInvite(NotificationDelivery $delivery): bool
    {
        $payload = $this->eventPayload($delivery);
        $required = [
            'consultation_encounter_id',
            'admission_attempt_id',
            'institution_id',
            'academic_cycle_id',
            'schedule_generation',
            'consultation_policy_version',
            'booking_path',
            'access_starts_at',
            'access_ends_at',
            'result_document_id',
            'result_document_version',
        ];
        foreach ($required as $key) {
            if (! array_key_exists($key, $payload)) {
                return false;
            }
        }

        if ($delivery->recipient_type !== ApplicantIdentity::class
            || empty($delivery->recipient_id)) {
            return false;
        }

        $recipientId = $payload['identity_id'] ?? $payload['recipient_id'] ?? $delivery->recipient_id;
        if ((string) $delivery->recipient_id !== (string) $recipientId) {
            return false;
        }

        $encounter = ConsultationEncounter::query()
            ->whereKey($payload['consultation_encounter_id'])
            ->where('admission_attempt_id', $payload['admission_attempt_id'])
            ->where('institution_id', $payload['institution_id'])
            ->where('medium', ConsultationEncounter::MEDIUM_ONLINE)
            ->whereIn('status', [
                ConsultationEncounter::STATUS_INVITED,
                ConsultationEncounter::STATUS_WAITING,
                ConsultationEncounter::STATUS_IN_PROGRESS,
            ])
            ->first();

        if ($encounter === null
            || (int) $encounter->schedule_generation !== (int) $payload['schedule_generation']
            || (int) $encounter->consultation_policy_version !== (int) $payload['consultation_policy_version']
            || (int) $encounter->result_document_id !== (int) $payload['result_document_id']
            || (int) $encounter->result_document_version !== (int) $payload['result_document_version']
            || $encounter->booking_path !== $payload['booking_path']
            || $encounter->media_close_status === ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING
            || ! is_string($payload['access_starts_at'])
            || ! is_string($payload['access_ends_at'])
            || $encounter->access_starts_at === null
            || $encounter->access_ends_at === null
            || ($encounter->access_starts_at->toIso8601String() !== $payload['access_starts_at']
                && ! $encounter->access_starts_at->equalTo(Carbon::parse($payload['access_starts_at'])))
            || ($encounter->access_ends_at->toIso8601String() !== $payload['access_ends_at']
                && ! $encounter->access_ends_at->equalTo(Carbon::parse($payload['access_ends_at'])))
            || now()->gte($encounter->access_ends_at)) {
            return false;
        }

        $attemptMatchesRecipient = AdmissionAttempt::query()
            ->whereKey($payload['admission_attempt_id'])
            ->where('applicant_identity_id', $recipientId)
            ->where('academic_cycle_id', $payload['academic_cycle_id'])
            ->where('institution_id', $payload['institution_id'])
            ->exists();
        if (! $attemptMatchesRecipient) {
            return false;
        }

        $documentIsCurrent = ResultDocument::query()
            ->whereKey($payload['result_document_id'])
            ->where('admission_attempt_id', $payload['admission_attempt_id'])
            ->where('status', ResultDocument::STATUS_READY)
            ->where('version', $payload['result_document_version'])
            ->exists();
        if (! $documentIsCurrent) {
            return false;
        }

        $policy = ConsultationPolicy::query()
            ->where('institution_id', $payload['institution_id'])
            ->where('academic_cycle_id', $payload['academic_cycle_id'])
            ->first();

        return $encounter->consultation_policy_version !== null
            && ($policy !== null || (int) $encounter->consultation_policy_version === 0)
            && ($policy?->allowsOnlineConsultation() ?? true);
    }

    private function isCurrentPaperReady(NotificationDelivery $delivery): bool
    {
        $payload = $delivery->payload ?? [];
        foreach (['attempt_id', 'document_id', 'identity_id'] as $key) {
            if (! isset($payload[$key])) {
                return false;
            }
        }

        if ($delivery->recipient_type !== ApplicantIdentity::class
            || (string) $delivery->recipient_id !== (string) $payload['identity_id']) {
            return false;
        }

        return ResultDocumentPrintReceipt::query()
            ->where('admission_attempt_id', $payload['attempt_id'])
            ->where('result_document_id', $payload['document_id'])
            ->where('is_current', true)
            ->where('operation', 'confirmed')
            ->whereHas('attempt', fn ($query) => $query
                ->where('current_result_document_id', $payload['document_id'])
                ->where('applicant_identity_id', $payload['identity_id']))
            ->exists();
    }

    private function isCurrentDigitalIssuance(NotificationDelivery $delivery): bool
    {
        $payload = $delivery->payload ?? [];
        foreach (['attempt_id', 'release_id', 'identity_id'] as $key) {
            if (! isset($payload[$key])) {
                return false;
            }
        }

        if ($delivery->recipient_type !== ApplicantIdentity::class
            || (string) $delivery->recipient_id !== (string) $payload['identity_id']) {
            return false;
        }

        $release = ResultRelease::query()
            ->with('admissionAttempt')
            ->whereKey($payload['release_id'])
            ->where('admission_attempt_id', $payload['attempt_id'])
            ->where('channel', 'portal')
            ->where('is_current', true)
            ->whereNull('unreleased_at')
            ->whereHas('admissionAttempt', fn ($query) => $query
                ->where('applicant_identity_id', $payload['identity_id'])
                ->whereColumn('admission_attempts.current_result_document_id', 'result_releases.result_document_id'))
            ->first();

        if ($release === null) {
            return false;
        }

        $access = app(ApplicantResultAccess::class)->forAttempt($release->admissionAttempt);

        return $access['available'] && (int) $access['release']?->id === (int) $release->id;
    }

    private function isCurrentExamReminder(NotificationDelivery $delivery): bool
    {
        if (! app(PracticeReminderSafety::class)->deliveryIsSafe($delivery)) {
            return false;
        }
        $payload = $delivery->payload ?? [];
        $newSnapshot = in_array(($payload['snapshot_schema_version'] ?? null), ['notice-delivery-2', 'notice-delivery-3'], true);
        if ($newSnapshot) {
            if ($this->containsScoreData($payload)) {
                return false;
            }
            if (($payload['domain'] ?? null) !== 'examination' || ($payload['canonical_slug'] ?? null) !== 'reminder'
                || ($payload['event_type'] ?? null) !== $delivery->event_type
                || ($payload['template_key'] ?? null) !== $delivery->template_key
                || ($payload['recipient']['type'] ?? null) !== $delivery->recipient_type) {
                return false;
            }
            $resource = $payload['event_resource'] ?? [];
            $payload = [
                'institution_id' => $payload['institution_id'] ?? null,
                'session_id' => $resource['id'] ?? null, 'assignment_id' => $resource['assignment_id'] ?? null,
                'session_version' => $resource['version'] ?? null, 'schedule_generation' => $resource['generation'] ?? null,
                'assignment_version' => $resource['assignment_version'] ?? null, 'starts_at' => $resource['starts_at'] ?? null,
                'identity_id' => $payload['recipient']['id'] ?? null,
            ];
        }
        foreach (['session_id', 'assignment_id', 'schedule_generation', 'session_version', 'assignment_version', 'starts_at', 'identity_id'] as $key) {
            if (! isset($payload[$key])) {
                return false;
            }
        }

        if ($this->containsScoreData($payload)
            || $delivery->recipient_type !== ApplicantIdentity::class
            || (string) $delivery->recipient_id !== (string) $payload['identity_id']) {
            return false;
        }

        $session = ExamSession::query()->find($payload['session_id']);
        if ($newSnapshot && ((int) $session?->academicCycle?->institution_id !== (int) $payload['institution_id']
            || (int) $payload['institution_id'] < 1)) {
            return false;
        }
        $snapshotSessionVersion = $session?->notification_policy_snapshot['session_version'] ?? null;
        if ($session === null
            || $session->status !== ExamSession::STATUS_PUBLISHED
            || $session->starts_at === null
            || ! $session->starts_at->isFuture()
            || $snapshotSessionVersion === null
            || (int) $snapshotSessionVersion !== (int) $payload['session_version']
            || (int) $payload['schedule_generation'] !== (int) $payload['session_version']
            || (string) $session->starts_at->toIso8601String() !== (string) $payload['starts_at']) {
            return false;
        }

        return ExamAssignment::query()
            ->whereKey($payload['assignment_id'])
            ->where('exam_session_id', $session->id)
            ->where('status', ExamAssignment::STATUS_ASSIGNED)
            ->where('version', $payload['assignment_version'])
            ->whereHas('admissionAttempt', fn ($query) => $query
                ->where('applicant_identity_id', $payload['identity_id'])
                ->when($newSnapshot, fn ($query) => $query->where('institution_id', $payload['institution_id'])))
            ->exists();
    }

    private function isCurrentEarlyExamStart(NotificationDelivery $delivery): bool
    {
        $payload = $this->eventPayload($delivery);
        $sessionId = (int) ($payload['session_id'] ?? 0);
        $cycleId = (int) ($payload['academic_cycle_id'] ?? 0);
        $institutionId = (int) ($payload['institution_id'] ?? 0);
        $identityId = (int) $delivery->recipient_id;

        if ($delivery->recipient_type !== ApplicantIdentity::class
            || $sessionId < 1 || $cycleId < 1 || $institutionId < 1 || $identityId < 1
            || ! is_string($payload['started_at'] ?? null)
            || ! is_string($payload['effective_end_at'] ?? null)) {
            return false;
        }

        $session = ExamSession::query()
            ->with('academicCycle')
            ->whereKey($sessionId)
            ->where('academic_cycle_id', $cycleId)
            ->where('status', ExamSession::STATUS_IN_PROGRESS)
            ->whereNull('closed_at')
            ->first();

        if ($session === null
            || (int) $session->academicCycle?->institution_id !== $institutionId
            || $session->started_at === null
            || $session->starts_at === null
            || $session->effective_end_at === null
            || $session->started_at->gte($session->starts_at)
            || ! Carbon::parse($payload['started_at'])->equalTo($session->started_at)
            || ! Carbon::parse($payload['effective_end_at'])->equalTo($session->effective_end_at)) {
            return false;
        }

        return $this->hasCurrentApplicantAssignment($sessionId, $cycleId, $institutionId, $identityId);
    }

    private function isCurrentExpectedEndChange(NotificationDelivery $delivery): bool
    {
        $payload = $this->eventPayload($delivery);
        $sessionId = (int) ($payload['session_id'] ?? 0);
        $cycleId = (int) ($payload['academic_cycle_id'] ?? 0);
        $institutionId = (int) ($payload['institution_id'] ?? 0);
        $identityId = (int) $delivery->recipient_id;

        if ($delivery->recipient_type !== ApplicantIdentity::class
            || $sessionId < 1 || $cycleId < 1 || $institutionId < 1 || $identityId < 1
            || ! is_string($payload['effective_end_at'] ?? null)) {
            return false;
        }

        $session = ExamSession::query()
            ->with('academicCycle')
            ->whereKey($sessionId)
            ->where('academic_cycle_id', $cycleId)
            ->where('version', (int) ($payload['session_version'] ?? 0))
            ->whereIn('status', [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS])
            ->first();

        if ($session === null
            || (int) $session->academicCycle?->institution_id !== $institutionId
            || $session->effective_end_at === null
            || ! Carbon::parse($payload['effective_end_at'])->equalTo($session->effective_end_at)) {
            return false;
        }

        return $this->hasCurrentApplicantAssignment($sessionId, $cycleId, $institutionId, $identityId);
    }

    private function isCurrentExamReassignment(NotificationDelivery $delivery): bool
    {
        $payload = $this->eventPayload($delivery);
        $assignmentId = (int) ($payload['assignment_id'] ?? 0);
        $sessionId = (int) ($payload['session_id'] ?? 0);
        $cycleId = (int) ($payload['academic_cycle_id'] ?? 0);
        $institutionId = (int) ($payload['institution_id'] ?? 0);
        $identityId = (int) $delivery->recipient_id;
        $sessionStatus = $payload['session_status'] ?? null;
        if ($delivery->recipient_type !== ApplicantIdentity::class
            || $assignmentId < 1 || $sessionId < 1 || $cycleId < 1 || $institutionId < 1 || $identityId < 1
            || ! in_array($sessionStatus, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)
            || ! is_string($payload['starts_at'] ?? null)
            || ! is_string($payload['effective_end_at'] ?? null)
            || ($sessionStatus === ExamSession::STATUS_IN_PROGRESS && ! is_string($payload['started_at'] ?? null))
            || ($sessionStatus === ExamSession::STATUS_PUBLISHED && ($payload['started_at'] ?? null) !== null)) {
            return false;
        }

        $session = ExamSession::query()
            ->with('academicCycle')
            ->whereKey($sessionId)
            ->where('academic_cycle_id', $cycleId)
            ->where('version', (int) ($payload['session_version'] ?? 0))
            ->where('status', $sessionStatus)
            ->first();

        if ($session === null
            || (int) $session->academicCycle?->institution_id !== $institutionId
            || $session->starts_at === null
            || $session->effective_end_at === null
            || ! Carbon::parse($payload['starts_at'])->equalTo($session->starts_at)
            || ! Carbon::parse($payload['effective_end_at'])->equalTo($session->effective_end_at)
            || ($sessionStatus === ExamSession::STATUS_IN_PROGRESS
                && ($session->started_at === null || ! Carbon::parse($payload['started_at'])->equalTo($session->started_at)))
            || ($sessionStatus === ExamSession::STATUS_PUBLISHED && $session->started_at !== null)) {
            return false;
        }

        return $this->hasCurrentApplicantAssignment($sessionId, $cycleId, $institutionId, $identityId, $assignmentId);
    }

    private function isCurrentExamStartRevert(NotificationDelivery $delivery): bool
    {
        $payload = $this->eventPayload($delivery);
        $sessionId = (int) ($payload['session_id'] ?? 0);
        $cycleId = (int) ($payload['academic_cycle_id'] ?? 0);
        $institutionId = (int) ($payload['institution_id'] ?? 0);
        $identityId = (int) $delivery->recipient_id;

        if ($delivery->recipient_type !== ApplicantIdentity::class
            || $sessionId < 1 || $cycleId < 1 || $institutionId < 1 || $identityId < 1) {
            return false;
        }

        $session = ExamSession::query()
            ->with('academicCycle')
            ->whereKey($sessionId)
            ->where('academic_cycle_id', $cycleId)
            ->where('version', (int) ($payload['session_version'] ?? 0))
            ->where('status', ExamSession::STATUS_PUBLISHED)
            ->first();

        if ($session === null || (int) $session->academicCycle?->institution_id !== $institutionId) {
            return false;
        }

        return $this->hasCurrentApplicantAssignment($sessionId, $cycleId, $institutionId, $identityId);
    }

    private function hasCurrentApplicantAssignment(
        int $sessionId,
        int $cycleId,
        int $institutionId,
        int $identityId,
        ?int $assignmentId = null,
    ): bool {
        $query = ExamAssignment::query()->where('exam_session_id', $sessionId);
        if ($assignmentId !== null) {
            $query->whereKey($assignmentId);
        }

        return $query
            ->active()
            ->whereHas('admissionAttempt', fn (Builder $attempt): Builder => $attempt
                ->where('institution_id', $institutionId)
                ->where('academic_cycle_id', $cycleId)
                ->where('applicant_identity_id', $identityId)
                ->whereHas('identity', fn (Builder $identity): Builder => $identity
                    ->whereKey($identityId)
                    ->where('institution_id', $institutionId)))
            ->exists();
    }

    private function isCurrentResultRelease(NotificationDelivery $delivery): bool
    {
        $payload = $this->eventPayload($delivery);
        foreach (['release_id', 'release_version', 'attempt_id', 'mode', 'identity_id'] as $key) {
            if (! isset($payload[$key])) {
                return false;
            }
        }

        if ($this->containsScoreData($payload)
            || $delivery->recipient_type !== ApplicantIdentity::class
            || (string) $delivery->recipient_id !== (string) $payload['identity_id']) {
            return false;
        }

        $templateMatchesMode = match ($payload['mode']) {
            'online' => $delivery->template_key === 'result.released.online',
            'f2f' => $delivery->template_key === 'result.released.f2f',
            default => false,
        };

        if (! $templateMatchesMode) {
            return false;
        }

        $current = ResultRelease::query()
            ->whereKey($payload['release_id'])
            ->where('admission_attempt_id', $payload['attempt_id'])
            ->where('release_version', $payload['release_version'])
            ->where('mode', $payload['mode'])
            ->where('is_current', true)
            ->whereNull('unreleased_at')
            ->whereHas('admissionAttempt', fn ($query) => $query
                ->where('applicant_identity_id', $payload['identity_id'])
                ->when(($delivery->payload['snapshot_schema_version'] ?? null) === 'notice-delivery-2',
                    fn ($query) => $query->where('institution_id', $payload['institution_id'])))
            ->exists();
        if (! $current) {
            return false;
        }
        $print = $delivery->payload['print'] ?? null;
        if (($delivery->payload['snapshot_schema_version'] ?? null) !== 'notice-delivery-3' || $print === null) {
            return true;
        }
        try {
            app(CanonicalReleasedResultBinding::class)
                ->verify($print['canonical_released_result'], $delivery, requiresData: false);
        } catch (\RuntimeException) {
            return false;
        }

        return true;
    }

    /** @return array<string,mixed> */
    private function eventPayload(NotificationDelivery $delivery): array
    {
        $snapshot = $delivery->payload ?? [];
        if (! in_array(($snapshot['snapshot_schema_version'] ?? null), ['notice-delivery-2', 'notice-delivery-3'], true)) {
            return $snapshot;
        }

        return array_merge($snapshot['event_resource'], [
            'institution_id' => $snapshot['institution_id'],
            'identity_id' => $snapshot['recipient']['id'],
        ]);
    }

    private function containsScoreData(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if (str_contains(strtolower((string) $key), 'score')
                || (is_array($value) && $this->containsScoreData($value))) {
                return true;
            }
        }

        return false;
    }

    private function isCurrentDocumentCorrectionRequest(NotificationDelivery $delivery): bool
    {
        $payload = $delivery->payload ?? [];
        $requestSnapshot = $payload['event_resource']['document_correction_request']
            ?? $payload['document_request_snapshot'] ?? null;
        $requestId = is_array($requestSnapshot)
            ? (int) ($requestSnapshot['id'] ?? $requestSnapshot['document_correction_request_id'] ?? 0)
            : 0;
        $eventResource = $payload['event_resource'] ?? [];
        $attemptId = (int) ($eventResource['attempt_id'] ?? $payload['attempt_id'] ?? 0);
        if ($requestId < 1 || $attemptId < 1 || $delivery->recipient_type !== ApplicantIdentity::class
            || $delivery->event_id !== hash('sha256', 'admission.document_correction_requested:'.$requestId)) {
            return false;
        }

        $attempt = AdmissionAttempt::query()->find($attemptId);
        $request = AdmissionDocumentCorrectionRequest::query()
            ->whereKey($requestId)
            ->where('admission_attempt_id', $attemptId)
            ->first();
        if ($attempt === null || $request === null
            || (int) $attempt->applicant_identity_id !== (int) $delivery->recipient_id
            || (int) $request->institution_id !== (int) $attempt->institution_id
            || (int) $request->academic_cycle_id !== (int) $attempt->academic_cycle_id
            || (string) ($request->practice_fixture_set_id ?? '') !== (string) ($attempt->practice_fixture_set_id ?? '')
            || (isset($eventResource['academic_cycle_id'])
                && (int) $eventResource['academic_cycle_id'] !== (int) $request->academic_cycle_id)) {
            return false;
        }

        foreach (AdmissionDocumentCorrectionRequest::applicantProjectionFor($attempt)['requests'] as $projectedRequest) {
            if ($projectedRequest['id'] === $requestId) {
                return $projectedRequest['status'] === 'current'
                    && collect($projectedRequest['items'])->every(static fn (array $item): bool => $item['status'] === 'current');
            }
        }

        return false;
    }

    private function isCurrentCaseMessageSent(NotificationDelivery $delivery): bool
    {
        $payload = $delivery->payload ?? [];
        foreach (['attempt_id', 'thread_id', 'message_id'] as $key) {
            if (! isset($payload[$key]) || $payload[$key] === '') {
                return false;
            }
        }

        $thread = RecordThread::query()
            ->with(['attempt', 'program'])
            ->find($payload['thread_id']);
        if ($thread === null
            || $thread->attempt === null
            || (string) $thread->admission_attempt_id !== (string) $payload['attempt_id']) {
            return false;
        }

        $message = RecordThreadMessage::query()
            ->whereKey($payload['message_id'])
            ->where('record_thread_id', $thread->id)
            ->first();
        if ($message === null) {
            return false;
        }

        if ($delivery->recipient_type === ApplicantIdentity::class) {
            if ($thread->thread_type !== RecordThread::TYPE_APPLICANT_SUPPORT
                || (string) $thread->attempt->applicant_identity_id !== (string) $delivery->recipient_id) {
                return false;
            }

            $recipient = User::query()
                ->whereHas('applicantAccount', fn (Builder $query): Builder => $query->where('applicant_identity_id', $delivery->recipient_id))
                ->first();
        } elseif ($delivery->recipient_type === User::class) {
            $recipient = User::query()->find($delivery->recipient_id);
        } else {
            return false;
        }

        return $recipient !== null
            && $message->sender_user_id !== null
            && (int) $message->sender_user_id !== (int) $recipient->id
            && app(RecordThreadService::class)->canRead($thread, $recipient);
    }

    private function isCurrentClearanceConfirmed(NotificationDelivery $delivery): bool
    {
        $payload = $this->eventPayload($delivery);
        $attemptId = (int) ($payload['attempt_id'] ?? 0);
        if ($attemptId <= 0) {
            return false;
        }

        $attempt = AdmissionAttempt::query()->find($attemptId);
        if ($attempt === null) {
            return false;
        }

        return AttemptProgramFacts::isClearanceConfirmed($attempt);
    }
}

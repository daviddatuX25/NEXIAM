<?php

namespace App\Notifications\Templates;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionDocumentCorrectionRequest;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Notifications\NotificationIntent;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class AdmissionExaminationNoticeContext
{
    public function __construct(private readonly NoticeRecipientContextResolver $catalog) {}

    /** @return array{institution_id:int,values:array<string,string>,resource:array<string,mixed>} */
    public function resolve(NotificationIntent $intent, User|ApplicantIdentity $recipient): array
    {
        $domain = $intent->payload['domain'] ?? null;
        $slug = $intent->payload['canonical_slug'] ?? null;
        $slot = is_string($domain) && is_string($slug)
            ? (CommunicationTemplateRegistry::findBySlug($domain, $slug)
                ?? CommunicationTemplateRegistry::historicalSlotBySlug($domain, $slug))
            : null;
        if ($slot === null || $slot['event_key'] !== $intent->eventType) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }

        return $domain === 'admissions'
            ? $this->admission($intent, $recipient, $slug)
            : $this->examination($intent, $recipient, $slug);
    }

    /** @return array<string,mixed> */
    public static function admissionPayload(AdmissionAttempt $attempt, string $slug): array
    {
        return ['institution_id' => (int) $attempt->institution_id, 'domain' => 'admissions',
            'canonical_slug' => $slug, 'attempt_version' => (int) ($attempt->version
                ?? AdmissionAttempt::query()->whereKey($attempt->id)->value('version'))];
    }

    /** @return array<string,mixed> */
    public static function examinationPayload(ExamSession $session, string $slug): array
    {
        $session->loadMissing('academicCycle');

        return ['institution_id' => (int) $session->academicCycle?->institution_id, 'domain' => 'examination',
            'canonical_slug' => $slug, 'session_version' => (int) $session->version,
            'schedule_generation' => (int) ($session->notification_policy_snapshot['session_version'] ?? $session->version)];
    }

    /** @param array<string,mixed>|Model $parameters */
    public static function url(string $name, array|Model $parameters = []): string
    {
        return rtrim((string) config('app.url'), '/').route($name, $parameters, absolute: false);
    }

    /** @return array{institution_id:int,values:array<string,string>,resource:array<string,mixed>} */
    private function admission(NotificationIntent $intent, User|ApplicantIdentity $recipient, string $slug): array
    {
        $attempt = AdmissionAttempt::query()->with(['identity', 'academicCycle.institution'])
            ->findOrFail($intent->payload['attempt_id'] ?? null);
        $institutionId = (int) $attempt->institution_id;
        if ($institutionId < 1 || (int) $attempt->academicCycle?->institution_id !== $institutionId
            || (int) $attempt->identity?->institution_id !== $institutionId
            || (int) ($intent->payload['institution_id'] ?? 0) !== $institutionId
            || (int) ($intent->payload['attempt_version'] ?? 0) !== (int) $attempt->version) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }
        if ($slug === 'new-submission') {
            if (! $recipient instanceof User) {
                throw new RuntimeException('notice_recipient_resource_mismatch');
            }
            $this->staff($recipient, $institutionId, 'registrar');
        } elseif (! $recipient instanceof ApplicantIdentity || (int) $attempt->applicant_identity_id !== (int) $recipient->id) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }
        $values = ['applicant_name' => $attempt->identity->fullName(), 'application_no' => $attempt->reference_code,
            'preferred_program' => $attempt->preferred_program_code, 'submission_date' => $attempt->created_at?->format('F j, Y'),
            'institution_name' => $attempt->academicCycle?->institution?->name,
            'portal_url' => $intent->payload['action_url'] ?? null, 'queue_url' => $intent->payload['action_url'] ?? null,
            'correction_notes' => $attempt->correction_notes];
        $resource = ['id' => $attempt->id, 'version' => $attempt->version, 'generation' => $attempt->version,
            'attempt_id' => $attempt->id, 'identity_id' => $attempt->applicant_identity_id,
            'academic_cycle_id' => $attempt->academic_cycle_id];
        if ($slug === 'document-correction-requested') {
            [$requestSnapshot, $documentText] = $this->documentCorrectionRequest($intent, $attempt, $recipient);
            $values['document_requests'] = $documentText;
            $resource['document_correction_request'] = $requestSnapshot;
        }

        return ['institution_id' => $institutionId, 'values' => $this->catalog->allowlisted('admissions', $slug, $values),
            'resource' => $resource];
    }

    /** @return array{institution_id:int,values:array<string,string>,resource:array<string,mixed>} */
    private function examination(NotificationIntent $intent, User|ApplicantIdentity $recipient, string $slug): array
    {
        $session = ExamSession::query()->with(['academicCycle.institution', 'room', 'currentMaterialSnapshot'])
            ->findOrFail($intent->payload['exam_session_id'] ?? $intent->payload['session_id'] ?? null);
        $institutionId = (int) $session->academicCycle?->institution_id;
        if ($institutionId < 1 || (int) ($intent->payload['institution_id'] ?? 0) !== $institutionId
            || (int) ($intent->payload['session_version'] ?? 0) !== (int) $session->version) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }
        $values = ['session_code' => $session->code, 'exam_date' => $session->starts_at?->format('F j, Y'),
            'exam_time' => $session->starts_at?->format('g:i A'),
            'room_name' => (int) $session->room?->institution_id === $institutionId ? $session->room?->name : null,
            'institution_name' => $session->academicCycle?->institution?->name,
            'portal_url' => $intent->payload['action_url'] ?? null];
        $resource = ['id' => $session->id, 'version' => $session->version,
            'generation' => (int) ($intent->payload['schedule_generation'] ?? $session->version),
            'session_id' => $session->id, 'academic_cycle_id' => $session->academic_cycle_id,
            'starts_at' => $session->starts_at?->toIso8601String()];
        if (in_array($slug, ['proctor-duty-assigned', 'session-completed'], true)) {
            if (! $recipient instanceof User) {
                throw new RuntimeException('notice_recipient_resource_mismatch');
            }
            $this->staff($recipient, $institutionId, 'guidance');
            $values['staff_name'] = $recipient->name;
            $resource['staff_id'] = $recipient->id;
            if ($slug === 'proctor-duty-assigned') {
                // Historical notice rendering does not depend on a live duty assignment.
                $resource['duty_id'] = (int) ($intent->payload['duty_id'] ?? 0);
            }
        } else {
            if (! $recipient instanceof ApplicantIdentity || (int) $recipient->institution_id !== $institutionId) {
                throw new RuntimeException('notice_recipient_resource_mismatch');
            }
            $query = ExamAssignment::query()->with(['admissionAttempt.identity', 'activeSeatAllocation.sessionSeat.materialSnapshot'])
                ->where('exam_session_id', $session->id)
                ->whereHas('admissionAttempt', fn ($query) => $query->where('applicant_identity_id', $recipient->id)
                    ->where('institution_id', $institutionId)->where('academic_cycle_id', $session->academic_cycle_id));
            if ($slug === 'session-cancelled') {
                $query->whereIn('id', $intent->payload['notice_assignment_ids'] ?? [])
                    ->where('status', ExamAssignment::STATUS_CANCELLED);
            } else {
                $query->active();
            }
            if (isset($intent->payload['assignment_id'])) {
                $query->whereKey($intent->payload['assignment_id']);
            }
            $assignment = $query->firstOrFail();
            $attempt = $assignment->admissionAttempt;
            if ((int) $attempt->identity?->id !== (int) $recipient->id || (int) $attempt->identity?->institution_id !== $institutionId) {
                throw new RuntimeException('notice_recipient_resource_mismatch');
            }
            $values['applicant_name'] = $attempt->identity->fullName();
            $values['application_no'] = $attempt->reference_code;
            $values['cancellation_reason'] = $slug === 'session-cancelled' ? $assignment->end_reason : null;
            $allocation = $assignment->activeSeatAllocation;
            $seat = $allocation?->sessionSeat;
            $snapshot = $seat?->materialSnapshot;
            if ($assignment->isActive() && $allocation !== null && $allocation->effective_to === null
                && (int) $allocation->exam_assignment_id === (int) $assignment->id
                && $snapshot?->is_current
                && $snapshot->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
                && (int) $snapshot->exam_session_id === (int) $session->id
                && (int) $session->currentMaterialSnapshot?->id === (int) $snapshot->id
                && $seat !== null && (int) $seat->material_snapshot_id === (int) $snapshot->id) {
                $values['seat_number'] = $seat->seat_label;
            }
            $resource += ['assignment_id' => $assignment->id, 'assignment_version' => $assignment->version,
                'attempt_id' => $attempt->id, 'identity_id' => $recipient->id];
        }

        return ['institution_id' => $institutionId, 'values' => $this->catalog->allowlisted('examination', $slug, $values),
            'resource' => $resource];
    }

    /**
     * @return array{array<string,mixed>,string}
     */
    private function documentCorrectionRequest(
        NotificationIntent $intent,
        AdmissionAttempt $attempt,
        User|ApplicantIdentity $recipient,
    ): array {
        if (! $recipient instanceof ApplicantIdentity) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }

        $request = AdmissionDocumentCorrectionRequest::query()
            ->with('items')
            ->where('admission_attempt_id', $attempt->getKey())
            ->findOrFail($intent->payload['document_correction_request_id'] ?? null);
        if ((int) $request->institution_id !== (int) $attempt->institution_id
            || (int) $request->academic_cycle_id !== (int) $attempt->academic_cycle_id
            || (string) ($request->practice_fixture_set_id ?? '') !== (string) ($attempt->practice_fixture_set_id ?? '')
            || (int) $request->requested_by_user_id < 1
            || (int) $attempt->applicant_identity_id !== (int) $recipient->getKey()
            || (int) ($intent->payload['attempt_id'] ?? 0) !== (int) $attempt->getKey()
            || (int) ($intent->payload['academic_cycle_id'] ?? 0) !== (int) $request->academic_cycle_id
            || (string) ($intent->payload['practice_fixture_set_id'] ?? '') !== (string) ($request->practice_fixture_set_id ?? '')) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }

        $items = $request->items->map(static fn ($item): array => [
            'requirement_item_id' => (int) $item->requirement_item_id,
            'label' => $item->label,
            'observed_status' => $item->observed_status,
            'expected_latest_verification_id' => $item->expected_latest_verification_id === null
                ? null : (int) $item->expected_latest_verification_id,
            'applicant_instruction' => $item->applicant_instruction,
        ])->values()->all();
        if ($items === []) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }

        $requestSnapshot = [
            'id' => (int) $request->getKey(),
            'recorded_at' => $request->recorded_at?->toIso8601String(),
            'overall_note' => $request->overall_note,
            'items' => $items,
        ];
        $text = [];
        if ($request->overall_note !== null && $request->overall_note !== '') {
            $text[] = $request->overall_note;
        }
        foreach ($items as $item) {
            $text[] = $item['label'].': '.$item['applicant_instruction'];
        }

        return [$requestSnapshot, implode('; ', $text)];
    }

    private function staff(User $recipient, int $institutionId, string $office): void
    {
        $profile = $recipient->staffProfile()->first();
        if ($profile === null || ! $profile->isActive() || (int) $profile->institution_id !== $institutionId || $profile->office !== $office) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }
    }
}

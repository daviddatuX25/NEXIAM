<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Release\ResultRelease;
use App\Notifications\NotificationIntent;
use RuntimeException;

final class ResultNoticeRecipientContextResolver
{
    public function __construct(private readonly NoticeRecipientContextResolver $contexts,
        private readonly CanonicalReleasedResultBinding $releasedBinding) {}

    /** @return array{institution_id:int,values:array,resource:array,print_binding?:array} */
    public function resolve(NotificationIntent $intent, ApplicantIdentity $recipient, string $slug): array
    {
        return match ($slug) {
            'released-online', 'released-f2f', 'unreleased' => $this->release($intent, $recipient, $slug),
            'consultation-published', 'consultation-reserved' => $this->consultation($intent, $recipient, $slug),
            'consultation-online-invited' => $this->online($intent, $recipient),
            default => throw new RuntimeException('notice_result_slot_unknown'),
        };
    }

    private function release(NotificationIntent $intent, ApplicantIdentity $recipient, string $slug): array
    {
        $release = ResultRelease::query()->with(['admissionAttempt.academicCycle.institution', 'resultDocument'])
            ->whereKey($intent->payload['release_id'] ?? null)->firstOrFail();
        $attempt = $release->admissionAttempt;
        $values = $this->applicant($attempt, $recipient, $this->url('applicant.portal'));
        if ((int) $attempt->id !== (int) ($intent->payload['attempt_id'] ?? 0)
            || ($slug === 'released-online' && $release->mode !== 'online')
            || ($slug === 'released-f2f' && $release->mode !== 'f2f')) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }
        $context = ['institution_id' => (int) $attempt->institution_id,
            'values' => $this->contexts->allowlisted('results', $slug, $values),
            'resource' => ['id' => (int) $release->id, 'version' => (int) $release->release_version,
                'generation' => (int) $release->release_version, 'release_id' => (int) $release->id,
                'release_version' => (int) $release->release_version, 'attempt_id' => (int) $attempt->id,
                'identity_id' => (int) $recipient->id, 'mode' => $release->mode,
                'document_id' => (int) $release->result_document_id]];
        if ($slug === 'released-online') {
            $context['print_binding'] = $this->releasedBinding->freeze($release, $release->resultDocument, $attempt, $recipient);
        }

        return $context;
    }

    private function consultation(NotificationIntent $intent, ApplicantIdentity $recipient, string $slug): array
    {
        $assignment = ConsultationAssignment::query()->active()
            ->where('consultation_session_id', $intent->payload['consultation_session_id'] ?? null)
            ->whereHas('admissionAttempt', fn ($query) => $query->where('applicant_identity_id', $recipient->id))
            ->when(isset($intent->payload['assignment_id']), fn ($query) => $query->whereKey($intent->payload['assignment_id']))
            ->with(['admissionAttempt.academicCycle.institution', 'consultationSession.academicCycle', 'consultationSession.room'])
            ->firstOrFail();
        $session = $assignment->consultationSession;
        $attempt = $assignment->admissionAttempt;
        $values = $this->applicant($attempt, $recipient, $this->url('applicant.consultation'));
        if ((int) $session->academicCycle?->institution_id !== (int) $attempt->institution_id
            || (int) $session->academic_cycle_id !== (int) $attempt->academic_cycle_id
            || ($session->room !== null && (int) $session->room->institution_id !== (int) $attempt->institution_id)) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }
        $values['session_code'] = $session->code;
        if ($session->scheduled_at !== null) {
            $values['consultation_date'] = $session->scheduled_at->format('F j, Y');
            $values['time_slot'] = $session->scheduled_at->format('g:i A');
        }
        if ($session->room !== null) {
            $values['room_name'] = $session->room->name;
        }

        return ['institution_id' => (int) $attempt->institution_id,
            'values' => $this->contexts->allowlisted('results', $slug, $values),
            'resource' => ['id' => (int) $session->id, 'version' => (int) $session->version,
                'generation' => (int) $session->version, 'consultation_session_id' => (int) $session->id,
                'assignment_id' => (int) $assignment->id, 'attempt_id' => (int) $attempt->id,
                'identity_id' => (int) $recipient->id]];
    }

    private function online(NotificationIntent $intent, ApplicantIdentity $recipient): array
    {
        $encounter = ConsultationEncounter::query()->with('admissionAttempt.academicCycle.institution')
            ->whereKey($intent->payload['consultation_encounter_id'] ?? null)->firstOrFail();
        $attempt = $encounter->admissionAttempt;
        $values = $this->applicant($attempt, $recipient, $this->url('applicant.portal'));
        if ((int) $encounter->institution_id !== (int) $attempt->institution_id
            || (int) ($intent->payload['academic_cycle_id'] ?? 0) !== (int) $attempt->academic_cycle_id) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }
        if ($encounter->access_starts_at !== null) {
            $values['consultation_date'] = $encounter->access_starts_at->format('F j, Y');
            $values['time_slot'] = $encounter->access_starts_at->format('g:i A');
        }

        return ['institution_id' => (int) $attempt->institution_id,
            'values' => $this->contexts->allowlisted('results', 'consultation-online-invited', $values),
            'resource' => ['id' => (int) $encounter->id, 'version' => (int) $encounter->schedule_generation,
                'generation' => (int) $encounter->schedule_generation, 'consultation_encounter_id' => (int) $encounter->id,
                'admission_attempt_id' => (int) $attempt->id, 'identity_id' => (int) $recipient->id,
                'institution_id' => (int) $attempt->institution_id, 'academic_cycle_id' => (int) $attempt->academic_cycle_id,
                'schedule_generation' => (int) $encounter->schedule_generation,
                'consultation_policy_version' => (int) $encounter->consultation_policy_version,
                'booking_path' => $encounter->booking_path,
                'access_starts_at' => $encounter->access_starts_at?->toIso8601String(),
                'access_ends_at' => $encounter->access_ends_at?->toIso8601String(),
                'result_document_id' => (int) $encounter->result_document_id,
                'result_document_version' => (int) $encounter->result_document_version]];
    }

    private function applicant(AdmissionAttempt $attempt, ApplicantIdentity $recipient, string $portal): array
    {
        if ((int) $attempt->applicant_identity_id !== (int) $recipient->id
            || (int) $attempt->institution_id !== (int) $recipient->institution_id
            || (int) $attempt->academicCycle?->institution_id !== (int) $attempt->institution_id) {
            throw new RuntimeException('notice_recipient_resource_mismatch');
        }

        return ['applicant_name' => $recipient->fullName(), 'application_no' => $attempt->reference_code,
            'institution_name' => $attempt->academicCycle->institution->name, 'portal_url' => $portal];
    }

    private function url(string $route): string
    {
        return rtrim((string) config('app.url'), '/').route($route, [], false);
    }
}

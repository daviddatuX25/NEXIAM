<?php

namespace App\Notifications;

use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Recipient derivation for notification intents. Centralizes "who should get
 * this event" so Actions never inline recipient lookups.
 */
final class NotificationRecipients
{
    /**
     * Portal users for the assigned applicants of a session (in_app + mail).
     *
     * @return Collection<int, User>
     */
    public static function assignedApplicantsFor(ExamSession $session): Collection
    {
        return ExamAssignment::query()
            ->where('exam_session_id', $session->id)
            ->active()
            ->with('admissionAttempt.identity.applicantAccount.user')
            ->get()
            ->map(fn (ExamAssignment $assignment): ?User => $assignment->admissionAttempt?->identity?->applicantAccount?->user)
            ->filter(fn (?User $user): bool => $user !== null)
            ->unique('id')
            ->values();
    }

    /**
     * Portal users for the assigned applicants of a consultation session (in_app + mail).
     *
     * @return Collection<int, User>
     */
    public static function assignedConsultationApplicantsFor(ConsultationSession $session): Collection
    {
        return ConsultationAssignment::query()
            ->where('consultation_session_id', $session->id)
            ->active()
            ->with('admissionAttempt.identity.applicantAccount.user')
            ->get()
            ->map(fn (ConsultationAssignment $assignment): ?User => $assignment->admissionAttempt?->identity?->applicantAccount?->user)
            ->filter(fn (?User $user): bool => $user !== null)
            ->unique('id')
            ->values();
    }

    /**
     * Portal user for one admission attempt (the applicant's own account).
     */
    public static function applicantFor(AdmissionAttempt $attempt): ?User
    {
        return $attempt->identity?->applicantAccount?->user;
    }

    /**
     * Active proctors of a session (in_app staff bell).
     *
     * @return Collection<int, User>
     */
    public static function proctorsFor(ExamSession $session): Collection
    {
        return ProctorDuty::query()
            ->where('exam_session_id', $session->id)
            ->where('status', ProctorDuty::STATUS_ACTIVE)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn (?User $user): bool => $user !== null)
            ->unique('id')
            ->values();
    }

    /**
     * Staff who hold the Test Administrator capability set — a stable,
     * capability-based "all exam ops staff" recipient set (never role strings).
     *
     * @return Collection<int, User>
     */
    public static function testAdministratorUsers(): Collection
    {
        $users = User::query()
            ->whereHas('permissions', fn ($query) => $query->whereIn(
                'name',
                [CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE],
            ))
            ->get();

        return $users->unique('id')->values();
    }
}

<?php

namespace App\Notifications;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Derives recipients from the committed target relationship, then enforces its
 * institution/office scope. Applicant notifications address stable identities,
 * not whichever email happens to match a User row.
 */
final class NotificationRecipients
{
    /**
     * Applicant identities with an active assignment to this session.
     *
     * @return Collection<int, ApplicantIdentity>
     */
    public static function assignedApplicantsFor(ExamSession $session): Collection
    {
        $session->loadMissing('academicCycle');
        $institutionId = (int) ($session->academicCycle?->institution_id ?? 0);

        if ($institutionId === 0) {
            return collect();
        }

        return ExamAssignment::query()
            ->where('exam_session_id', $session->id)
            ->active()
            ->with('admissionAttempt.identity')
            ->get()
            ->map(fn (ExamAssignment $assignment): ?ApplicantIdentity => self::identityForAttempt(
                $assignment->admissionAttempt,
                $institutionId,
                (int) $session->academic_cycle_id,
            ))
            ->filter(fn (?ApplicantIdentity $identity): bool => $identity !== null)
            ->unique('id')
            ->values();
    }

    /**
     * Applicant identities with an active assignment to this consultation session.
     *
     * @return Collection<int, ApplicantIdentity>
     */
    public static function assignedConsultationApplicantsFor(ConsultationSession $session): Collection
    {
        $session->loadMissing('academicCycle');
        $institutionId = (int) ($session->academicCycle?->institution_id ?? 0);

        if ($institutionId === 0) {
            return collect();
        }

        return ConsultationAssignment::query()
            ->where('consultation_session_id', $session->id)
            ->active()
            ->with('admissionAttempt.identity')
            ->get()
            ->map(fn (ConsultationAssignment $assignment): ?ApplicantIdentity => self::identityForAttempt(
                $assignment->admissionAttempt,
                $institutionId,
            ))
            ->filter(fn (?ApplicantIdentity $identity): bool => $identity !== null)
            ->unique('id')
            ->values();
    }

    /**
     * Applicant identity for one attempt, independent of portal activation.
     */
    public static function applicantFor(AdmissionAttempt $attempt): ?ApplicantIdentity
    {
        return self::identityForAttempt($attempt, (int) $attempt->institution_id);
    }

    /**
     * Active Registrar staff who can view admissions for this identity's institution.
     *
     * @return Collection<int, User>
     */
    public static function registrarApplicationViewersFor(
        ApplicantIdentity $identity,
        CapabilityAuthorizer $authorizer,
    ): Collection {
        $institutionId = (int) $identity->institution_id;

        if ($institutionId <= 0) {
            return collect();
        }

        $scope = [
            'institution_id' => $institutionId,
            'office' => 'registrar',
        ];

        return User::query()
            ->whereHas('staffProfile', fn (Builder $query): Builder => $query
                ->where('institution_id', $institutionId)
                ->where('office', 'registrar')
                ->where('status', StaffProfile::STATUS_ACTIVE))
            ->with('staffProfile')
            ->get()
            ->filter(fn (User $user): bool => $authorizer->allows(
                $user,
                CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
                $scope,
            ))
            ->unique('id')
            ->values();
    }

    /**
     * Guidance staff authorized for exam scheduling in this session's scope.
     *
     * @return Collection<int, User>
     */
    public static function testAdministratorUsersFor(
        ExamSession $session,
        CapabilityAuthorizer $authorizer,
    ): Collection {
        $session->loadMissing('academicCycle');
        $institutionId = (int) ($session->academicCycle?->institution_id ?? 0);

        if ($institutionId === 0) {
            return collect();
        }

        $scope = [
            'institution_id' => $institutionId,
            'office' => 'guidance',
        ];

        return User::query()
            ->whereHas('staffProfile', fn (Builder $query): Builder => $query
                ->where('institution_id', $institutionId)
                ->where('office', 'guidance')
                ->where('status', StaffProfile::STATUS_ACTIVE))
            ->with('staffProfile')
            ->get()
            ->filter(fn (User $user): bool => $authorizer->allows(
                $user,
                CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
                $scope,
            ))
            ->unique('id')
            ->values();
    }

    private static function identityForAttempt(?AdmissionAttempt $attempt, int $institutionId, ?int $academicCycleId = null): ?ApplicantIdentity
    {
        $identity = $attempt?->identity;

        if ($attempt === null
            || $identity === null
            || (int) $attempt->institution_id !== $institutionId
            || ($academicCycleId !== null && (int) $attempt->academic_cycle_id !== $academicCycleId)
            || (int) $identity->institution_id !== $institutionId
            || (int) $attempt->applicant_identity_id !== (int) $identity->id) {
            return null;
        }

        return $identity;
    }
}

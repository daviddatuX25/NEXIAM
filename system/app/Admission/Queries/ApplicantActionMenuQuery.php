<?php

namespace App\Admission\Queries;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;

/**
 * D-092: server-authored action-hub destinations for the current actor after
 * a successful QR resolve. Destinations come from ordinary capability, duty,
 * office/program/cycle relationship, and case state — never from the QR.
 * A denied/not-found destination reveals no applicant data.
 */
final class ApplicantActionMenuQuery
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    public function for(AdmissionAttempt $attempt, User $actor): array
    {
        $destinations = [];
        $programCodes = $actor->actorScope()->programCodes;

        if ($this->authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)) {
            $destinations[] = [
                'key' => 'application_facts',
                'label' => 'View application facts',
                'url' => route('admissions.show', $attempt),
            ];
        }

        if ($this->authorizer->allows($actor, CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY)) {
            $destinations[] = [
                'key' => 'enrollment_eligibility',
                'label' => 'Check enrollment eligibility',
                'url' => route('registrar.enrollment-eligibility.show', $attempt),
            ];
        }

        if ($this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
            || $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)
            || $this->authorizer->allows($actor, CapabilityCatalog::PROGRAM_HEAD_VIEW, ['program' => $programCodes[0] ?? ''])) {
            $destinations[] = [
                'key' => 'admission_support',
                'label' => 'Open admission support',
                'url' => route('admission-support.show', $attempt),
            ];
        }

        if ($this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)) {
            $destinations[] = [
                'key' => 'consultation_dashboard',
                'label' => 'Open consultation dashboard',
                'url' => route('consultation.index'),
            ];
        }

        return $destinations;
    }
}

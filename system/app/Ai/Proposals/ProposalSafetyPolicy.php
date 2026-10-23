<?php

namespace App\Ai\Proposals;

final class ProposalSafetyPolicy
{
    /**
     * @var array<string, array{tier: string, ttl_minutes: int, phrase: string|null, requires_reauth: bool}>
     */
    private const REQUIREMENTS = [
        'exams.session.create@1' => ['tier' => 't1', 'ttl_minutes' => 30, 'phrase' => null, 'requires_reauth' => false],
        'consultation.session.create@1' => ['tier' => 't1', 'ttl_minutes' => 30, 'phrase' => null, 'requires_reauth' => false],
        'exams.session.assign@1' => ['tier' => 't1', 'ttl_minutes' => 30, 'phrase' => null, 'requires_reauth' => false],
        'exams.session.reservation.open@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => null, 'requires_reauth' => false],
        'exams.session.publish@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => null, 'requires_reauth' => false],
        'consultation.session.reservation.open@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => null, 'requires_reauth' => false],
        'consultation.session.cancel@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => 'CANCEL SESSION', 'requires_reauth' => false],
        'consultation.decision.record@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => 'CONFIRM DECISION', 'requires_reauth' => false],
        'consultation.decision.record@2' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => 'CONFIRM DECISION', 'requires_reauth' => false],
        'exams.session.start@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => 'START SESSION', 'requires_reauth' => false],
        'exams.session.close@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => 'CLOSE SESSION', 'requires_reauth' => false],
        'setup.rooms.create_draft@1' => ['tier' => 't1', 'ttl_minutes' => 30, 'phrase' => null, 'requires_reauth' => false],
        'setup.cycles.create_draft@1' => ['tier' => 't1', 'ttl_minutes' => 30, 'phrase' => null, 'requires_reauth' => false],
        'setup.rooms.update@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => 'UPDATE ROOM', 'requires_reauth' => false],
        'setup.quotas.upsert@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => 'UPDATE QUOTA', 'requires_reauth' => false],
        'setup.notifications.update_admission@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => 'UPDATE NOTIFICATIONS', 'requires_reauth' => false],
        'setup.notifications.update_exam@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => 'UPDATE NOTIFICATIONS', 'requires_reauth' => false],
        'setup.windows.store@1' => ['tier' => 't2', 'ttl_minutes' => 10, 'phrase' => 'UPDATE APPLICATION WINDOW', 'requires_reauth' => false],
        'setup.cycles.activate@1' => ['tier' => 't3', 'ttl_minutes' => 5, 'phrase' => 'ACTIVATE CYCLE', 'requires_reauth' => true],
        'admissions.attempt.bulk_accept@1' => ['tier' => 't3', 'ttl_minutes' => 5, 'phrase' => 'ACCEPT APPLICANTS', 'requires_reauth' => true],
        'assessment.grading.finalize@1' => ['tier' => 't3', 'ttl_minutes' => 5, 'phrase' => 'FINALIZE GRADES', 'requires_reauth' => true],
        'release.result.publish@1' => ['tier' => 't3', 'ttl_minutes' => 5, 'phrase' => 'RELEASE RESULTS', 'requires_reauth' => true],
        'release.result.publish@2' => ['tier' => 't3', 'ttl_minutes' => 5, 'phrase' => 'ISSUE RESULT TO PORTAL', 'requires_reauth' => true],
        'registrar.enrollment.stamp_enrolled@1' => ['tier' => 't3', 'ttl_minutes' => 5, 'phrase' => 'ENROLL APPLICANT', 'requires_reauth' => true],
    ];

    /** @param array<string, mixed> $command */
    public function evaluate(string $capabilityId, array $command = []): SafetyRequirement
    {
        $requirement = self::REQUIREMENTS[$capabilityId] ?? [
            'tier' => 't1',
            'ttl_minutes' => 30,
            'phrase' => null,
            'requires_reauth' => false,
        ];

        return new SafetyRequirement(
            tier: $requirement['tier'],
            ttlMinutes: $requirement['ttl_minutes'],
            confirmationPhrase: $requirement['phrase'],
            requiresReauth: $requirement['requires_reauth'],
        );
    }
}

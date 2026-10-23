<?php

namespace App\Reports\Queries;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\User;

final class StaffAccomplishmentDataQuery implements ContextualReadToolHandler
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        $institutionId = (int) ($context->institutionId ?? $actor->actorScope()->institutionId ?? 0);
        $cycleId = isset($input['academic_cycle_id']) && is_numeric($input['academic_cycle_id'])
            ? (int) $input['academic_cycle_id']
            : null;

        return $this->forXiamSafe($institutionId, $cycleId);
    }

    /**
     * @return array{
     *   institution_id: int,
     *   cycle_id: ?int,
     *   cycle_name: string,
     *   total_applications_received: int,
     *   total_applications_verified: int,
     *   total_examinees_tested: int,
     *   total_sessions_scored: int,
     *   total_clearances_issued: int,
     *   total_audit_events: int,
     *   milestones: list<array{
     *     office: string,
     *     activity_milestone: string,
     *     standard_metric: string,
     *     volume_processed: int,
     *     completion_status: string,
     *     benchmark_yield: string
     *   }>,
     *   as_of: string
     * }
     */
    public function forXiamSafe(int $institutionId, ?int $cycleId = null): array
    {
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->when(
                $cycleId === null,
                fn ($query) => $query->where('is_active', true),
                fn ($query) => $query->whereKey($cycleId),
            )
            ->first();
        $resolvedCycleId = $cycle === null ? null : (int) $cycle->id;

        $totalApplications = $resolvedCycleId === null
            ? 0
            : AdmissionAttempt::query()
                ->forPracticeSet(null)
                ->where('institution_id', $institutionId)
                ->where('academic_cycle_id', $resolvedCycleId)
                ->count();
        $totalVerified = $resolvedCycleId === null
            ? 0
            : AdmissionAttempt::query()
                ->forPracticeSet(null)
                ->where('institution_id', $institutionId)
                ->where('academic_cycle_id', $resolvedCycleId)
                ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
                ->count();
        $totalExamineesTested = $resolvedCycleId === null
            ? 0
            : ExamAttendanceRecord::query()
                ->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT)
                ->whereHas('examAssignment.examSession', fn ($query) => $query
                    ->where('academic_cycle_id', $resolvedCycleId)
                    ->whereNull('practice_fixture_set_id')
                    ->whereHas('academicCycle', fn ($cycleQuery) => $cycleQuery->where('institution_id', $institutionId)))
                ->count();
        $totalSessionsScored = $resolvedCycleId === null
            ? 0
            : GradingSession::query()
                ->where('status', GradingSession::STATUS_FINALIZED)
                ->whereHas('examSession', fn ($query) => $query
                    ->where('academic_cycle_id', $resolvedCycleId)
                    ->whereNull('practice_fixture_set_id')
                    ->whereHas('academicCycle', fn ($cycleQuery) => $cycleQuery->where('institution_id', $institutionId)))
                ->count();
        $totalClearances = $resolvedCycleId === null
            ? 0
            : AdmissionClearanceReceipt::query()
                ->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED)
                ->whereHas('admissionAttempt', fn ($query) => $query
                    ->forPracticeSet(null)
                    ->where('institution_id', $institutionId)
                    ->where('academic_cycle_id', $resolvedCycleId))
                ->count();
        $totalAuditEvents = $resolvedCycleId === null
            ? 0
            : AuditEvent::query()
                ->where('payload->institution_id', $institutionId)
                ->whereNull('payload->practice_fixture_set_id')
                ->where(fn ($query) => $query
                    ->where('payload->academic_cycle_id', $resolvedCycleId)
                    ->orWhere('payload->cycle_id', $resolvedCycleId))
                ->count();

        $milestones = [
            [
                'office' => 'Registrar Office',
                'activity_milestone' => 'Application Intake & Dossier Processing',
                'standard_metric' => 'Total Applications Received',
                'volume_processed' => $totalApplications,
                'completion_status' => 'In Progress',
                'benchmark_yield' => '100%',
            ],
            [
                'office' => 'Registrar Office',
                'activity_milestone' => 'Verification & Requirement Endorsement',
                'standard_metric' => 'Qualified & Accepted Applicants',
                'volume_processed' => $totalVerified,
                'completion_status' => 'Completed',
                'benchmark_yield' => $totalApplications > 0
                    ? number_format(($totalVerified / $totalApplications) * 100, 1).'%'
                    : '0.0%',
            ],
            [
                'office' => 'Guidance & Testing',
                'activity_milestone' => 'Institutional Exam Administration',
                'standard_metric' => 'Present Examinees Tested',
                'volume_processed' => $totalExamineesTested,
                'completion_status' => 'Verified',
                'benchmark_yield' => $totalApplications > 0
                    ? number_format(($totalExamineesTested / $totalApplications) * 100, 1).'%'
                    : '0.0%',
            ],
            [
                'office' => 'Guidance & Testing',
                'activity_milestone' => 'Test Kit Scoring & Psychometric Validation',
                'standard_metric' => 'Finalized Grading Sessions',
                'volume_processed' => $totalSessionsScored,
                'completion_status' => 'Finalized',
                'benchmark_yield' => '100%',
            ],
            [
                'office' => 'Registrar Office',
                'activity_milestone' => 'Admission Clearance & Enrollment Endorsement',
                'standard_metric' => 'Confirmed Clearance Certificates Issued',
                'volume_processed' => $totalClearances,
                'completion_status' => 'Certified',
                'benchmark_yield' => $totalVerified > 0
                    ? number_format(($totalClearances / $totalVerified) * 100, 1).'%'
                    : '0.0%',
            ],
        ];

        return [
            'institution_id' => $institutionId,
            'cycle_id' => $resolvedCycleId,
            'cycle_name' => $cycle?->staffLabel() ?? '',
            'total_applications_received' => $totalApplications,
            'total_applications_verified' => $totalVerified,
            'total_examinees_tested' => $totalExamineesTested,
            'total_sessions_scored' => $totalSessionsScored,
            'total_clearances_issued' => $totalClearances,
            'total_audit_events' => $totalAuditEvents,
            'milestones' => $milestones,
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $cycleId = isset($filters['academic_cycle_id'])
            ? (int) $filters['academic_cycle_id']
            : AcademicCycle::active()?->id;

        $cycle = $cycleId === null ? null : AcademicCycle::query()->with('institution')->find($cycleId);

        $totalApps = AdmissionAttempt::query()->forPracticeSet(null)
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->count();

        $verifiedApps = AdmissionAttempt::query()->forPracticeSet(null)
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->count();

        $examineesTested = ExamAttendanceRecord::query()
            ->where('outcome', 'present')
            ->when($cycleId !== null, fn ($q) => $q->whereHas('examAssignment.examSession', fn ($sq) => $sq->where('academic_cycle_id', $cycleId)))
            ->count();

        $sessionsScored = GradingSession::query()
            ->where('status', GradingSession::STATUS_FINALIZED)
            ->when($cycleId !== null, fn ($q) => $q->whereHas('examSession', fn ($sq) => $sq->where('academic_cycle_id', $cycleId)))
            ->count();

        $clearancesIssued = AdmissionClearanceReceipt::query()
            ->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED)
            ->when($cycleId !== null, fn ($q) => $q->whereHas('admissionAttempt', fn ($aq) => $aq->where('academic_cycle_id', $cycleId)))
            ->count();

        $totalAuditEvents = AuditEvent::query()->count();

        $activityRows = [
            [
                'row_no' => 1,
                'office' => 'Registrar Office',
                'activity_milestone' => 'Application Intake & Dossier Processing',
                'standard_metric' => 'Total Applications Received',
                'volume_processed' => $totalApps,
                'completion_status' => 'In Progress',
                'benchmark_yield' => '100%',
            ],
            [
                'row_no' => 2,
                'office' => 'Registrar Office',
                'activity_milestone' => 'Verification & Requirement Endorsement',
                'standard_metric' => 'Qualified & Accepted Applicants',
                'volume_processed' => $verifiedApps,
                'completion_status' => 'Completed',
                'benchmark_yield' => $totalApps > 0 ? number_format(($verifiedApps / $totalApps) * 100, 1).'%' : '0.0%',
            ],
            [
                'row_no' => 3,
                'office' => 'Guidance & Testing',
                'activity_milestone' => 'Institutional Exam Administration',
                'standard_metric' => 'Present Examinees Tested',
                'volume_processed' => $examineesTested,
                'completion_status' => 'Verified',
                'benchmark_yield' => $totalApps > 0 ? number_format(($examineesTested / max(1, $totalApps)) * 100, 1).'%' : '0.0%',
            ],
            [
                'row_no' => 4,
                'office' => 'Guidance & Testing',
                'activity_milestone' => 'Test Kit Scoring & Psychometric Validation',
                'standard_metric' => 'Finalized Grading Sessions',
                'volume_processed' => $sessionsScored,
                'completion_status' => 'Finalized',
                'benchmark_yield' => '100%',
            ],
            [
                'row_no' => 5,
                'office' => 'Registrar Office',
                'activity_milestone' => 'Admission Clearance & Enrollment Endorsement',
                'standard_metric' => 'Confirmed Clearance Certificates Issued',
                'volume_processed' => $clearancesIssued,
                'completion_status' => 'Certified',
                'benchmark_yield' => $verifiedApps > 0 ? number_format(($clearancesIssued / max(1, $verifiedApps)) * 100, 1).'%' : '0.0%',
            ],
        ];

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'total_milestones' => count($activityRows),
            'total_operations_volume' => $totalApps + $verifiedApps + $examineesTested + $clearancesIssued,
            'total_audit_events' => $totalAuditEvents,
            'reporting_period' => $cycle?->staffLabel() ?? now()->format('Y'),
            'accomplishments' => $activityRows,
            'prepared_by' => 'Administrative Officer',
            'verified_by' => 'Head of Human Resources / Admin',
            'approved_by' => 'University President',
        ];
    }

    private function escapeText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}

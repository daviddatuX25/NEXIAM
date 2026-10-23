<?php

namespace App\Testing\Queries;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\AttemptProgramFacts;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\AttemptResultReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\ScoreRecord;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\Program;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use Illuminate\Support\Collection;

/** Read-only operator prerequisites, never participant outcomes or instrument mappings. */
final class PracticeResearchAssignments
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ApplicantOfficeInquiryAccess $inquiries,
    ) {}

    /**
     * @param  Collection<int, AdmissionAttempt>  $attempts
     * @param  list<array<string, mixed>>  $caseRows
     * @param  array<string, mixed>  $pairedExchange
     * @return array{designated_case_id: ?int, task_assignments: list<array{task_id: string, case_id: ?int, actor: string, stage: ?string, account_user_id?: int, session_id?: int, document_id?: int, url: ?string, available: bool, reason: string}>}
     */
    public function for(PracticeFixtureSet $set, Collection $attempts, array $caseRows, array $pairedExchange): array
    {
        $owned = $attempts->filter(static fn (AdmissionAttempt $attempt): bool => (string) $attempt->practice_fixture_set_id === (string) $set->id
            && (int) $attempt->institution_id === (int) $set->institution_id
            && (int) $attempt->academic_cycle_id === (int) $set->academic_cycle_id
        )->keyBy('id');
        foreach ($owned as $attempt) {
            $attempt->loadMissing(['identity.applicantAccount', 'resultDocuments', 'examAssignments.examSession.gradingSession', 'examAssignments.attendanceRecord', 'examAssignments.submissionRecord']);
        }
        $cases = collect($caseRows)->filter(static fn (array $row): bool => $owned->has($row['id']))
            ->map(fn (array $row): array => $row + ['attempt' => $owned->get($row['id'])])->values();
        $pins = $set->material_snapshot['designated_cases'] ?? [];
        $student = $this->choose($cases->where('stage', 'program'), 'applicant', $pins['student_primary'] ?? null, fn (array $row): bool => $this->document($row) !== null);
        $notice = $this->choose($cases->where('stage', 'review'), 'applicant', $pins['student_notice'] ?? null, fn (array $row): bool => $this->hasNotice($row));
        $read = $this->choose($cases->where('stage', 'review'), 'review', $pins['registrar_read'] ?? null, static fn (array $row): bool => $row['attempt']->status !== AdmissionAttempt::STATUS_PENDING);
        $pending = $this->choose($cases->where('stage', 'review'), 'review', $pins['registrar_correction'] ?? null, static fn (array $row): bool => $row['attempt']->status === AdmissionAttempt::STATUS_PENDING);
        $enrollment = $this->choose($cases->where('stage', 'enrollment'), 'enrollment', $pins['registrar_progressed'] ?? null);
        $grading = $this->choose($cases->where('stage', 'grading'), 'omr', preferred: fn (array $row): bool => $this->omrPrerequisite($set, $row)[0]);
        $document = $this->choose($cases->whereIn('stage', ['program', 'consultation']), 'print', $pins['pair'] ?? null, fn (array $row): bool => $this->document($row) !== null);
        $consultation = $this->choose($cases->whereIn('stage', ['consultation', 'program']), 'consultation', $pins['pair'] ?? null, fn (array $row): bool => $this->document($row) !== null);
        $live = $this->choose($cases->where('stage', 'conduct'), 'monitoring', preferred: fn (array $row): bool => $this->attendanceWindow($set, $row) && $this->unmarkedAttendance($set, $row));
        $scheduling = $this->choose($cases->whereIn('stage', ['reservation', 'conduct']), 'scheduling');
        $academic = $this->choose($cases->where('stage', 'program'), 'academic');
        $paired = $cases->firstWhere('id', (int) ($pairedExchange['case_id'] ?? 0));
        $rows = [];

        if ($set->scenario === 'student') {
            $login = $this->hasCredentials($set, $student);
            $rows[] = $this->task($set, 'A1', $student, 'applicant', 'applicant', $login ? 'Sign in normally with this designated account’s operator-only credential handoff; no authentication outcome is inferred.' : 'Matching generated access credentials are missing; do not guess or use another cohort’s account.', $login, route('applicant.login', ['practice_set' => $set->id], absolute: false));
            $rows[] = $this->task($set, 'A2', $student, 'applicant', 'applicant', 'Read this same assigned account’s current application state; no milestone is set by this descriptor.');
            $canInquire = $this->inquiryCounterpart($set, $student);
            $rows[] = $this->task($set, 'A3', $student, 'applicant', 'inquiry', $canInquire ? 'Use the registered own-account inquiry with the assigned permitted office counterpart. Question, reply and participant outcome are not prepared or inferred.' : 'No current permitted assigned Registrar/Guidance inquiry counterpart exists. An accessible inquiry index is not permission to send to an unrelated office actor.', $canInquire);
            $rows[] = $this->task($set, 'A4', $notice, 'applicant', 'applicant', 'This separately designated notice account requires its own normal login. '.$this->noticeReason($notice), $notice !== null && $this->hasCredentials($set, $notice) && $this->hasNotice($notice));
            $assignment = $student !== null && $student['attempt']->isAccepted() ? $this->assignment($set, $student) : null;
            $rows[] = $this->task($set, 'A5', $student, 'applicant', 'applicant', $assignment !== null ? 'Inspect the same primary applicant’s actual assigned session; this is not slot booking. Date, room and permit remain ordinary schedule facts.' : 'Assigned examination session is missing for this primary account; no unrelated reservation cohort or slot-booking substitute is assigned.', $assignment !== null, $assignment !== null ? route('applicant.schedule', ['practice_set' => $set->id], absolute: false) : null);
            $rows[] = $this->task($set, 'A6', $student, 'applicant', 'applicant', $this->paperReason($student).' Authorized staff use this same primary case’s ordinary print handoff. Physical recipient handover remains unverified; no online result or staff print link is substituted.', false);
        }

        if ($set->scenario === 'registrar') {
            $rows[] = $this->task($set, 'B1', $read, 'registrar', 'review', 'Review the assigned permitted intake dossier. No scoring or private counselling information is implied.');
            $rows[] = $this->task($set, 'B2', $read, 'registrar', 'review', 'Inspect this same intake record’s identity, consent and current record-management state.');
            $rows[] = $this->task($set, 'B3', $enrollment, 'registrar', 'enrollment', 'Inspect the separate progressed case’s actual eligibility and missing milestones; this neither enrolls the applicant nor proves completion.');
            $canCorrect = $this->capability($set, 'registrar', CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT);
            $pendingState = $pending !== null && $pending['attempt']->status === AdmissionAttempt::STATUS_PENDING && ($read === null || (int) $read['id'] !== (int) $pending['id']);
            $rows[] = $this->task($set, 'B4', $pending, 'registrar', 'review', ! $canCorrect ? 'Assigned Registrar lacks current correction authority; grants are not repaired.' : (! $pendingState ? 'The separate designated correction case is no longer pending or is missing; do not reset it to force success.' : 'RequestCorrection applies to this separate pending case with current expected version and a supplied valid reason. No correction outcome is manufactured.'), $canCorrect && $pendingState);
            $rows[] = $this->task($set, 'B5', $enrollment, 'registrar', 'enrollment', 'Read authorized Guidance lifecycle/program/follow-up handoff facts only. Registrar is not a participant in the private Guidance/academic thread.');
            $rows[] = $this->task($set, 'B6', $enrollment, 'registrar', 'enrollment', 'Use permitted office-coordination status facts on this same progressed eligibility case. No private thread or broader messaging operation is assigned.');
        }

        if ($set->scenario === 'guidance') {
            $canSchedule = $this->capability($set, 'guidance', CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
            $rows[] = $this->task($set, 'C1', $scheduling, 'guidance', 'scheduling', 'Create a staff-managed draft session with assigned testing date and room, then inspect capacity. Existing session facts are context, not completion; ordinary room/layout/material gates remain.', $canSchedule, $canSchedule ? route('exams.sessions.create', ['practice_set' => $set->id], absolute: false) : null);
            [$omr, $omrReason] = $this->omrPrerequisite($set, $grading);
            $rows[] = $this->task($set, 'C2', $grading, 'guidance', 'omr', $omrReason, $omr);
            $rows[] = $this->task($set, 'C3', $consultation, 'guidance', 'consultation', $this->matchReason($consultation), false);
            $rows[] = $this->task($set, 'C4', $consultation, 'guidance', 'consultation', 'Read actual admission, examination, grading and consultation milestones. No Registrar intake-edit authority is implied.');
            $rows[] = $this->pairedTask($set, 'C5', 'guidance', $paired, $pairedExchange);
            [$canDecide, $decisionReason] = $this->decisionPrerequisite($set, $consultation);
            $rows[] = $this->task($set, 'C6', $consultation, 'guidance', 'consultation', $decisionReason, $canDecide);
            $rows[] = $this->task($set, 'C7', $document, 'guidance', 'print', $this->paperReason($document).' Physical recipient handover remains unverified. Ordinary print confirmations are not independent handover proof; no online release or manufactured delivery is assigned.', false);
            $rows[] = $this->task($set, 'C8', $live, 'guidance', 'monitoring', $this->attendanceReason($set, $live), $this->attendanceWindow($set, $live) && $this->unmarkedAttendance($set, $live));
            $rows[] = $this->pairedTask($set, 'D4', 'academic-leader', $paired, $pairedExchange);
        }

        if ($set->scenario === 'academic-leader') {
            $programUrl = $this->programUrl($set, $academic);
            $rows[] = $this->task($set, 'D1', $academic, 'academic-leader', 'academic', 'Read capacity, commitment and headroom for the current permitted own program only. A title is not institution-wide quota authority.', $programUrl !== null, $programUrl);
            $rows[] = $this->task($set, 'D2', $academic, 'academic-leader', 'academic', 'Locate this accepted candidate in the permitted own-program summary/list. No raw score or private clinical detail is implied.', $programUrl !== null && $academic !== null && $academic['attempt']->isAccepted(), $programUrl);
            $referral = $this->choose($cases->where('stage', 'program'), 'academic', preferred: fn (array $row): bool => $this->hasReferral($row));
            $hasReferral = $referral !== null && $this->hasReferral($referral);
            $rows[] = $this->task($set, 'D3', $referral, 'academic-leader', 'academic', $hasReferral ? 'Review the actual open referral targeted to this permitted program. Review does not clear or resolve it.' : 'No actual open referral targets the permitted own program; a selection or unrelated referral is not a substitute.', $hasReferral, $this->programUrl($set, $referral));
            $rows[] = $this->pairedTask($set, 'D4', 'academic-leader', $paired, $pairedExchange);
            $rows[] = $this->pairedTask($set, 'C5', 'guidance', $paired, $pairedExchange);
        }

        $designated = match ($set->scenario) {
            'student' => $student, 'registrar' => $enrollment ?? $read,
            'guidance' => $consultation, 'academic-leader' => $academic, default => null,
        };

        return ['designated_case_id' => $designated !== null ? (int) $designated['id'] : null, 'task_assignments' => $rows];
    }

    /** @param Collection<int, array<string, mixed>> $cases @return array<string, mixed>|null */
    private function choose(Collection $cases, string $key, ?int $pinned = null, ?callable $preferred = null): ?array
    {
        if ($pinned !== null) {
            return $cases->firstWhere('id', $pinned);
        }
        $allowed = $cases->filter(fn (array $row): bool => ($this->handoff($row, $key)['available'] ?? false) === true);

        return ($preferred !== null ? $allowed->first($preferred) : null) ?? $allowed->first()
            ?? ($preferred !== null ? $cases->first($preferred) : null) ?? $cases->first();
    }

    /** @param array<string, mixed>|null $case @return array<string, mixed> */
    private function handoff(?array $case, string $key): array
    {
        return collect($case['handoffs'] ?? [])->firstWhere('key', $key) ?? [];
    }

    /** @param array<string, mixed>|null $case @return array<string, mixed> */
    private function task(PracticeFixtureSet $set, string $id, ?array $case, string $actor, string $key, string $reason, bool $prerequisite = true, ?string $url = null): array
    {
        $handoff = $this->handoff($case, $key);
        $sameActor = ($handoff['actor'] ?? null) === $actor;
        $available = $set->status === 'active' && ($handoff['available'] ?? false) === true && $sameActor && $prerequisite;
        if (! $sameActor && ($handoff['available'] ?? false) === true) {
            $reason = 'The native handoff belongs to another actor, not the assigned task account. '.$reason;
        }
        $userId = $actor === 'applicant' ? ($case !== null ? $case['attempt']->identity?->applicantAccount?->user_id : null) : ($set->actor_bindings[$actor] ?? null);
        $row = [
            'task_id' => $id, 'case_id' => $case !== null ? (int) $case['id'] : null,
            'actor' => $actor, 'stage' => $case['stage'] ?? null,
            'url' => $available ? ($url ?? $handoff['url'] ?? null) : null, 'available' => $available,
            'reason' => (($handoff['available'] ?? false) === true ? '' : ($handoff['reason'] ?? 'No assigned owned case with the required native access is available.').' ').$reason,
        ];
        if ($userId !== null) {
            $row['account_user_id'] = (int) $userId;
        }
        if (isset($handoff['session_id'])) {
            $row['session_id'] = (int) $handoff['session_id'];
        }
        $assignment = $this->assignment($set, $case);
        if ($assignment !== null && in_array($id, ['A5', 'C2', 'C8'], true)) {
            $row['session_id'] = (int) $assignment->exam_session_id;
        }
        $document = $this->document($case);
        if ($document !== null && in_array($id, ['A6', 'C6', 'C7'], true)) {
            $row['document_id'] = (int) $document->id;
        }

        return $row;
    }

    /** @param array<string, mixed>|null $case @param array<string, mixed> $exchange @return array<string, mixed> */
    private function pairedTask(PracticeFixtureSet $set, string $id, string $actor, ?array $case, array $exchange): array
    {
        $available = $set->status === 'active' && $case !== null && ($exchange['status'] ?? null) === 'available' && isset($exchange['url']);
        $row = [
            'task_id' => $id, 'case_id' => $case !== null ? (int) $case['id'] : null,
            'actor' => $actor, 'stage' => $case['stage'] ?? null,
            'url' => $available ? $exchange['url'] : null, 'available' => $available,
            'reason' => ($exchange['reason'] ?? 'The same approved native C5/D4 case/thread and both eligible parties are missing.').' Separate normal actor sessions are required; no message/reply or completed exchange is inferred.',
        ];
        if (isset($set->actor_bindings[$actor])) {
            $row['account_user_id'] = (int) $set->actor_bindings[$actor];
        }

        return $row;
    }

    private function capability(PracticeFixtureSet $set, string $actor, string $capability): bool
    {
        $user = User::query()->find($set->actor_bindings[$actor] ?? null);

        return $user !== null && $this->authorizer->decideFresh($user, $capability, [
            'institution_id' => (int) $set->institution_id, 'cycle' => $set->academicCycle->code, 'office' => $actor,
        ])->allowed;
    }

    /** @param array<string, mixed>|null $case */
    private function inquiryCounterpart(PracticeFixtureSet $set, ?array $case): bool
    {
        if ($case === null) {
            return false;
        }
        foreach (['registrar', 'guidance'] as $office) {
            $assigned = $set->actor_bindings[$office] ?? null;
            if ($assigned !== null && $this->inquiries->officeStaffFor($case['attempt'], $office)->contains('id', (int) $assigned)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed>|null $case */
    private function document(?array $case): ?ResultDocument
    {
        return $case === null ? null : $case['attempt']->resultDocuments->first(fn (ResultDocument $document): bool => (int) $document->id === (int) $case['attempt']->current_result_document_id
            && $document->status === ResultDocument::STATUS_READY && filled($document->storage_path) && filled($document->checksum)
        );
    }

    /** @param array<string, mixed>|null $case */
    private function assignment(PracticeFixtureSet $set, ?array $case): ?ExamAssignment
    {
        return $case === null ? null : $case['attempt']->examAssignments->sortByDesc('id')->first(static fn (ExamAssignment $assignment): bool => $assignment->isActive() && $assignment->examSession !== null
            && (string) $assignment->examSession->practice_fixture_set_id === (string) $set->id
            && (int) $assignment->examSession->academic_cycle_id === (int) $set->academic_cycle_id
            && $assignment->examSession->status !== ExamSession::STATUS_CANCELLED
        );
    }

    /** @param array<string, mixed>|null $case */
    private function hasCredentials(PracticeFixtureSet $set, ?array $case): bool
    {
        $credentials = $case !== null ? ($set->credentials[(string) $case['attempt']->applicant_identity_id] ?? []) : [];

        return isset($credentials['access_key'], $credentials['birthdate']) && (int) ($credentials['admission_attempt_id'] ?? 0) === (int) ($case['id'] ?? 0);
    }

    /** @param array<string, mixed> $case */
    private function hasNotice(array $case): bool
    {
        return in_array($case['attempt']->status, [AdmissionAttempt::STATUS_NEEDS_CORRECTION, AdmissionAttempt::STATUS_DISMISSED, AdmissionAttempt::STATUS_ARCHIVED], true)
            || ResultRelease::query()->where('admission_attempt_id', $case['id'])->where('is_current', true)->where('mode', 'f2f')->exists();
    }

    /** @param array<string, mixed>|null $case */
    private function noticeReason(?array $case): string
    {
        if ($case === null) {
            return 'The separately designated actual notice case/account is missing; no fake notice or role repair is performed.';
        }

        return $this->hasNotice($case) ? 'Its actual correction/dismissal/archive/current F2F-release fields produce the native portal notice. No media readiness probe or state mutation is performed.' : 'No correction, dismissal, archive or current F2F-release notice exists for this account; portal access is not the notice prerequisite.';
    }

    /** @param array<string, mixed>|null $case */
    private function paperReason(?array $case): string
    {
        $document = $this->document($case);
        if ($document === null) {
            return 'A current ready generated result document is missing; physical printing and recipient handover remain unverified.';
        }
        $printed = ResultDocumentPrintReceipt::query()->where('admission_attempt_id', $case['id'])->where('result_document_id', $document->id)
            ->where('is_current', true)->whereNotNull('confirmed_by_user_id')->whereNotNull('confirmed_at')->exists();

        return 'Current generated result document #'.$document->id.' exists. '.($printed ? 'Attributable current print confirmation exists, but is not independent recipient-handover proof.' : 'Attributable current physical print confirmation is missing; generated PDF is not printed paper.');
    }

    /** @param array<string, mixed>|null $case @return array{bool, string} */
    private function omrPrerequisite(PracticeFixtureSet $set, ?array $case): array
    {
        $assignment = $this->assignment($set, $case);
        $grading = $assignment?->examSession?->gradingSession;
        if ($grading === null) {
            return [false, 'Assigned ordinary OMR grading context is missing; no manual-score substitute is permitted.'];
        }
        $binding = ExamSessionMaterialSnapshot::query()->where('exam_session_id', $assignment->exam_session_id)->where('is_current', true)->first();
        if ($binding === null) {
            return [false, 'Current frozen institutional material binding is missing; no ordinary scoring readiness is claimed.'];
        }
        $sheet = OmrCaptureSheet::query()->where('admission_attempt_id', $case['id'])->whereHas('batch', fn ($query) => $query->where('grading_session_id', $grading->id)
            ->where('template_version_id', $binding->template_version_id)->where('booklet_version_id', $binding->booklet_version_id))
            ->where('asset_state', OmrCaptureSheet::ASSET_LIVE)->whereNotNull('captured_at')->whereNotNull('asset_path')
            ->where('status', OmrCaptureSheet::STATUS_COMMITTED)->whereNotNull('committed_at')->first();
        $output = $sheet !== null && ScoreRecord::query()->where('grading_session_id', $grading->id)->where('admission_attempt_id', $case['id'])
            ->where('source', ScoreRecord::SOURCE_OMR)->where('capture_sheet_id', $sheet->id)->exists();

        return [$output, $output ? 'Actual bound ordinary OMR capture and OMR-sourced output exist for this case. Software provenance does not certify physical qualification, recognition accuracy or completion; no uncertain-mark adjudication is assigned.' : 'Prepared bound sheet/capture and ordinary OMR-sourced result provenance are missing/incomplete. Seeded manual scores are not this task; no scoring readiness or physical qualification is claimed.'];
    }

    /** @param array<string, mixed>|null $case */
    private function matchReason(?array $case): string
    {
        $snapshot = $case !== null ? AdmissionSupportSnapshot::latestForAttempt((int) $case['id']) : null;
        if ($snapshot === null) {
            return 'Actual stored approved current TabPFN match output is missing/unverified. No provider/model execution or heuristic substitute is performed.';
        }
        $backend = $snapshot->payload['inference_backend'] ?? 'in_process_heuristics';

        return in_array($backend, ['in_process_heuristics', 'in_process_fallback'], true)
            ? 'Stored match provenance is '.$backend.', not verified TabPFN output. Approved current TabPFN output remains missing/unverified; no provider is executed or fallback relabelled.'
            : 'Stored inference provenance is '.$backend.'. Approved current TabPFN identity/input/benchmark validity remain unverified; presence alone is not readiness or model-performance evidence. No provider is executed.';
    }

    /** @param array<string, mixed>|null $case @return array{bool, string} */
    private function decisionPrerequisite(PracticeFixtureSet $set, ?array $case): array
    {
        if ($case === null) {
            return [false, 'An assigned accepted result-ready F2F case is missing; no online/Jitsi substitute is assigned.'];
        }
        $attempt = $case['attempt'];
        $ready = $attempt->isAccepted() && AttemptResultReadiness::isReady($attempt) && $this->document($case) !== null;
        $preEnrollment = ! AttemptProgramFacts::isEnrolling($attempt) && ! AttemptProgramFacts::isEnrolled($attempt);
        $authorized = $this->capability($set, 'guidance', CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE);
        $encounter = ConsultationEncounter::query()->where('admission_attempt_id', $case['id'])->where('medium', ConsultationEncounter::MEDIUM_F2F)
            ->whereIn('status', [ConsultationEncounter::STATUS_INVITED, ConsultationEncounter::STATUS_WAITING, ConsultationEncounter::STATUS_IN_PROGRESS])
            ->where('counselor_user_id', $set->actor_bindings['guidance'] ?? null)->exists();
        $printed = $this->document($case) !== null && ResultDocumentPrintReceipt::query()
            ->where('institution_id', $set->institution_id)->where('admission_attempt_id', $case['id'])
            ->where('result_document_id', $attempt->current_result_document_id)->where('is_current', true)
            ->whereNotNull('confirmed_by_user_id')->whereNotNull('confirmed_at')->exists();
        $available = $ready && $preEnrollment && $authorized && $encounter && $printed;

        return [$available, ($ready ? 'Accepted finalized/current-document case exists. ' : 'Accepted finalized/current-document readiness is missing. ')
            .($preEnrollment ? 'Enrollment has not started. ' : 'Enrollment has already started/completed; a new consultation decision is not allowed. ')
            .($authorized ? 'Assigned Guidance has current decision authority. ' : 'Assigned Guidance lacks current decision authority. ')
            .($encounter ? 'Actual assigned active F2F encounter exists. ' : 'Actual assigned active F2F context is missing; no context is manufactured. ')
            .($printed ? 'Attributable current-document print confirmation exists. ' : 'Attributable current-document physical print confirmation is missing. ')
            .'Branch-specific grants and valid decision inputs remain authoritative. No decision/outcome or recipient handover is inferred; no online/Jitsi substitute is assigned.'];
    }

    /** @param array<string, mixed>|null $case */
    private function attendanceWindow(PracticeFixtureSet $set, ?array $case): bool
    {
        $session = $this->assignment($set, $case)?->examSession;

        return $session?->attendanceIsOpen() ?? false;
    }

    /** @param array<string, mixed>|null $case */
    private function unmarkedAttendance(PracticeFixtureSet $set, ?array $case): bool
    {
        $assignment = $this->assignment($set, $case);

        return $assignment !== null && $assignment->attendanceRecord === null && $assignment->submissionRecord === null;
    }

    /** @param array<string, mixed>|null $case */
    private function attendanceReason(PracticeFixtureSet $set, ?array $case): string
    {
        if (! $this->attendanceWindow($set, $case)) {
            return 'Assigned session is missing or outside the native published/in-progress attendance state/time window. Roster reading is not permission to record; current conduct authority/session duty remains independently required.';
        }

        return $this->unmarkedAttendance($set, $case)
            ? 'Assigned session is within the native attendance state/time window and the active examinee assignment has no attendance or submission record. Monitoring separately enforces current conduct authority or active session duty; record only the specified examinee through the ordinary action.'
            : 'The active examinee assignment already has an attendance or submission record; C8 requires an unmarked examinee, so no further attendance recording is assigned.';
    }

    /** @param array<string, mixed> $case */
    private function hasReferral(array $case): bool
    {
        $programId = $this->handoff($case, 'academic')['program_id'] ?? null;

        return $programId !== null && $case['attempt']->isAccepted() && ProgramFollowUp::query()->where('admission_attempt_id', $case['id'])
            ->where('program_id', $programId)->whereIn('status', ProgramFollowUp::OPEN_STATUSES)->exists();
    }

    /** @param array<string, mixed>|null $case */
    private function programUrl(PracticeFixtureSet $set, ?array $case): ?string
    {
        $handoff = $this->handoff($case, 'academic');
        if (! ($handoff['available'] ?? false) || ! isset($handoff['program_id'])) {
            return null;
        }
        $program = Program::query()->whereKey($handoff['program_id'])->where('institution_id', $set->institution_id)->where('is_active', true)->first();

        return $program === null ? null : route('program-head.show', ['program' => $program->code, 'practice_set' => $set->id], absolute: false);
    }
}

<?php

use App\Admission\Actions\EnsureAttemptRequirementChecklist;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Assessment\GradingSession;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Support\Navigation\NavigationBadgeProjector;
use App\Support\Navigation\StaffNavigationBuilder;

function flattenNavSections(array $sections): array
{
    return collect($sections)
        ->flatMap(fn (array $section) => $section['items'])
        ->values()
        ->all();
}

test('staff navigation marks home exact so personnel is not double-active', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $sections = app(StaffNavigationBuilder::class)->for($hr);
    $home = collect($sections)->firstWhere('label', null)['items'][0] ?? null;
    $personnel = collect(flattenNavSections($sections))->firstWhere('href', '/staff/personnel');

    expect($home)->not->toBeNull()
        ->and($home['match'])->toBe('exact')
        ->and($personnel)->not->toBeNull()
        ->and($personnel['match'])->toBe('prefix');
});

test('staff navigation includes staff page for all staff', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $hrHrefs = collect(flattenNavSections(app(StaffNavigationBuilder::class)->for($hr)))->pluck('href');
    $clerkHrefs = collect(flattenNavSections(app(StaffNavigationBuilder::class)->for($clerk)))->pluck('href');
    $guidanceHrefs = collect(flattenNavSections(app(StaffNavigationBuilder::class)->for($guidance)))->pluck('href');

    expect($hrHrefs)->toContain('/staff/personnel')
        ->and($clerkHrefs)->toContain('/staff/personnel')
        ->and($guidanceHrefs)->toContain('/staff/personnel')
        ->and($clerkHrefs)->toContain('/admissions')
        ->and($hrHrefs)->not->toContain('/staff/scan')
        ->and($clerkHrefs)->not->toContain('/staff/scan')
        ->and($guidanceHrefs)->not->toContain('/staff/scan');
});

test('admin office staff navigation groups institutional destinations under governance', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    foreach ([$admin, $hr] as $user) {
        $sections = app(StaffNavigationBuilder::class)->for($user);
        $sectionLabels = collect($sections)->pluck('label');
        $governance = collect($sections)->firstWhere('label', 'Governance');
        $governanceItems = collect($governance['items'] ?? []);
        $allItems = collect(flattenNavSections($sections));

        expect($sectionLabels)->toContain('Governance')
            ->and($governanceItems->firstWhere('href', '/staff/personnel'))->not->toBeNull()
            ->and($governanceItems->firstWhere('href', '/staff/logs'))->not->toBeNull()
            ->and($allItems->pluck('href')->duplicates()->all())->toBe([]);

        if ($user->id === $admin->id) {
            $system = collect($sections)->firstWhere('label', 'More Functions');
            $systemItems = collect($system['items'] ?? []);

            expect($governanceItems->pluck('href'))->toContain('/setup/cycles', '/setup/ai-center', '/communications/homepage')
                ->and(collect(collect($sections)->firstWhere('label', 'Communications & Support')['items'])->firstWhere('href', '/communications/policies'))->not->toBeNull()
                ->and($governanceItems->firstWhere('href', '/staff/personnel')['label'])->toBe('Personnel & Staff')
                ->and($governanceItems->firstWhere('href', '/staff/logs')['label'])->toBe('Global Audit Logs')
                ->and($governanceItems->firstWhere('href', '/communications/homepage')['label'])->toBe('Homepage Studio')
                ->and($systemItems->firstWhere('href', '/setup')['label'])->toBe('Institutional Setup')
                ->and($systemItems->firstWhere('href', '/setup/document-templates')['label'])->toBe('Document Templates');
        }
    }

    foreach (['guidance.counselor@nexiam.test', 'registrar.admin@nexiam.test'] as $email) {
        $user = User::query()->where('email', $email)->firstOrFail();
        $sections = app(StaffNavigationBuilder::class)->for($user);
        $sectionLabels = collect($sections)->pluck('label');
        $system = collect($sections)->firstWhere('label', 'More Functions');
        $systemItems = collect($system['items'] ?? []);

        expect($sectionLabels)->not->toContain('Admin Office')
            ->and($systemItems->firstWhere('href', '/setup/document-templates')['label'] ?? null)->toBe('Document Templates');
    }
});
test('homepage studio appears once in governance navigation section', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $sections = app(StaffNavigationBuilder::class)->for($admin);
    $governance = collect($sections)->firstWhere('label', 'Governance');
    $items = flattenNavSections($sections);

    expect($governance)->not->toBeNull()
        ->and(collect($governance['items'])->pluck('href'))->toContain('/communications/homepage')
        ->and(collect($items)->pluck('href')->duplicates()->all())->toBe([]);
});

test('examination navigation groups schedules, monitoring and scoring', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $regSections = app(StaffNavigationBuilder::class)->for($registrar);
    $guidanceSections = app(StaffNavigationBuilder::class)->for($guidance);
    $application = collect($regSections)->firstWhere('label', 'Application');
    $examination = collect($guidanceSections)->firstWhere('label', 'Examination');
    $applicationHrefs = collect($application['items'])->pluck('href');
    $examinationHrefs = collect($examination['items'])->pluck('href');
    $allHrefs = collect(flattenNavSections($guidanceSections))->pluck('href');

    expect($applicationHrefs)->toContain('/admissions')
        ->and($examinationHrefs)->toContain('/exams/sessions', '/exams/monitoring', '/scoring')
        ->and($allHrefs)->not->toContain('/proctor')
        ->and($allHrefs)->not->toContain('/ui-kit');
});

test('Release of Results navigation uses Release Day vocabulary', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $sections = app(StaffNavigationBuilder::class)->for($guidance);
    $releaseResults = collect($sections)->firstWhere('label', 'Release of Results');
    $items = collect($releaseResults['items'] ?? []);
    $labels = collect($sections)->pluck('label')->filter()->merge($items->pluck('label'));

    expect($items->firstWhere('href', '/consultation')['label'])->toBe('Release Day')
        ->and($items->firstWhere('href', '/consultation/sessions')['label'])->toBe('Release Day Scheduling')
        ->and($labels->contains(fn (string $label): bool => preg_match('/Consultation|Counselling/i', $label) === 1))->toBeFalse();
});

test('historical duty does not grant exam monitoring navigation without test administrator capability', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-NAV-HISTORICAL-DUTY',
        'starts_at' => now()->addDays(4)->setTime(8, 0),
        'effective_end_at' => now()->addDays(4)->setTime(10, 0),
        'status' => ExamSession::STATUS_PUBLISHED,
    ]);

    ProctorDuty::query()->create([
        'exam_session_id' => $session->id,
        'user_id' => $registrar->id,
        'status' => ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'nav-duty-1',
        'assigned_at' => now(),
    ]);

    $sections = app(StaffNavigationBuilder::class)->for($registrar);
    $hrefs = collect(flattenNavSections($sections))->pluck('href');

    expect($hrefs)->not->toContain('/exams/monitoring', '/exams/sessions');
});

test('authorized communications and support destinations are grouped once in staff navigation', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    foreach ([$admin, $guidance, $registrar] as $user) {
        $sections = app(StaffNavigationBuilder::class)->for($user);
        $support = collect($sections)->firstWhere('label', 'Communications & Support');
        $items = collect($support['items'] ?? []);
        $allItems = collect(flattenNavSections($sections));

        expect($support)->not->toBeNull()
            ->and($items->pluck('href')->duplicates()->all())->toBe([])
            ->and($allItems->pluck('href')->duplicates()->all())->toBe([]);

        if ($user->id === $guidance->id || $user->id === $registrar->id) {
            expect($items->pluck('href'))->toContain(
                '/staff/inquiries',
                '/communications',
                '/communications/policies',
                '/knowledge',
            )
                ->and($items->firstWhere('href', '/staff/inquiries')['label'])->toBe('Messages')
                ->and($items->firstWhere('href', '/communications')['label'])->toBe('Announcements')
                ->and($items->firstWhere('href', '/communications/policies')['label'])->toBe('Public policies')
                ->and($items->firstWhere('href', '/knowledge')['label'])->toBe('FAQs');
        } else {
            expect($items->pluck('href'))->toContain('/communications', '/communications/policies')
                ->and($items->pluck('href'))->not->toContain('/staff/inquiries', '/knowledge');
        }
    }

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $hrSections = app(StaffNavigationBuilder::class)->for($hr);
    $hrItems = collect(flattenNavSections($hrSections));

    expect(collect($hrSections)->pluck('label'))->not->toContain('Communications & Support')
        ->and($hrItems->pluck('href'))->not->toContain('/staff/inquiries', '/communications', '/communications/policies', '/knowledge');
});
test('staff navigation uses reconciled assessment consultation and registrar labels', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $guidanceSections = app(StaffNavigationBuilder::class)->for($guidance);
    $guidanceSectionLabels = collect($guidanceSections)->pluck('label');
    $guidanceItemLabels = collect(flattenNavSections($guidanceSections))->pluck('label');

    $releaseSection = collect($guidanceSections)->firstWhere('label', 'Release of Results');
    $releaseItem = collect($releaseSection['items'] ?? [])->firstWhere('href', '/release');
    $schedulingItem = collect($releaseSection['items'] ?? [])->firstWhere('href', '/consultation/sessions');
    $dayOfReleaseItem = collect($releaseSection['items'] ?? [])->firstWhere('href', '/consultation');

    $registrarSections = app(StaffNavigationBuilder::class)->for($registrar);
    $applicationSection = collect($registrarSections)->firstWhere('label', 'Application');
    $applicationsItem = collect($applicationSection['items'] ?? [])->firstWhere('href', '/admissions');
    $clearanceSection = collect($registrarSections)->firstWhere('label', 'Admission Clearance');
    $clearanceItem = collect($clearanceSection['items'] ?? [])->firstWhere('href', '/registrar/enrollment-eligibility');

    expect($applicationsItem)->not->toBeNull()
        ->and($applicationsItem['label'])->toBe('Applications')
        ->and($releaseItem)->not->toBeNull()
        ->and($releaseItem['label'])->toBe('Printing of Results')
        ->and($releaseItem['icon'])->toBe('Printer')
        ->and($guidanceSectionLabels)->toContain('Release of Results')
        ->and($schedulingItem)->not->toBeNull()
        ->and($schedulingItem['label'])->toBe('Release Day Scheduling')
        ->and($dayOfReleaseItem)->not->toBeNull()
        ->and($dayOfReleaseItem['label'])->toBe('Release Day')
        ->and($guidanceSectionLabels)->not->toContain('Counselling')
        ->and($guidanceSectionLabels)->not->toContain('Consultation')
        ->and($guidanceItemLabels)->not->toContain('Counselling')
        ->and($guidanceItemLabels)->not->toContain('Consultation')
        ->and($clearanceItem)->not->toBeNull()
        ->and($clearanceItem['label'])->toBe('Admission Clearance');
});

test('staff navigation badges reflect active exam, monitoring, scoring, and release day session queues', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-BADGE-DRAFT',
        'starts_at' => now()->addDays(2)->setTime(8, 0),
        'effective_end_at' => now()->addDays(2)->setTime(10, 0),
        'status' => ExamSession::STATUS_DRAFT,
    ]);

    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-BADGE-LIVE',
        'starts_at' => now()->subMinutes(15),
        'effective_end_at' => now()->addMinutes(45),
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-BADGE-SCORING',
        'starts_at' => now()->subDays(1)->setTime(8, 0),
        'effective_end_at' => now()->subDays(1)->setTime(10, 0),
        'status' => ExamSession::STATUS_COMPLETED,
    ]);

    $printingExam = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-BADGE-PRINT',
        'starts_at' => now()->subDays(2)->setTime(8, 0),
        'effective_end_at' => now()->subDays(2)->setTime(10, 0),
        'status' => ExamSession::STATUS_COMPLETED,
    ]);
    $printingAttempt = submitSyntheticApplicant('badge.printing@example.test', 'BadgePrinting')['attempt'];
    $printingAttempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED, 'accepted_at' => now()]);
    ExamAssignment::query()->create([
        'exam_session_id' => $printingExam->id,
        'admission_attempt_id' => $printingAttempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => ExamAssignment::query()->where('exam_session_id', $printingExam->id)->value('id'),
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'recorded_by_user_id' => $guidance->id,
        'recorded_at' => now()->subDays(2),
        'idempotency_key' => 'nav-badge-print-att',
    ]);
    ExamSubmissionRecord::query()->create([
        'exam_assignment_id' => ExamAssignment::query()->where('exam_session_id', $printingExam->id)->value('id'),
        'recorded_by_user_id' => $guidance->id,
        'submitted_at' => now()->subDays(2),
        'idempotency_key' => 'nav-badge-print-sub',
    ]);
    GradingSession::query()->create([
        'exam_session_id' => $printingExam->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(2),
        'finalized_at' => now()->subDays(1),
    ]);

    ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'RD-BADGE-DRAFT',
        'scheduled_at' => now()->addDays(3)->setTime(9, 0),
        'status' => ConsultationSession::STATUS_DRAFT,
    ]);

    ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'RD-BADGE-OPEN',
        'scheduled_at' => now()->addHours(2),
        'status' => ConsultationSession::STATUS_PUBLISHED,
    ]);

    $guidanceNav = collect(flattenNavSections(app(StaffNavigationBuilder::class)->for($guidance)))->keyBy('key');
    expect($guidanceNav->get('exam_scheduling')['badge_key'] ?? null)->toBe('exam_scheduling')
        ->and($guidanceNav->get('exam_monitoring')['badge_key'] ?? null)->toBe('exam_monitoring')
        ->and($guidanceNav->get('scoring')['badge_key'] ?? null)->toBe('scoring')
        ->and($guidanceNav->get('result_documents')['badge_key'] ?? null)->toBe('result_documents')
        ->and($guidanceNav->get('consultation_scheduling')['badge_key'] ?? null)->toBe('consultation_scheduling')
        ->and($guidanceNav->get('consultation_release')['badge_key'] ?? null)->toBe('consultation_release');

    $guidanceBadges = app(NavigationBadgeProjector::class)->forUser($guidance)['items'];
    expect($guidanceBadges['exam_scheduling']['count'] ?? 0)->toBeGreaterThanOrEqual(1)
        ->and($guidanceBadges['exam_monitoring']['count'] ?? 0)->toBeGreaterThanOrEqual(1)
        ->and($guidanceBadges['exam_monitoring']['pulse'] ?? false)->toBeTrue()
        ->and($guidanceBadges['scoring']['count'] ?? 0)->toBeGreaterThanOrEqual(1)
        ->and($guidanceBadges['result_documents']['count'] ?? 0)->toBeGreaterThanOrEqual(1)
        ->and($guidanceBadges['consultation_scheduling']['count'] ?? 0)->toBe(1)
        ->and($guidanceBadges['consultation_release']['count'] ?? 0)->toBe(1);

    $registrarBadges = app(NavigationBadgeProjector::class)->forUser($registrar)['items'];
    expect($registrarBadges['exam_scheduling'] ?? null)->toBeNull()
        ->and($registrarBadges['exam_monitoring'] ?? null)->toBeNull()
        ->and($registrarBadges['scoring'] ?? null)->toBeNull()
        ->and($registrarBadges['result_documents'] ?? null)->toBeNull();
});

test('staff navigation badges reflect pending applications and ready admission clearance queues', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();

    $registrarNav = collect(flattenNavSections(app(StaffNavigationBuilder::class)->for($registrar)))->keyBy('key');
    expect($registrarNav->get('admissions')['badge_key'] ?? null)->toBe('admissions')
        ->and($registrarNav->get('admission_clearance')['badge_key'] ?? null)->toBe('admission_clearance');

    // 1. Submit a synthetic pending applicant
    $pendingAttempt = submitSyntheticApplicant('badge.pending@example.test', 'BadgePending')['attempt'];

    $registrarBadges = app(NavigationBadgeProjector::class)->forUser($registrar)['items'];
    expect($registrarBadges['admissions']['count'])->toBe(1)
        ->and($registrarBadges['admissions']['unit'])->toBe('pending_applications')
        ->and($registrarBadges['admissions']['variant'])->toBe('info')
        ->and($registrarBadges['admissions']['accessible_label'])->toBe('1 pending application')
        ->and($registrarBadges['admission_clearance'])->toBeNull();

    // Guidance counselor should not see registrar operational badges
    $guidanceBadges = app(NavigationBadgeProjector::class)->forUser($guidance)['items'];
    expect($guidanceBadges['admissions'] ?? null)->toBeNull()
        ->and($guidanceBadges['admission_clearance'] ?? null)->toBeNull();

    // 2. Prepare an attempt that is ready for admission clearance
    $clearanceAttempt = submitSyntheticApplicant('badge.clearance@example.test', 'BadgeClearance')['attempt'];
    $clearanceAttempt->update([
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
        'accepted_at' => now(),
    ]);

    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $clearanceAttempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLING,
        'provisional_program_id' => $program->id,
        'stamped_by_user_id' => $registrar->id,
        'reason' => 'Provisional program stamped for clearance test',
        'idempotency_key' => 'stamp-'.$clearanceAttempt->id,
        'version' => 1,
        'created_at' => now(),
    ]);
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-CLEARANCE-'.uniqid(),
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(2),
        'effective_end_at' => now()->subDays(2)->addHours(2),
        'closed_at' => now()->subDays(2),
    ]);

    ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $clearanceAttempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);

    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(2),
        'finalized_at' => now()->subDays(1),
    ]);

    ResultDocument::query()->create([
        'admission_attempt_id' => $clearanceAttempt->id,
        'template_version' => '1.0',
        'checksum' => hash('sha256', 'mock-result-'.$clearanceAttempt->id),
        'status' => ResultDocument::STATUS_READY,
    ]);

    ConsultationDecision::query()->create([
        'admission_attempt_id' => $clearanceAttempt->id,
        'counselor_user_id' => $guidance->id,
        'decision_type' => 'approve',
        'notes' => 'Approved for admission clearance.',
        'version' => 1,
        'idempotency_key' => 'decision-clearance-'.$clearanceAttempt->id,
    ]);

    $set = app(EnsureAttemptRequirementChecklist::class)->handle($clearanceAttempt, $registrar);
    expect($set)->not->toBeNull();
    foreach ($set->items as $item) {
        AdmissionAttemptDocumentVerification::query()->create([
            'admission_attempt_id' => $clearanceAttempt->id,
            'requirement_item_id' => $item->id,
            'status' => 'verified',
            'verified_by_user_id' => $registrar->id,
            'verified_at' => now(),
        ]);
    }

    $registrarBadges = app(NavigationBadgeProjector::class)->forUser($registrar)['items'];
    expect($registrarBadges['admissions']['count'])->toBe(1)
        ->and($registrarBadges['admission_clearance']['count'])->toBe(1)
        ->and($registrarBadges['admission_clearance']['unit'])->toBe('ready_for_clearance')
        ->and($registrarBadges['admission_clearance']['variant'])->toBe('info')
        ->and($registrarBadges['admission_clearance']['accessible_label'])->toBe('1 applicant ready for clearance');

    // 3. Confirm clearance -> ready badge drops back to null
    AdmissionClearanceReceipt::query()->create([
        'admission_attempt_id' => $clearanceAttempt->id,
        'program_id' => $program->id,
        'receipt_number' => 'REC-'.strtoupper(uniqid()),
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now(),
        'status' => 'confirmed',
        'evidence_vector' => ['is_ready' => true],
        'idempotency_key' => 'receipt-clearance-'.$clearanceAttempt->id,
    ]);

    $registrarBadgesAfterClearance = app(NavigationBadgeProjector::class)->forUser($registrar)['items'];
    expect($registrarBadgesAfterClearance['admission_clearance'])->toBeNull()
        ->and($registrarBadgesAfterClearance['admissions']['count'])->toBe(1);
});

test('dean and program head navigation places My Department and My Program first after dashboard', function (): void {
    seedE2Foundation();
    $dean = User::query()->where('email', 'cas.dean@nexiam.test')->firstOrFail();
    $programHead = User::query()->where('email', 'bsit.head@nexiam.test')->firstOrFail();

    $deanSections = app(StaffNavigationBuilder::class)->for($dean);
    $phSections = app(StaffNavigationBuilder::class)->for($programHead);

    expect($deanSections[0]['label'])->toBeNull()
        ->and($deanSections[1]['label'])->toBe('My Department')
        ->and($deanSections[1]['items'][0]['label'])->toBe('My Programs')
        ->and($deanSections[1]['items'][0]['href'])->toBe('/staff/programs')
        ->and(collect($deanSections)->pluck('label'))->not->toContain('Program Head');

    expect($phSections[0]['label'])->toBeNull()
        ->and($phSections[1]['label'])->toBe('My Program')
        ->and($phSections[1]['items'][0]['label'])->toBe('BSIT')
        ->and($phSections[1]['items'][0]['href'])->toBe('/staff/programs/BSIT')
        ->and(collect($phSections)->pluck('label'))->not->toContain('Program Head')
        ->and(collect(flattenNavSections($phSections))->pluck('label'))->not->toContain('My Programs');

    $this->actingAs($programHead)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $programHead->authenticationState->session_version,
    ])->get('/staff/programs')
        ->assertRedirect('/staff/programs/BSIT');

    $this->actingAs($dean)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $dean->authenticationState->session_version,
    ])->get('/staff/programs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ProgramHead/Index')
            ->where('is_dean', true)
            ->where('department.code', 'CAS')
        );
});

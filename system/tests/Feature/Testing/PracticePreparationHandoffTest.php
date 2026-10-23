<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\ResultSheet\Actions\ConfirmPhysicalPrintAction;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use App\Testing\Queries\InstitutionalOmrBookletQuery;
use App\Testing\Queries\PracticePreparationQuery;
use App\Testing\Seeders\Stages\SeedGradingStage;
use App\Testing\Seeders\Stages\SeedProgramStage;
use App\Testing\Seeders\Stages\SeedReservationStage;
use App\Testing\Seeders\Stages\SeedReviewStage;
use Database\Seeders\ApplicantOfficeInquiryDemoSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
    $this->operator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->makeHandoffSet = function (string $scenario, string $purpose = 'demo'): PracticeFixtureSet {
        return PracticeFixtureSet::query()->create([
            'label' => 'Assigned workflow rehearsal', 'institution_id' => Institution::current()->id,
            'academic_cycle_id' => AcademicCycle::active()->id, 'scenario' => $scenario, 'purpose' => $purpose,
            'created_by_user_id' => $this->operator->id,
            'actor_bindings' => ['guidance' => (int) $this->operator->id, 'registrar' => (int) $this->registrar->id,
                'academic-leader' => (int) User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail()->id],
            'material_snapshot' => [], 'credentials' => [],
        ]);
    };
});

test('scoring cases hand off to their actual grading session and applicant instead of the Registrar dossier', function (): void {
    seedE2ApprovedOmrMaterialForPipelineTests();
    $set = ($this->makeHandoffSet)('scoring-results');
    $stage = app(SeedGradingStage::class)->forSet($set)->seed();
    $preparation = app(PracticePreparationQuery::class)->get($this->operator, ['purpose' => 'demo', 'scenario' => 'scoring-results', 'set_id' => $set->id]);
    $case = collect($preparation['selected_set']['cases'])->firstWhere('id', $stage['attempts'][0]->id);
    expect($case['actor'])->toBe('guidance')->and($case['available'])->toBeTrue()
        ->and($case['url'])->toBe(route('grading.sessions.attempts.scores', ['gradingSession' => $stage['grading']->id, 'attempt' => $case['id'], 'practice_set' => $set->id], absolute: false))
        ->and(collect($case['handoffs'])->firstWhere('key', 'grading')['grading_session_id'])->toBe($stage['grading']->id);
});

test('consultation and enrollment starting cases have role-specific actual case handoffs without private Registrar substitution', function (): void {
    seedE2ApprovedOmrMaterialForPipelineTests();
    foreach (['consultation-referral' => 'consultation', 'enrollment-verification' => 'enrollment'] as $scenario => $key) {
        $set = ($this->makeHandoffSet)($scenario);
        $stage = app(SeedProgramStage::class)->forSet($set)->seed();
        $preparation = app(PracticePreparationQuery::class)->get($this->operator, ['purpose' => 'demo', 'scenario' => $scenario, 'set_id' => $set->id]);
        $case = collect($preparation['selected_set']['cases'])->firstWhere('id', $stage['attempts'][0]->id);
        $handoff = collect($case['handoffs'])->firstWhere('key', $key);
        expect($case['url'])->toBe($handoff['url'])->and($case['actor'])->toBe($key === 'consultation' ? 'guidance' : 'registrar')
            ->and($case['url'])->not->toContain('/admissions/');
        if ($key === 'consultation') {
            expect($case['url'])->toContain('/consultation/applicants/'.$case['id']);
        } else {
            expect($case['url'])->toContain('/registrar/enrollment-eligibility/'.$case['id']);
        }
    }
});

test('physical milestone checks exclude unrelated pending review cases after actual relevant document evidence exists', function (): void {
    seedE2ApprovedOmrMaterialForPipelineTests();
    $set = ($this->makeHandoffSet)('consultation-referral');
    $review = app(SeedReviewStage::class)->forSet($set)->seed();
    $program = app(SeedProgramStage::class)->forSet($set)->seed();
    foreach ($program['attempts'] as $attempt) {
        app(ConfirmPhysicalPrintAction::class)->confirmPrint(
            ResultDocument::query()->findOrFail($attempt->fresh()->current_result_document_id),
            $this->operator,
            (string) Str::uuid(),
        );
    }
    $preparation = app(PracticePreparationQuery::class)->get($this->operator, ['purpose' => 'demo', 'scenario' => 'consultation-referral', 'set_id' => $set->id]);
    expect(collect($preparation['prerequisites'])->firstWhere('key', 'case-physical_print')['status'])->toBe('available')
        ->and($review['attempts'][0]->fresh()->status)->toBe(AdmissionAttempt::STATUS_PENDING)
        ->and($review['attempts'][0]->fresh()->current_result_document_id)->toBeNull();
    foreach ($program['attempts'] as $attempt) {
        app(ConfirmPhysicalPrintAction::class)->correctPrintConfirmation(
            ResultDocumentPrintReceipt::query()->where('result_document_id', $attempt->fresh()->current_result_document_id)->where('is_current', true)->firstOrFail(),
            $this->operator,
            'Confirmation was entered before the paper was printed.',
            (string) Str::uuid(),
        );
    }
    $corrected = app(PracticePreparationQuery::class)->get($this->operator, ['purpose' => 'demo', 'scenario' => 'consultation-referral', 'set_id' => $set->id]);
    expect(collect($corrected['prerequisites'])->firstWhere('key', 'case-physical_print')['status'])->not->toBe('available');
    foreach ($corrected['selected_set']['documents'] as $document) {
        expect($document['physical_status'])->toContain('unverified');
    }
});

test('research student task map designates one primary result-capable account and a separate real notice account', function (): void {
    seedE2ApprovedOmrMaterialForPipelineTests();
    (new ApplicantOfficeInquiryDemoSeeder)->run();
    $registeredGuidance = User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::GUIDANCE_EMAIL)->firstOrFail();
    $registeredGuidance->givePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, CapabilityCatalog::GUIDANCE_SCORING_MANUAL, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE);
    $input = ['purpose' => 'research', 'scenario' => 'student', 'action' => 'prepare'];
    $preview = $this->actingAs($this->operator)->postJson('/demo/preparation/preview', $input)->assertOk()->assertJsonPath('can_confirm', true);
    $confirmed = $this->postJson($preview->json('operation.confirm_url'), [])->assertOk();
    $selected = $confirmed->json('preparation.selected_set');
    $primary = $selected['designated_case_id'];
    $assignments = collect($selected['assignments']);
    $primaryTasks = $assignments->filter(static fn (array $assignment): bool => $assignment['case_id'] === $primary)->flatMap(static fn (array $assignment): array => $assignment['task_ids'])->all();
    expect($primaryTasks)->toContain('A1', 'A2', 'A3', 'A5', 'A6');
    $notice = $assignments->first(static fn (array $assignment): bool => in_array('A4', $assignment['task_ids'], true));
    expect($notice['case_id'])->not->toBe($primary)
        ->and(AdmissionAttempt::query()->findOrFail($notice['case_id'])->status)->toBe(AdmissionAttempt::STATUS_NEEDS_CORRECTION)
        ->and(AdmissionAttempt::query()->findOrFail($primary)->current_result_document_id)->not->toBeNull();
    $paperTask = $assignments->first(static fn (array $assignment): bool => in_array('A6', $assignment['task_ids'], true));
    $primaryAttempt = AdmissionAttempt::query()->findOrFail($primary);
    $scheduleTask = $assignments->first(static fn (array $assignment): bool => in_array('A5', $assignment['task_ids'], true));
    expect($scheduleTask['url'])->toBe(route('applicant.schedule', ['practice_set' => $selected['id']], absolute: false))
        ->and($scheduleTask['session_id'])->toBe($primaryAttempt->examAssignments()->active()->latest('id')->firstOrFail()->exam_session_id)
        ->and($paperTask['available'])->toBeFalse()->and($paperTask['url'])->toBeNull()
        ->and($paperTask['document_id'])->toBe($primaryAttempt->current_result_document_id);
});

test('an explicitly selected institutional material that changes after preview returns conflict without case writes', function (): void {
    seedE2ApprovedOmrMaterialForPipelineTests();
    $booklet = app(InstitutionalOmrBookletQuery::class)->eligible()->firstOrFail();
    $count = AdmissionAttempt::query()->count();
    $preview = $this->actingAs($this->operator)->postJson('/demo/preparation/preview', [
        'purpose' => 'demo', 'scenario' => 'exam-scheduling-attendance', 'action' => 'prepare', 'booklet_version_id' => $booklet->id,
    ])->assertOk();
    $booklet->update(['status' => 'retired']);
    $this->postJson($preview->json('operation.confirm_url'), [])->assertConflict();
    expect(AdmissionAttempt::query()->count())->toBe($count)->and(PracticeFixtureSet::query()->count())->toBe(0);
});

test('fresh starting sessions ignore retained retired and other-set room occupancy without rewriting those histories', function (): void {
    seedE2ApprovedOmrMaterialForPipelineTests();
    $room = Room::query()->where('institution_id', Institution::current()->id)->active()->examRooms()->firstOrFail();
    $old = ($this->makeHandoffSet)('exam-scheduling-attendance');
    $old->update(['material_snapshot' => ['room_candidate_ids' => [$room->id]]]);
    $oldStage = app(SeedReservationStage::class)->forSet($old)->seed();
    $oldSession = $oldStage['session']->getAttributes();
    $old->update(['status' => 'retired', 'retired_at' => now()]);
    $fresh = ($this->makeHandoffSet)('exam-scheduling-attendance');
    $fresh->update(['material_snapshot' => ['room_candidate_ids' => [$room->id]]]);
    $newStage = app(SeedReservationStage::class)->forSet($fresh)->seed();
    expect($newStage['session']->practice_fixture_set_id)->toBe($fresh->id)
        ->and($newStage['session']->room_id)->toBe($room->id)
        ->and($oldStage['session']->fresh()->getAttributes())->toBe($oldSession)
        ->and($old->fresh()->status)->toBe('retired');
    $another = ($this->makeHandoffSet)('exam-scheduling-attendance');
    $another->update(['material_snapshot' => ['room_candidate_ids' => [$room->id]]]);
    $third = app(SeedReservationStage::class)->forSet($another)->seed();
    expect($third['session']->practice_fixture_set_id)->toBe($another->id)
        ->and($newStage['session']->fresh()->practice_fixture_set_id)->toBe($fresh->id);
});

test('preparing ready practice results never assigns into or changes a genuine automatic consultation session', function (): void {
    seedE2ApprovedOmrMaterialForPipelineTests();
    $session = ConsultationSession::factory()->autoAssignEnabled()->create();
    $before = $session->fresh()->getAttributes();
    $set = ($this->makeHandoffSet)('consultation-referral');
    $stage = app(SeedProgramStage::class)->forSet($set)->seed();
    foreach ($stage['attempts'] as $attempt) {
        expect($attempt->fresh()->current_result_document_id)->not->toBeNull();
    }
    expect($session->fresh()->getAttributes())->toBe($before)
        ->and($session->assignments()->count())->toBe(0)
        ->and(ConsultationAssignment::query()->whereIn('admission_attempt_id', array_column($stage['attempts'], 'id'))->count())->toBe(0);
});

test('unresolved OMR pages inherit their owned batch boundary before any applicant has been matched', function (): void {
    seedE2ApprovedOmrMaterialForPipelineTests();
    $set = ($this->makeHandoffSet)('scoring-results');
    $stage = app(SeedGradingStage::class)->forSet($set)->seed();
    $workspace = app(PracticeWorkspace::class);
    [$sheet, $page] = $workspace->run($set, function () use ($stage): array {
        $batch = app(CreateOmrCaptureBatch::class)->handle(
            $stage['grading'], $this->operator, (string) Str::uuid(),
        );
        $sheet = OmrCaptureSheet::query()->create([
            'batch_id' => $batch->id, 'status' => 'received', 'expected_page_count' => 1,
            'capture_mode' => 'manual', 'client_capture_id' => (string) Str::uuid(),
            'version' => 0, 'asset_state' => 'pending_write',
        ]);
        $page = OmrCaptureSheetPage::query()->create([
            'sheet_id' => $sheet->id, 'page_no' => 1, 'status' => 'queued',
        ]);

        return [$sheet, $page];
    });
    expect($sheet->fresh()->admission_attempt_id)->toBeNull();
    $foreign = ($this->makeHandoffSet)('scoring-results');
    expect(fn () => $workspace->run($foreign, fn () => $page->update(['status' => 'failed'])))->toThrow(RuntimeException::class);
    expect($page->fresh()->status)->toBe('queued');
    $set->update(['status' => 'retired', 'retired_at' => now()]);
    expect(fn () => $page->update(['status' => 'failed']))->toThrow(RuntimeException::class);
    expect($page->fresh()->status)->toBe('queued')->and($sheet->fresh()->admission_attempt_id)->toBeNull();
});

<?php

declare(strict_types=1);

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Applicant\Support\ApplicantResultAccess;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Printing\PrintRenderer;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\QueueConsultationPortalCopy;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\UnreleaseResult;
use App\Release\Jobs\GenerateConsultationResultPdf;
use App\Release\Jobs\IssueConsultationPortalCopy;
use App\Release\Support\ResultDocumentPdf;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('sensitive');
});

/** @return array{0: AdmissionAttempt, 1: ResultDocument, 2: User, 3: array<string, mixed>} */
function portalCopyLifecycleFixture(string $suffix): array
{
    seedE2Foundation();
    $submitted = submitSyntheticApplicant('portal-copy-'.$suffix.'@example.test', 'PortalCopy');
    $attempt = $submitted['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    test()->actingAs($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();
    $attempt->refresh();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-PORTAL-'.$suffix,
        'starts_at' => now()->subDays(2),
        'effective_end_at' => now()->subDays(2)->addHours(2),
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'version' => 1,
    ]);
    $grading = GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(2),
        'finalized_at' => now()->subDay(),
        'version' => 1,
    ]);
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'idempotency_key' => 'portal-copy-attendance-'.$suffix,
        'recorded_at' => now(),
    ]);
    ExamSubmissionRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'idempotency_key' => 'portal-copy-submission-'.$suffix,
        'submitted_at' => now(),
    ]);
    foreach (AptitudeArea::query()->where('is_active', true)->get() as $area) {
        ScoreRecord::query()->create([
            'grading_session_id' => $grading->id,
            'admission_attempt_id' => $attempt->id,
            'aptitude_area_id' => $area->id,
            'score' => min(20, (int) $area->max_items),
            'converted_score' => '85.00',
            'source' => ScoreRecord::SOURCE_MANUAL,
            'recorded_by_user_id' => $guidance->id,
            'idempotency_key' => 'portal-copy-score-'.$suffix.'-'.$area->id,
        ]);
    }
    $examSession->update(['status' => ExamSession::STATUS_COMPLETED]);

    $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $guidance->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'portal-copy-print-'.$suffix,
        'mode' => 'f2f',
    ]);
    app(MarkResultSheetsPrinted::class)->handle(
        $guidance,
        [(int) $document->id],
        true,
        idempotencyKey: 'portal-copy-print-receipt-'.$suffix,
    );

    return [$attempt->fresh(), $document->fresh(), $guidance, $submitted];
}

/** @return array{0: ConsultationDecision, 1: ConsultationEncounter} */
function approvePortalCopyFixture(array $fixture, string $suffix): array
{
    [$attempt, , $guidance] = $fixture;
    $decision = app(RecordConsultationDecision::class)->handle(
        $attempt,
        $guidance,
        'approve',
        null,
        'portal-copy-decision-'.$suffix,
    );
    $encounter = ConsultationEncounter::query()
        ->where('idempotency_key', 'consultation-decision-encounter-'.$decision->idempotency_key)
        ->firstOrFail();

    return [$decision, $encounter];
}

/** @return array{0: ConsultationDecision, 1: ConsultationEncounter} */
function referPortalCopyFixture(array $fixture, string $suffix): array
{
    [$attempt, , $guidance] = $fixture;
    $targetProgram = Program::query()
        ->where('institution_id', $attempt->institution_id)
        ->where('is_active', true)
        ->firstOrFail();
    $decision = app(RecordConsultationDecision::class)->handle(
        $attempt,
        $guidance,
        'refer',
        null,
        'portal-copy-refer-'.$suffix,
        targetProgramCode: $targetProgram->code,
        referralReason: 'Program review of the frozen result.',
    );
    $encounter = ConsultationEncounter::query()
        ->where('idempotency_key', 'consultation-decision-encounter-'.$decision->idempotency_key)
        ->firstOrFail();

    return [$decision, $encounter];
}

function setVerifiedPortalCopyPdf(ResultDocument $document, string $bytes = "%PDF-1.7\nverified fixture\n"): void
{
    $path = 'results/'.$document->admission_attempt_id.'/pdf/'.$document->id.'-fixture.pdf';
    app(SensitiveAssetStore::class)->store($bytes, $path);
    $manifest = $document->artifact_manifest ?? [];
    $manifest['pdf'] = [
        'status' => 'ready',
        'path' => $path,
        'size' => strlen($bytes),
        'sha256' => hash('sha256', $bytes),
    ];
    $document->forceFill(['artifact_manifest' => $manifest])->save();
}

test('confirmed F2F decision issues a verified current PDF and retries create one receipt and notice pair', function (): void {
    $fixture = portalCopyLifecycleFixture('ready');
    [$attempt, $document, $guidance, $submitted] = $fixture;
    app(AuthenticateApplicantWithAccessKey::class)->handle($submitted['access_token'], '2001-01-01');
    Queue::fake();
    [$decision, $encounter] = approvePortalCopyFixture($fixture, 'ready');
    setVerifiedPortalCopyPdf($document);
    app(QueueConsultationPortalCopy::class)->handle($attempt, $decision, $encounter, $document, $guidance);
    $job = new IssueConsultationPortalCopy($attempt->id, $document->id, $decision->id, $encounter->id, $guidance->id);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    $release = ResultRelease::query()
        ->where('consultation_decision_id', $decision->id)
        ->where('channel', 'portal')
        ->firstOrFail();
    $deliveries = NotificationDelivery::query()->where('event_type', 'result.digital_issued.v1')->get();
    expect($release->is_current)->toBeTrue()
        ->and($release->mode)->toBe('online')
        ->and($release->physical_handover_at)->toBeNull()
        ->and($release->print_receipt_id)->toBeNull()
        ->and((int) $release->consultation_encounter_id)->toBe((int) $encounter->id)
        ->and(ResultRelease::query()->where('admission_attempt_id', $attempt->id)->whereNotNull('result_printed_at')->count())->toBe(1)
        ->and(ResultRelease::query()->where('consultation_decision_id', $decision->id)->where('channel', 'portal')->count())->toBe(1)
        ->and($deliveries)->toHaveCount(2);
    foreach ($deliveries as $delivery) {
        expect($delivery->payload['action_url'])->toBe(route('applicant.results.show'));
    }
});

test('a completed F2F referral issues the current portal copy once without recording physical handover', function (): void {
    $fixture = portalCopyLifecycleFixture('refer-ready');
    [$attempt, $document, $guidance] = $fixture;
    Queue::fake();
    [$decision, $encounter] = referPortalCopyFixture($fixture, 'ready');
    expect($decision->decision_type)->toBe('refer')
        ->and((int) $decision->counselor_user_id)->toBe((int) $guidance->id)
        ->and($encounter->medium)->toBe(ConsultationEncounter::MEDIUM_F2F)
        ->and($encounter->status)->toBe(ConsultationEncounter::STATUS_OUTCOME_RECORDED)
        ->and($encounter->closed_at)->not->toBeNull()
        ->and((int) $encounter->result_document_id)->toBe((int) $document->id)
        ->and((int) $encounter->result_document_version)->toBe((int) $document->version);

    setVerifiedPortalCopyPdf($document);
    expect(app(ApplicantResultAccess::class)->forAttempt($attempt->fresh())['available'])->toBeFalse();
    $physicalReceiptCount = ResultDocumentPrintReceipt::query()
        ->where('admission_attempt_id', $attempt->id)
        ->count();
    app(QueueConsultationPortalCopy::class)->handle($attempt, $decision, $encounter, $document, $guidance);
    $job = new IssueConsultationPortalCopy($attempt->id, $document->id, $decision->id, $encounter->id, $guidance->id);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    $release = ResultRelease::query()
        ->where('consultation_decision_id', $decision->id)
        ->where('channel', 'portal')
        ->firstOrFail();
    expect($release->is_current)->toBeTrue()
        ->and($release->print_receipt_id)->toBeNull()
        ->and($release->physical_handover_at)->toBeNull()
        ->and($release->consultation_encounter_id)->toBe($encounter->id)
        ->and(ResultRelease::query()->where('consultation_decision_id', $decision->id)->where('channel', 'portal')->count())->toBe(1)
        ->and(ResultDocumentPrintReceipt::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($physicalReceiptCount);
    $access = app(ApplicantResultAccess::class)->forAttempt($attempt->fresh());
    expect($access['available'])->toBeTrue()
        ->and($access['document']?->id)->toBe($document->id)
        ->and(ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->firstOrFail()->status)
        ->toBe(ProgramFollowUp::STATUS_OPEN);
});

test('pending PDF generation renders the frozen prepared template and data then queues the same issuance path', function (): void {
    $fixture = portalCopyLifecycleFixture('pending');
    [$attempt, $document, $guidance, $submitted] = $fixture;
    app(AuthenticateApplicantWithAccessKey::class)->handle($submitted['access_token'], '2001-01-01');
    Queue::fake();
    $preparedSnapshot = $document->snapshot_payload_json;
    $preparedSource = base64_decode($preparedSnapshot['_template_source']['bytes_base64'], true);
    expect(in_array($preparedSnapshot['_template']['mode'], [ResultSheetTemplate::MODE_HTML, ResultSheetTemplate::MODE_DOCX], true))->toBeTrue()
        ->and(is_string($preparedSource))->toBeTrue()
        ->and(hash('sha256', $preparedSource))->toBe($preparedSnapshot['_template_source']['sha256']);
    [$decision, $encounter] = referPortalCopyFixture($fixture, 'pending');

    $source = '<html><body>Frozen result {{applicant_reference}}</body></html>';
    $snapshot = $document->snapshot_payload_json;
    $snapshot['reference'] = 'FROZEN-REF-77';
    $snapshot['_template'] = [
        'mode' => ResultSheetTemplate::MODE_HTML,
        'source_kind' => ResultSheetTemplate::SOURCE_LEGACY_HTML,
        'paper_size' => ResultSheetTemplate::PAPER_A4,
        'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
        'watermark_text' => null,
        'builder_json' => null,
    ];
    $snapshot['_template_source'] = [
        'bytes_base64' => base64_encode($source),
        'sha256' => hash('sha256', $source),
    ];
    $document->forceFill(['snapshot_payload_json' => $snapshot])->save();
    $activeTemplate = ResultSheetTemplate::active();
    $activeTemplate?->forceFill(['content' => '<html><body>Live changed template</body></html>'])->save();

    $renderedHtml = null;
    app()->instance(PrintRenderer::class, new class($renderedHtml) implements PrintRenderer
    {
        private mixed $capture;

        public function __construct(mixed &$capture)
        {
            $this->capture = &$capture;
        }

        public function render(string $html, array $options = []): string
        {
            $this->capture = $html;

            return "%PDF-1.7\nfrozen render fixture\n";
        }
    });

    app(QueueConsultationPortalCopy::class)->handle($attempt, $decision, $encounter, $document, $guidance);
    $generation = new GenerateConsultationResultPdf($attempt->id, $document->id, $decision->id, $encounter->id, $guidance->id);
    app()->call([$generation, 'handle']);

    expect($renderedHtml)->toContain('Frozen result')
        ->and($renderedHtml)->toContain('FROZEN-REF-77')
        ->and($renderedHtml)->not->toContain('Live changed template')
        ->and(app(ResultDocumentPdf::class)->isReady($document->fresh()))->toBeTrue();
    Queue::assertPushed(IssueConsultationPortalCopy::class);
    $issue = new IssueConsultationPortalCopy($attempt->id, $document->id, $decision->id, $encounter->id, $guidance->id);
    app()->call([$issue, 'handle']);
    expect(ResultRelease::query()->where('consultation_decision_id', $decision->id)->where('channel', 'portal')->count())->toBe(1);
});

test('a later defer prevents a queued portal issuance', function (): void {
    $fixture = portalCopyLifecycleFixture('stale');
    [$attempt, $document, $guidance] = $fixture;
    Queue::fake();
    [$decision, $encounter] = referPortalCopyFixture($fixture, 'stale');
    setVerifiedPortalCopyPdf($document);
    $job = new IssueConsultationPortalCopy($attempt->id, $document->id, $decision->id, $encounter->id, $guidance->id);

    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $guidance->id,
        'decision_type' => 'defer',
        'notes' => 'Review deferred.',
        'version' => $decision->version + 1,
        'idempotency_key' => 'portal-copy-later-defer',
    ]);
    app()->call([$job, 'handle']);

    expect(ResultRelease::query()->where('consultation_decision_id', $decision->id)->where('channel', 'portal')->exists())->toBeFalse();
});

test('automatic portal copy queue denies actors outside the attempt institution scope', function (): void {
    $fixture = portalCopyLifecycleFixture('denied');
    [$attempt, $document, $guidance] = $fixture;
    Queue::fake();
    [$decision, $encounter] = referPortalCopyFixture($fixture, 'denied');
    Queue::fake();
    $outsider = User::factory()->create();
    app(QueueConsultationPortalCopy::class)->handle($attempt, $decision, $encounter, $document, $outsider);

    Queue::assertNothingPushed();
});

test('a retracted automatic portal issuance is not resurrected by a duplicate job', function (): void {
    $fixture = portalCopyLifecycleFixture('retracted');
    [$attempt, $document, $guidance, $submitted] = $fixture;
    app(AuthenticateApplicantWithAccessKey::class)->handle($submitted['access_token'], '2001-01-01');
    Queue::fake();
    [$decision, $encounter] = referPortalCopyFixture($fixture, 'retracted');
    setVerifiedPortalCopyPdf($document);
    $job = new IssueConsultationPortalCopy($attempt->id, $document->id, $decision->id, $encounter->id, $guidance->id);
    app()->call([$job, 'handle']);

    $release = ResultRelease::query()->where('consultation_decision_id', $decision->id)->where('channel', 'portal')->firstOrFail();
    app(UnreleaseResult::class)->handle($release, $guidance, 'Corrected outcome.');
    app()->call([$job, 'handle']);

    expect($release->fresh()->is_current)->toBeFalse()
        ->and(ResultRelease::query()->where('consultation_decision_id', $decision->id)->where('channel', 'portal')->count())->toBe(1);
    [$laterDecision, $laterEncounter] = referPortalCopyFixture($fixture, 'retracted-again');
    $laterJob = new IssueConsultationPortalCopy($attempt->id, $document->id, $laterDecision->id, $laterEncounter->id, $guidance->id);
    app()->call([$laterJob, 'handle']);
    expect(ResultRelease::query()->where('admission_attempt_id', $attempt->id)->where('channel', 'portal')->count())->toBe(1);
});

test('decision rollback queues no portal issuance', function (): void {
    $fixture = portalCopyLifecycleFixture('rollback');
    [$attempt, , $guidance] = $fixture;
    Queue::fake();

    try {
        DB::transaction(function () use ($attempt, $guidance): void {
            app(RecordConsultationDecision::class)->handle(
                $attempt,
                $guidance,
                'approve',
                null,
                'portal-copy-rolled-back',
            );
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback');
    }

    expect(ConsultationDecision::query()->where('idempotency_key', 'portal-copy-rolled-back')->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

test('terminal frozen PDF preparation failure marks the artifact unavailable without exposing exception text', function (): void {
    $fixture = portalCopyLifecycleFixture('failed');
    [$attempt, $document, $guidance] = $fixture;
    Queue::fake();
    [$decision, $encounter] = approvePortalCopyFixture($fixture, 'failed');

    $job = new GenerateConsultationResultPdf($attempt->id, $document->id, $decision->id, $encounter->id, $guidance->id);
    $job->failed(new RuntimeException('sensitive renderer failure detail'));
    $manifest = $document->fresh()->artifact_manifest;

    expect($manifest['pdf']['status'] ?? null)->toBe('failed')
        ->and($manifest['pdf']['reason'] ?? null)->toBe('automatic_preparation_failed')
        ->and(json_encode($manifest))->not->toContain('sensitive renderer failure detail')
        ->and(app(ResultDocumentPdf::class)->isReady($document->fresh()))->toBeFalse();
});

test('PDF readiness verifies encrypted bytes, manifest integrity, and document-owned storage paths', function (): void {
    $fixture = portalCopyLifecycleFixture('artifact');
    [, $document] = $fixture;
    setVerifiedPortalCopyPdf($document);
    $pdf = app(ResultDocumentPdf::class);
    expect($pdf->isReady($document->fresh()))->toBeTrue()
        ->and(str_starts_with($pdf->read($document->fresh()), '%PDF-'))->toBeTrue();

    $manifest = $document->fresh()->artifact_manifest;
    $manifest['pdf']['sha256'] = hash('sha256', 'other artifact');
    $document->forceFill(['artifact_manifest' => $manifest])->save();
    expect($pdf->isReady($document->fresh()))->toBeFalse();
});

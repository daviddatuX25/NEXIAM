<?php

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\AuditEvent;
use App\Models\Printing\PrintJob;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\ResultSheet\Actions\RequestResultSheetPrint;
use App\ResultSheet\Pipeline\PreviewResultSheetJob;
use App\ResultSheet\Queries\PrintJobHistoryQuery;
use App\ResultSheet\Queries\ReleaseOverviewQuery;
use App\ResultSheet\Rendering\DocxToPdfConverter;
use App\ResultSheet\Rendering\ResultSheetPreviewRenderer;
use App\ResultSheet\Rendering\ResultSheetRenderer;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use App\Support\SensitiveAssetStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @return array{guidance: User, grading: GradingSession, documents: list<ResultDocument>}
 */
function preparedResultDocuments(TestCase $test, int $count = 1): array
{
    $fixture = buildGradingFixture($test, applicantCount: $count);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $documents = [];

    foreach ($fixture['attempts'] as $attempt) {
        foreach (activeAreas() as $id) {
            $area = AptitudeArea::query()->findOrFail($id);
            ScoreRecord::query()->create([
                'grading_session_id' => $grading->id,
                'admission_attempt_id' => $attempt->id,
                'aptitude_area_id' => $id,
                'score' => number_format(min(20, (int) $area->max_items), 2, '.', ''),
                'converted_score' => 'Above Average',
                'source' => ScoreRecord::SOURCE_MANUAL,
                'recorded_by_user_id' => $guidance->id,
                'idempotency_key' => 'preview-query-'.$attempt->id.'-'.$id,
            ]);
        }
    }

    $grading->update([
        'status' => GradingSession::STATUS_FINALIZED,
        'finalized_at' => now(),
        'finalized_by_user_id' => $guidance->id,
    ]);
    $finalized = $grading->fresh();
    expect($finalized->status)->toBe(GradingSession::STATUS_FINALIZED)
        ->and($finalized->finalized_at)->not->toBeNull()
        ->and($finalized->finalized_by_user_id)->toBe($guidance->id);

    foreach ($fixture['attempts'] as $attempt) {
        $documents[] = app(PrepareResultDocument::class)->handle($attempt->fresh(), $guidance);
    }

    return ['guidance' => $guidance, 'grading' => $grading, 'documents' => $documents];
}

function htmlResultSheetTemplate(): ResultSheetTemplate
{
    return ResultSheetTemplate::query()->create([
        'name' => 'Query Preview HTML',
        'mode' => ResultSheetTemplate::MODE_HTML,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<h1>{{institution_name}}</h1><p>{{applicant_name}} ({{applicant_reference}})</p><table><tbody class="scores-rows-placeholder"></tbody></table>',
        'is_active' => true,
    ]);
}
test('print job history query returns session-scoped jobs with item stats', function (): void {
    Queue::fake();
    $fixture = preparedResultDocuments($this, 2);
    $guidance = $fixture['guidance'];
    $session = $fixture['grading'];
    $documentIds = array_map(static fn (ResultDocument $document): int => (int) $document->id, $fixture['documents']);

    $job = app(RequestResultSheetPrint::class)->handle($guidance, $documentIds, copies: 1);
    $history = app(PrintJobHistoryQuery::class)->forSession($session);

    expect($history)->toHaveCount(1)
        ->and($history[0]['id'])->toBe($job->id)
        ->and($history[0]['progress_total'])->toBe(2)
        ->and($history[0]['ready_items'])->toBe(0);
});

test('release overview query returns finalized session tallies and defaults release index to unprinted', function (): void {
    $fixture = preparedResultDocuments($this, 2);
    $session = $fixture['grading'];
    $guidance = $fixture['guidance'];
    $institutionId = (int) $session->examSession->academicCycle->institution_id;

    $openExamSession = $session->examSession->replicate(['code']);
    $openExamSession->code = 'EX-OPEN-'.uniqid();
    $openExamSession->save();
    $openSession = GradingSession::query()->create([
        'exam_session_id' => $openExamSession->id,
        'status' => GradingSession::STATUS_OPEN,
        'opened_at' => now(),
        'opened_by_user_id' => $guidance->id,
    ]);

    $sessions = app(ReleaseOverviewQuery::class)->forInertia($institutionId);
    $target = collect($sessions)->firstWhere('id', $session->id);
    $openTarget = collect($sessions)->firstWhere('id', $openSession->id);

    expect($target)->not->toBeNull()
        ->and($openTarget)->toBeNull()
        ->and($target['id'])->toBe($session->id)
        ->and($target['examinees_count'])->toBe(2)
        ->and($target['prepared_count'])->toBe(2)
        ->and($target['printed_count'])->toBe(0)
        ->and($target['unprinted_count'])->toBe(2)
        ->and($target['delivered_count'])->toBe(0);

    $this->actingAs($guidance)
        ->withSession([
            'auth.surface' => 'staff',
            'auth.session_version' => (int) ($guidance->staffProfile?->authenticationState?->session_version ?? 1),
        ])
        ->get(route('release.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'unprinted')
            ->where('sessions', fn ($rows): bool => collect($rows)->contains('id', $session->id)
                && ! collect($rows)->contains('id', $openSession->id))
        );
});

test('preview renderer produces pdf bytes directly for html template', function (): void {
    seedE2Foundation();
    $template = htmlResultSheetTemplate();
    $result = app(ResultSheetPreviewRenderer::class)->renderPreview($template, [], useSampleData: true);

    expect($result['mode'])->toBe(ResultSheetTemplate::MODE_HTML)
        ->and($result['type'])->toBe('pdf_bytes')
        ->and($result['pdf_bytes'])->toStartWith('%PDF');
});
test('docx preview creates a tagged ephemeral preview job', function (): void {
    seedE2Foundation();
    Queue::fake();
    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    $sourcePath = 'result-sheet-templates/preview-fixture.docx';
    $sourceBytes = (string) file_get_contents($asset);
    Storage::disk('local')->put($sourcePath, $sourceBytes);
    $template = new ResultSheetTemplate([
        'name' => 'Preview Word',
        'mode' => 'docx',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => $sourcePath,
        'document_extension' => 'docx',
    ]);

    $result = app(ResultSheetPreviewRenderer::class)->renderPreview($template, [], useSampleData: true);
    $job = PrintJob::query()->findOrFail($result['job_id']);
    $pinned = $job->pinned_input_json;
    $expectedChecksum = hash('sha256', $sourceBytes);

    expect($result['type'])->toBe('preview_job')
        ->and($job->is_preview)->toBeTrue()
        ->and($job->scope_type)->toBe('preview')
        ->and($pinned['source_path'])->not->toBe($sourcePath)
        ->and($pinned['source_checksum'])->toBe($expectedChecksum)
        ->and(Storage::disk('local')->get($pinned['source_path']))->toBe($sourceBytes)
        ->and($pinned['template_snapshot']['document_path'])->toBe($pinned['source_path'])
        ->and($pinned['template_snapshot']['document_checksum'])->toBe($expectedChecksum);

    Storage::disk('local')->delete([$sourcePath, $pinned['source_path']]);
});

test('preview requester can query and download their queued preview job', function (): void {
    seedE2Foundation();
    Queue::fake();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $guidance->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $guidance->givePermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE);
    $preview = null;
    $job = null;

    try {
        $preview = actingAsAdmissionSupportStaff($this, $guidance)
            ->post(route('setup.result-templates.preview'), [
                'mode' => 'docx',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'logical_unit' => 'full',
                'document' => new UploadedFile(
                    database_path('seed-assets/result-sheets/generic_results.docx'),
                    'generic_results.docx',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    null,
                    true,
                ),
            ])
            ->assertOk();

        $job = PrintJob::query()->findOrFail($preview->json('preview_job_id'));
        $jobId = $job->id;
        $ownedSource = $job->pinned_input_json['source_path'];

        expect($job->is_preview)->toBeTrue()
            ->and($job->artifact_type)->toBe(PrintJob::ARTIFACT_RESULT_SHEET)
            ->and($job->scope_type)->toBe('preview')
            ->and($job->requested_by_user_id)->toBe($guidance->id);

        $guidance->revokePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
        foreach ($guidance->roles as $role) {
            $role->revokePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
        }
        actingAsAdmissionSupportStaff($this, $guidance)->get(route('printing.jobs.show', $jobId))->assertForbidden();
        actingAsAdmissionSupportStaff($this, $guidance)->get(route('printing.jobs.download', $jobId))->assertForbidden();
        $guidance->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
        actingAsAdmissionSupportStaff($this, $guidance)->get(route('printing.jobs.show', $jobId))
            ->assertOk()
            ->assertJsonPath('id', $jobId)
            ->assertJsonPath('is_preview', true)
            ->assertJsonPath('status', PrintJob::STATUS_QUEUED)
            ->assertJsonPath('ready', false);
        actingAsAdmissionSupportStaff($this, $guidance)->get(route('printing.jobs.download', $jobId))
            ->assertStatus(409)
            ->assertJsonPath('error', 'print_output_not_ready');

        $expectedBytes = "%PDF-preview-regression\nexact-worker-output";
        $converter = new class($expectedBytes) implements DocxToPdfConverter
        {
            public function __construct(private readonly string $bytes) {}

            public function isAvailable(): bool
            {
                return true;
            }

            public function convert(string $documentPath, ?float $timeoutSeconds = null): string
            {
                return $this->bytes;
            }
        };
        app()->instance(DocxToPdfConverter::class, $converter);

        (new PreviewResultSheetJob($jobId))->handle(
            app(ResultSheetRenderer::class),
            app(ResultSheetPlaceholderService::class),
            app(SensitiveAssetStore::class),
            app(RecordAuditEvent::class),
        );
        $job->refresh();
        $checksum = hash('sha256', $expectedBytes);

        expect($job->status)->toBe(PrintJob::STATUS_READY)
            ->and($job->file_checksum)->toBe($checksum);

        actingAsAdmissionSupportStaff($this, $guidance)->get(route('printing.jobs.show', $jobId))
            ->assertOk()
            ->assertJsonPath('status', PrintJob::STATUS_READY)
            ->assertJsonPath('ready', true);
        $download = actingAsAdmissionSupportStaff($this, $guidance)->get(route('printing.jobs.download', $jobId));
        $download->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($download->headers->hasCacheControlDirective('private'))->toBeTrue()
            ->and($download->headers->hasCacheControlDirective('no-store'))->toBeTrue();
        expect(hash('sha256', $download->getContent()))->toBe($checksum)
            ->and($download->getContent())->toBe($expectedBytes);

        $downloadAudit = AuditEvent::query()
            ->where('event_type', 'printing.job_downloaded')
            ->where('actor', (string) $guidance->id)
            ->latest('created_at')
            ->firstOrFail();
        expect($downloadAudit->payload['print_job_id'])->toBe($jobId)
            ->and($downloadAudit->payload['checksum'])->toBe($checksum)
            ->and(AuditEvent::query()->where('event_type', 'printing.job_download_denied')
                ->where('actor', (string) $guidance->id)->count())->toBe(1);

        $foreignGuidance = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
        $foreignGuidance->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
        $foreignGuidance->givePermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE);
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
        $registrar->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
        $registrar->givePermissionTo(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE);
        foreach ([$foreignGuidance, $registrar] as $unauthorizedActor) {
            actingAsAdmissionSupportStaff($this, $unauthorizedActor)->get(route('printing.jobs.show', $jobId))->assertForbidden();
            actingAsAdmissionSupportStaff($this, $unauthorizedActor)->get(route('printing.jobs.download', $jobId))->assertForbidden();
        }

        $guidance->revokePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
        foreach ($guidance->roles as $role) {
            $role->revokePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
        }
        actingAsAdmissionSupportStaff($this, $guidance)->get(route('printing.jobs.show', $jobId))->assertForbidden();
        actingAsAdmissionSupportStaff($this, $guidance)->get(route('printing.jobs.download', $jobId))->assertForbidden();
        $guidance->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
        actingAsAdmissionSupportStaff($this, $guidance);
        app(InvalidateStaffAuthentication::class)->handle(
            $guidance,
            $guidance->authenticationState,
        );
        $this->get(route('printing.jobs.show', $jobId))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email', 'Your session has expired due to a security update. Please sign in again.')
            ->assertSessionMissing('auth.surface')
            ->assertSessionMissing('auth.session_version');

        actingAsAdmissionSupportStaff($this, $guidance);
        DB::table('print_jobs')->where('id', $jobId)->update([
            'is_preview' => false,
            'scope_type' => 'invalid-preview-scope',
        ]);
        actingAsAdmissionSupportStaff($this, $guidance)->get(route('printing.jobs.show', $jobId))->assertForbidden();
        actingAsAdmissionSupportStaff($this, $guidance)->get(route('printing.jobs.download', $jobId))->assertForbidden();
    } finally {
        if ($job !== null) {
            $job->refresh();
            if ($job->file_path !== null) {
                Storage::disk('sensitive')->delete($job->file_path);
            }
            $ownedSource = $job->pinned_input_json['source_path'] ?? null;
            if (is_string($ownedSource)) {
                Storage::disk('local')->delete($ownedSource);
            }
        }
    }
});
test('queued stored docx preview renders the pinned source after replacement', function (): void {

    seedE2Foundation();
    Queue::fake();
    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    $sourcePath = 'result-sheet-templates/race-template.docx';
    $sourceBytes = (string) file_get_contents($asset);
    Storage::disk('local')->put($sourcePath, $sourceBytes);
    $template = ResultSheetTemplate::query()->create([
        'name' => 'Stored Preview Word',
        'mode' => ResultSheetTemplate::MODE_DOCX,
        'source_kind' => ResultSheetTemplate::SOURCE_DOCX,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => $sourcePath,
        'document_checksum' => hash('sha256', $sourceBytes),
        'document_extension' => 'docx',
        'is_active' => false,
    ]);

    $result = app(ResultSheetPreviewRenderer::class)->renderPreview($template, [], useSampleData: true);
    $job = PrintJob::query()->findOrFail($result['job_id']);
    $pinned = $job->pinned_input_json;
    $pinnedPath = $pinned['source_path'];
    $sourceChecksum = $pinned['source_checksum'];

    Storage::disk('local')->put($sourcePath, 'replaced after queue creation');

    expect($pinnedPath)->not->toBe($sourcePath)
        ->and(Storage::disk('local')->get($pinnedPath))->toBe($sourceBytes)
        ->and($sourceChecksum)->toBe(hash('sha256', $sourceBytes))
        ->and($pinned['template_id'])->toBe($template->id)
        ->and($pinned['template_lookup']['template_id'])->toBe($template->id)
        ->and($pinned['template_lookup']['document_path'])->toBe($sourcePath);

    $converter = new class implements DocxToPdfConverter
    {
        public ?string $documentContents = null;

        public function isAvailable(): bool
        {
            return true;
        }

        public function convert(string $documentPath, ?float $timeoutSeconds = null): string
        {
            $this->documentContents = (string) file_get_contents($documentPath);

            return '%PDF-race';
        }
    };
    app()->instance(DocxToPdfConverter::class, $converter);

    (new PreviewResultSheetJob($job->id))->handle(
        app(ResultSheetRenderer::class),
        app(ResultSheetPlaceholderService::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $job->refresh();
    $auditEvent = AuditEvent::query()
        ->where('event_type', 'printing.preview_ready')
        ->latest('created_at')
        ->firstOrFail();

    expect($job->status)->toBe(PrintJob::STATUS_READY)
        ->and($job->file_checksum)->toBe(hash('sha256', '%PDF-race'))
        ->and($converter->documentContents)->not->toBeNull()
        ->and(Storage::disk('local')->exists($pinnedPath))->toBeFalse()
        ->and(Storage::disk('local')->exists($sourcePath))->toBeTrue()
        ->and($auditEvent->payload['template_id'])->toBe($template->id)
        ->and($auditEvent->payload['template_lookup']['template_id'])->toBe($template->id)
        ->and($auditEvent->payload['source_checksum'])->toBe($sourceChecksum);

    Storage::disk('local')->delete($sourcePath);
    Storage::disk('sensitive')->delete((string) $job->file_path);
});

test('preview worker removes unsaved document sources after failure', function (): void {
    seedE2Foundation();
    Queue::fake();
    $sourcePath = 'result-sheet-templates/preview-failure.docx';
    Storage::disk('local')->put($sourcePath, 'not a document archive');
    $template = new ResultSheetTemplate([
        'name' => 'Failed Preview Word',
        'mode' => ResultSheetTemplate::MODE_DOCX,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => $sourcePath,
        'document_extension' => 'docx',
    ]);

    $result = app(ResultSheetPreviewRenderer::class)->renderPreview($template, [], useSampleData: true);
    $job = PrintJob::query()->findOrFail($result['job_id']);
    $pinnedPath = $job->pinned_input_json['source_path'];

    (new PreviewResultSheetJob($job->id))->handle(
        app(ResultSheetRenderer::class),
        app(ResultSheetPlaceholderService::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    expect($job->fresh()->status)->toBe(PrintJob::STATUS_FAILED)
        ->and(Storage::disk('local')->exists($pinnedPath))->toBeFalse()
        ->and(Storage::disk('local')->exists($sourcePath))->toBeFalse();
});

test('prune command purges ephemeral preview jobs older than 24 hours', function (): void {
    seedE2Foundation();

    $previewJob = PrintJob::query()->create([
        'artifact_type' => PrintJob::ARTIFACT_RESULT_SHEET,
        'is_preview' => true,
        'scope_type' => 'preview',
        'scope_id' => 0,
        'file_path' => 'print-jobs/preview/fake.pdf',
        'file_checksum' => 'fake-checksum',
        'status' => PrintJob::STATUS_READY,
    ]);
    DB::table('print_jobs')->where('id', $previewJob->id)->update(['created_at' => now()->subHours(26)]);

    $this->artisan('result-sheet:prune')
        ->assertExitCode(0);

    expect($previewJob->fresh()->file_path)->toBeNull()
        ->and($previewJob->fresh()->file_checksum)->toBeNull();
});

<?php

namespace App\Http\Controllers\Reports;

use App\Admission\Support\ApplicantQrCode;
use App\Admission\Support\ApplicantReference;
use App\Admission\Support\ApplicantScope;
use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\MountSlotRegistry;
use App\DocumentTemplate\OfficeDocumentPackCatalog;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\DocumentTemplate\Rendering\ReportHtmlContextFiller;
use App\DocumentTemplate\Services\DynamicAssetResolutionService;
use App\DocumentTemplate\Support\DocxTextExtractor;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Jobs\Printing\PrepareDocumentPrintJob;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Program;
use App\Models\Printing\PrintJob;
use App\Models\Reports\ReportTemplate;
use App\Models\User;
use App\Printing\PrintRenderer;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Rendering\ResultSheetCssService;
use App\ResultSheet\Support\TemplateShortValue;
use App\Support\SensitiveAssetStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentPrintController extends Controller
{
    public function scopeOptions(Request $request, CapabilityAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $templateId = $request->query('template_id');
        $template = $request->filled('template_id')
            ? ReportTemplate::query()->find((int) $templateId)
            : null;
        abort_if($request->filled('template_id') && $template === null, 422, 'Unknown or unavailable document surface.');

        $surfaceKey = $template?->surface_key ?? $request->query('surface_key');
        $surfaceKey = is_string($surfaceKey) && $surfaceKey !== '' ? $surfaceKey : null;
        if ($template === null && $surfaceKey !== null) {
            $template = ReportTemplate::query()
                ->where('surface_key', $surfaceKey)
                ->where('is_active', true)
                ->when($surfaceKey === 'custom_document', fn ($query) => $query->where('office', $user->actorScope()->office))
                ->orderByDesc('id')
                ->first();
        }
        $this->authorizeTemplate($user, $template);

        $grain = $template?->grain
            ?? (is_string($surfaceKey) ? OfficeDocumentPackCatalog::forSurface($surfaceKey)['grain'] ?? null : null)
            ?? 'cycle_program';
        $requestedGrain = $request->query('grain');
        if (is_string($requestedGrain) && $requestedGrain !== $grain) {
            abort(422, 'Requested document grain does not match the server contract.');
        }
        $this->authorizePrintScope($authorizer, $user, $surfaceKey, $grain);
        $cycles = AcademicCycle::query()
            ->select(['id', 'code', 'name', 'school_year', 'semester', 'is_active'])
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (AcademicCycle $cycle): array => [
                'id' => (int) $cycle->id,
                'code' => (string) $cycle->code,
                'name' => $cycle->staffLabel(),
                'is_active' => (bool) $cycle->is_active,
            ])
            ->all();
        $actorScope = $user->actorScope();
        $programs = Program::query()
            ->select(['id', 'department_id', 'code', 'name'])
            ->with('department:id,code')
            ->where('is_active', true)
            ->when($actorScope->office === 'program', static fn ($query) => $query->whereIn('code', $actorScope->programCodes ?: ['__none__']))
            ->orderBy('code')
            ->get()
            ->map(static fn (Program $program): array => [
                'id' => (int) $program->id,
                'code' => (string) $program->code,
                'name' => (string) $program->name,
                'department_code' => $program->department?->code,
            ])
            ->all();

        $applicants = [];
        if ($user->applicantAccount()->exists()) {
            $identityId = ApplicantScope::identityIdForUser($user);
            $attempts = $identityId === null ? collect() : AdmissionAttempt::query()
                ->forWorkspace()
                ->with('identity')
                ->where('applicant_identity_id', $identityId)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
            $applicants = $attempts->map(static fn (AdmissionAttempt $attempt): array => [
                'id' => (int) $attempt->id,
                'reference_code' => (string) $attempt->reference_code,
                'applicant_name' => trim(($attempt->identity?->first_name ?? '').' '.($attempt->identity?->last_name ?? '')) ?: (string) $attempt->reference_code,
                'program_code' => $attempt->preferred_program_code,
                'status' => $attempt->status,
            ])->all();
            $cycles = AcademicCycle::query()
                ->where('is_active', true)
                ->select(['id', 'code', 'name', 'school_year', 'semester', 'is_active'])
                ->orderByDesc('id')
                ->get()
                ->map(static fn (AcademicCycle $cycle): array => [
                    'id' => (int) $cycle->id,
                    'code' => (string) $cycle->code,
                    'name' => $cycle->staffLabel(),
                    'is_active' => true,
                ])
                ->all();
        } elseif ($grain === MountSlotRegistry::GRAIN_STUDENT) {
            $cycleId = $request->filled('cycle_id') ? (int) $request->query('cycle_id') : null;
            $programCode = trim((string) $request->query('program_code', ''));
            $search = trim((string) $request->query('search', ''));

            $attempts = AdmissionAttempt::query()
                ->forWorkspace()
                ->with('identity')
                ->when($cycleId !== null, static fn ($query) => $query->where('academic_cycle_id', $cycleId))
                ->when($programCode !== '', static fn ($query) => $query->where('preferred_program_code', $programCode))
                ->when($search !== '', static function ($query) use ($search, $cycleId): void {
                    $like = '%'.mb_strtolower($search).'%';
                    $query->where(static function ($query) use ($like, $search, $cycleId): void {
                        $query->whereRaw('LOWER(reference_code) LIKE ?', [$like]);
                        if (ApplicantReference::extractSequence($search) !== null) {
                            $query->orWhereIn(DB::raw('UPPER(reference_code)'), ApplicantReference::candidateCodes($search, $cycleId !== null ? AcademicCycle::query()->find($cycleId) : null));
                        }
                        $query->orWhereHas('identity', static function ($identityQuery) use ($like): void {
                            $identityQuery->whereRaw('LOWER(first_name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);
                        });
                    });
                })
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            $applicants = $attempts->map(static fn (AdmissionAttempt $attempt): array => [
                'id' => (int) $attempt->id,
                'reference_code' => (string) $attempt->reference_code,
                'applicant_name' => trim(($attempt->identity?->first_name ?? '').' '.($attempt->identity?->last_name ?? '')) ?: (string) $attempt->reference_code,
                'program_code' => $attempt->preferred_program_code,
                'status' => $attempt->status,
            ])->all();
        }

        $sessions = [];
        if ($grain === MountSlotRegistry::GRAIN_SESSION) {
            $sessions = ExamSession::query()
                ->with('room')
                ->latest('starts_at')
                ->limit(50)
                ->get()
                ->map(static fn (ExamSession $session): array => [
                    'id' => (int) $session->id,
                    'label' => (string) ($session->label ?? $session->code),
                    'exam_date' => $session->starts_at?->toDateString(),
                    'room_name' => (string) ($session->room?->name ?? $session->room?->code ?? ''),
                ])
                ->all();
        }

        return response()->json([
            'cycles' => $cycles,
            'programs' => $programs,
            'applicants' => $applicants,
            'items' => $grain === MountSlotRegistry::GRAIN_SESSION ? $sessions : $applicants,
            'sessions' => $sessions,
        ]);
    }

    public function preflight(Request $request, CapabilityAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'template_id' => ['sometimes', 'nullable', 'integer'],
            'surface_key' => ['sometimes', 'nullable', 'string', 'max:120'],
            'grain' => ['sometimes', 'nullable', 'in:student,session,cycle_program'],
            'cycle_id' => ['sometimes', 'nullable', 'integer'],
            'program_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'department_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'attempt_ids' => ['sometimes', 'nullable', 'array'],
            'attempt_ids.*' => ['integer'],
            'session_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $template = isset($validated['template_id'])
            ? ReportTemplate::query()->find((int) $validated['template_id'])
            : null;
        abort_if(isset($validated['template_id']) && $template === null, 422, 'Unknown or unavailable document surface.');

        $requestedSurfaceKey = $validated['surface_key'] ?? null;
        if ($template !== null && $requestedSurfaceKey !== null && $requestedSurfaceKey !== $template->surface_key) {
            abort(422, 'Unknown or unavailable document surface.');
        }
        $surfaceKey = (string) ($template?->surface_key ?? $requestedSurfaceKey ?? 'course_quota_qualifiers');
        $surface = DocumentSurfaceRegistry::resolve($surfaceKey);
        abort_if($surface === null, 422, 'Unknown or unavailable document surface.');
        if ($template === null) {
            $template = ReportTemplate::query()
                ->where('surface_key', $surfaceKey)
                ->where('is_active', true)
                ->when($surfaceKey === 'custom_document', fn ($query) => $query->where('office', $user->actorScope()->office))
                ->orderByDesc('id')
                ->first();
        }
        $this->authorizeTemplate($user, $template);

        $grain = (string) ($template?->grain ?? OfficeDocumentPackCatalog::forSurface($surfaceKey)['grain'] ?? 'cycle_program');
        abort_unless(in_array($grain, [
            MountSlotRegistry::GRAIN_STUDENT,
            MountSlotRegistry::GRAIN_SESSION,
            MountSlotRegistry::GRAIN_CYCLE_PROGRAM,
        ], true), 422);
        if (isset($validated['grain'])) {
            abort_unless($validated['grain'] === $grain, 422, 'Requested document grain does not match the server contract.');
        }
        $scope = $this->validatedPrintScope($validated);
        $this->authorizePrintScope($authorizer, $user, $surfaceKey, $grain, $scope['attempt_ids'] ?? []);

        $scope = $this->validatedPrintScope($validated);
        $count = $this->resolveSubjectCount($user, $grain, $scope, $surfaceKey);

        return response()->json([
            'eligible_sync' => $count === 1 && ($template?->mode ?? ReportTemplate::MODE_HTML) === ReportTemplate::MODE_HTML,
            'count' => $count,
            'grain' => $grain,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{attempt_ids?: list<int>, cycle_id?: int|null, program_code?: string|null, department_code?: string|null, session_id?: int|null}
     */
    private function validatedPrintScope(array $validated): array
    {
        $attemptIds = array_values(array_unique(array_map('intval', $validated['attempt_ids'] ?? [])));

        return [
            'attempt_ids' => $attemptIds,
            'cycle_id' => isset($validated['cycle_id']) ? (int) $validated['cycle_id'] : null,
            'program_code' => $validated['program_code'] ?? null,
            'department_code' => $validated['department_code'] ?? null,
            'session_id' => isset($validated['session_id']) ? (int) $validated['session_id'] : null,
        ];
    }

    /**
     * @param  array{attempt_ids?: list<int>, cycle_id?: int|null, program_code?: string|null, department_code?: string|null, session_id?: int|null}  $scope
     */
    private function resolveSubjectCount(User $user, string $grain, array $scope, ?string $surfaceKey = null): int
    {
        if ($grain === MountSlotRegistry::GRAIN_STUDENT) {
            $attemptIds = $scope['attempt_ids'] ?? [];
            abort_if($attemptIds === [], 422, 'Select at least one applicant to print.');

            $query = AdmissionAttempt::query()
                ->forWorkspace()
                ->whereIn('id', $attemptIds);
            $count = $query->count();
            abort_if($count !== count($attemptIds), 422, 'One or more selected applicants are outside the active workspace or unavailable.');
            if ($surfaceKey === 'clearance_slip') {
                $hasUnconfirmedReceipt = AdmissionAttempt::query()
                    ->forWorkspace()
                    ->whereIn('id', $attemptIds)
                    ->whereDoesntHave('latestClearanceReceipt', static fn ($receiptQuery) => $receiptQuery->where('status', AdmissionClearanceReceipt::STATUS_CONFIRMED))
                    ->exists();
                abort_if($hasUnconfirmedReceipt, 422, 'Only applicants with a confirmed Admission Clearance Receipt can print an official Clearance Slip.');
            }

            return $count;
        }

        if (($scope['cycle_id'] ?? null) !== null) {
            abort_unless(AcademicCycle::query()->whereKey($scope['cycle_id'])->exists(), 422, 'Select an available academic cycle.');
        }
        if (($scope['program_code'] ?? null) !== null) {
            abort_unless(Program::query()->where('code', $scope['program_code'])->exists(), 422, 'Select an available program.');
        }

        if ($grain === MountSlotRegistry::GRAIN_SESSION) {
            abort_if(($scope['session_id'] ?? null) === null, 422, 'Select a session to print.');
            $count = ExamAssignment::query()
                ->where('exam_session_id', $scope['session_id'])
                ->whereIn('status', ExamAssignment::ACTIVE_STATUSES)
                ->whereHas('admissionAttempt', static fn ($query) => $query->forWorkspace())
                ->count();

            return max(1, $count);
        }

        return 1;
    }

    public function render(
        Request $request,
        CapabilityAuthorizer $authorizer,
        DocumentBlueprintCompiler $compiler,
        ReportHtmlContextFiller $htmlFiller,
        DocxTemplateFiller $docxFiller,
        LibreOfficeConverter $converter,
        DocxTextExtractor $extractor,
        PrintRenderer $printRenderer,
        SensitiveAssetStore $assetStore
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'template_id' => ['sometimes', 'nullable', 'integer'],
            'surface_key' => ['sometimes', 'nullable', 'string', 'max:120'],
            'grain' => ['sometimes', 'nullable', 'in:student,session,cycle_program'],
            'cycle_id' => ['sometimes', 'nullable', 'integer'],
            'program_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'department_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'attempt_ids' => ['sometimes', 'nullable', 'array'],
            'attempt_ids.*' => ['integer'],
            'session_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $templateId = $validated['template_id'] ?? null;
        $template = $templateId !== null
            ? ReportTemplate::query()->find((int) $templateId)
            : null;
        abort_if($templateId !== null && $template === null, 422, 'Unknown or unavailable document surface.');

        $requestedSurfaceKey = $validated['surface_key'] ?? null;
        if ($template !== null && $requestedSurfaceKey !== null && $requestedSurfaceKey !== $template->surface_key) {
            abort(422, 'Unknown or unavailable document surface.');
        }

        $surfaceKey = (string) ($template?->surface_key ?? $requestedSurfaceKey ?? 'course_quota_qualifiers');
        $surface = DocumentSurfaceRegistry::resolve($surfaceKey);
        abort_if($surface === null, 422, 'Unknown or unavailable document surface.');
        if ($template === null) {
            $template = ReportTemplate::query()
                ->where('surface_key', $surfaceKey)
                ->where('is_active', true)
                ->when($surfaceKey === 'custom_document', fn ($query) => $query->where('office', $user->actorScope()->office))
                ->orderByDesc('id')
                ->first();
        }
        $this->authorizeTemplate($user, $template);

        $pack = OfficeDocumentPackCatalog::forSurface($surfaceKey);
        $grain = $template?->grain ?? ($pack['grain'] ?? MountSlotRegistry::GRAIN_CYCLE_PROGRAM);
        abort_unless(in_array($grain, [
            MountSlotRegistry::GRAIN_STUDENT,
            MountSlotRegistry::GRAIN_SESSION,
            MountSlotRegistry::GRAIN_CYCLE_PROGRAM,
        ], true), 422);
        $scope = $this->validatedPrintScope($validated);
        $this->authorizePrintScope($authorizer, $user, $surfaceKey, $grain, $scope['attempt_ids'] ?? []);
        if (isset($validated['grain']) && $validated['grain'] !== $grain) {
            abort(422, 'Requested document grain does not match the server contract.');
        }
        $scope = $this->validatedPrintScope($validated);
        $subjectCount = $this->resolveSubjectCount($user, $grain, $scope, $surfaceKey);
        $eligibleSync = $subjectCount === 1 && ($template?->mode ?? ReportTemplate::MODE_HTML) === ReportTemplate::MODE_HTML;
        if (! $eligibleSync) {
            abort_if($template === null, 422, 'Select an available document template before queueing a print job.');

            return $this->queueDocumentPrint($user, $template, $grain, $scope, $subjectCount);
        }

        $paperSize = (string) ($template?->paper_size ?? 'a4');
        $orientation = (string) ($template?->orientation ?? 'portrait');
        $renderedCount = 1;
        if ($grain === MountSlotRegistry::GRAIN_STUDENT) {
            $attemptIds = array_values(array_unique(array_map('intval', (array) $request->input('attempt_ids', []))));
            abort_if($attemptIds === [], 422, 'Select at least one applicant to print.');

            $attempts = AdmissionAttempt::query()
                ->forWorkspace()
                ->with(['identity', 'academicCycle.institution', 'examAssignments.examSession.room', 'latestClearanceReceipt.confirmedBy', 'latestClearanceReceipt.program'])
                ->whereIn('id', $attemptIds)
                ->get();
            abort_if(
                $attempts->count() !== count($attemptIds),
                422,
                'One or more selected applicants are outside the active workspace or unavailable.',
            );
            if (
                $surfaceKey === 'clearance_slip'
                && $attempts->contains(static fn (AdmissionAttempt $attempt): bool => $attempt->latestClearanceReceipt?->status !== AdmissionClearanceReceipt::STATUS_CONFIRMED)
            ) {
                abort(422, 'Only applicants with a confirmed Admission Clearance Receipt can print an official Clearance Slip.');
            }
            $renderedCount = $attempts->count();

            [$rawHtml, $marginMm] = $this->resolveTemplateHtml($template, $surface, $compiler, $paperSize, $orientation);
            $studentPages = [];
            foreach ($attempts as $index => $attempt) {
                $context = $this->buildStudentContext($attempt, $index + 1, $template, $user);
                $studentPages[] = $htmlFiller->fill($rawHtml, $context);
            }

            $wrappedHtml = (new ResultSheetCssService)->wrapForPdf(
                implode('<div style="page-break-after:always;"></div>', $studentPages),
                $paperSize,
                $orientation,
                $marginMm,
            );
            $pdfBytes = $printRenderer->render($wrappedHtml, [
                'format' => $paperSize,
                'orientation' => $orientation,
            ]);
        } else {
            $cycleId = $validated['cycle_id'] ?? null;
            $programCode = $validated['program_code'] ?? null;
            $data = $surface->resolveData([
                'academic_cycle_id' => $cycleId,
                'cycle_id' => $cycleId,
                'program_code' => $programCode,
                'department_code' => $scope['department_code'],
                'session_id' => $scope['session_id'],
            ]);
            if ($data === []) {
                $data = $surface->sampleContext();
            }

            $documentPath = $template?->document_path;
            $sourcePath = is_string($documentPath) && $documentPath !== ''
                ? Storage::disk('local')->path($documentPath)
                : null;
            if ($template?->mode === 'docx' && $sourcePath !== null && is_file($sourcePath)) {
                $filledPath = $docxFiller->fill($sourcePath, $data, $surface->repeatingRegions());
                try {
                    if ($converter->isAvailable()) {
                        $pdfBytes = $converter->convertToPdf($filledPath);
                    } else {
                        $fallbackHtml = '<html><body>'.nl2br(e($extractor->text($filledPath))).'</body></html>';
                        $wrappedHtml = (new ResultSheetCssService)->wrapForPdf($fallbackHtml, $paperSize, $orientation);
                        $pdfBytes = $printRenderer->render($wrappedHtml, [
                            'format' => $paperSize,
                            'orientation' => $orientation,
                        ]);
                    }
                } finally {
                    if (is_file($filledPath)) {
                        @unlink($filledPath);
                    }
                }
            } else {
                [$rawHtml, $marginMm] = $this->resolveTemplateHtml($template, $surface, $compiler, $paperSize, $orientation);
                $filledHtml = $htmlFiller->fill($rawHtml, $data);
                $wrappedHtml = (new ResultSheetCssService)->wrapForPdf($filledHtml, $paperSize, $orientation, $marginMm);
                $pdfBytes = $printRenderer->render($wrappedHtml, [
                    'format' => $paperSize,
                    'orientation' => $orientation,
                ]);
            }
        }

        $job = DB::transaction(function () use ($assetStore, $user, $template, $surfaceKey, $grain, $scope, $pdfBytes, $renderedCount): PrintJob {
            $scopeId = (int) ($scope['session_id'] ?? $scope['cycle_id'] ?? $scope['attempt_ids'][0] ?? $template?->id ?? 1);
            $job = PrintJob::query()->create([
                'artifact_type' => PrintJob::ARTIFACT_OFFICE_DOCUMENT,
                'scope_type' => $grain,
                'scope_id' => $scopeId,
                'status' => PrintJob::STATUS_QUEUED,
                'requested_by_user_id' => $user->id,
                'progress_total' => $renderedCount,
                'progress_done' => $renderedCount,
                'pinned_input_json' => ['template_id' => $template?->id, 'surface_key' => $template?->surface_key ?? $surfaceKey, ...$scope],
            ]);
            $checksum = $assetStore->hmac($pdfBytes);
            $path = 'print-jobs/'.$job->id.'/'.$checksum.'.pdf';
            $assetStore->store($pdfBytes, $path);
            $job->forceFill([
                'status' => PrintJob::STATUS_READY,
                'file_path' => $path,
                'file_checksum' => $checksum,
                'completed_at' => now(),
            ])->save();

            return $job;
        });

        return response()->json([
            'status' => PrintJob::STATUS_READY,
            'job_id' => $job->id,
            'download_url' => route('document-printing.jobs.download', $job->id),
            'pdf_base64' => base64_encode($pdfBytes),
        ]);
    }

    /**
     * @param  array{attempt_ids?: list<int>, cycle_id?: int|null, program_code?: string|null, department_code?: string|null, session_id?: int|null}  $scope
     */
    private function queueDocumentPrint(User $user, ReportTemplate $template, string $grain, array $scope, int $count): JsonResponse
    {
        $job = DB::transaction(function () use ($user, $template, $grain, $scope, $count): PrintJob {
            $scopeId = (int) ($scope['session_id'] ?? $scope['cycle_id'] ?? $scope['attempt_ids'][0] ?? $template->id);
            $pinnedInput = [
                'template_id' => $template->id,
                'surface_key' => $template->surface_key,
                ...$scope,
            ];
            $job = PrintJob::query()->create([
                'artifact_type' => PrintJob::ARTIFACT_OFFICE_DOCUMENT,
                'scope_type' => $grain,
                'scope_id' => $scopeId,
                'status' => PrintJob::STATUS_QUEUED,
                'requested_by_user_id' => $user->id,
                'progress_total' => $count,
                'progress_done' => 0,
                'pinned_input_json' => $pinnedInput,
            ]);

            PrepareDocumentPrintJob::dispatch($job, $template, $scope)->afterCommit();

            return $job;
        });

        return response()->json([
            'status' => PrintJob::STATUS_QUEUED,
            'job_id' => $job->id,
            'status_url' => route('document-printing.jobs.status', $job->id),
        ], 202);
    }

    public function jobStatus(Request $request, PrintJob $job, CapabilityAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->authorizeDocumentJob($job, $user, $authorizer);

        return response()->json([
            'job_id' => $job->id,
            'status' => $job->status,
            'stage' => $job->stage,
            'progress' => [
                'settled_count' => $job->progress_done,
                'total_count' => $job->progress_total,
            ],
            'download_url' => route('document-printing.jobs.download', $job->id),
        ]);
    }

    public function download(
        Request $request,
        PrintJob $job,
        CapabilityAuthorizer $authorizer,
        SensitiveAssetStore $assetStore
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->authorizeDocumentJob($job, $user, $authorizer);
        abort_unless($job->status === PrintJob::STATUS_READY && is_string($job->file_path), 409);

        $bytes = $assetStore->read($job->file_path, $job->file_checksum);

        return response($bytes, 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function markJobPrinted(
        Request $request,
        PrintJob $job,
        RecordAuditEvent $audit,
        CapabilityAuthorizer $authorizer
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->authorizeDocumentJob($job, $user, $authorizer);
        abort_unless($job->status === PrintJob::STATUS_READY, 409);

        $event = DB::transaction(function () use ($job, $user, $audit): AuditEvent {
            $lockedJob = PrintJob::query()->lockForUpdate()->findOrFail($job->id);
            abort_unless($lockedJob->status === PrintJob::STATUS_READY, 409);

            $existing = AuditEvent::query()
                ->where('event_type', 'document_template.printed')
                ->where('payload->print_job_id', $lockedJob->id)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $pinned = is_array($lockedJob->pinned_input_json) ? $lockedJob->pinned_input_json : [];
            $templateId = (int) ($pinned['template_id'] ?? 0);
            $template = $templateId > 0 ? ReportTemplate::query()->find($templateId) : null;

            $attemptIds = $lockedJob->items()
                ->whereNotNull('admission_attempt_id')
                ->orderBy('item_index')
                ->pluck('admission_attempt_id')
                ->map(static fn ($attemptId): int => (int) $attemptId)
                ->all();
            if ($attemptIds === []) {
                $attemptIds = array_values(array_unique(array_map('intval', $pinned['attempt_ids'] ?? [])));
            }

            return $audit->handle('document_template.printed', [
                'print_job_id' => $lockedJob->id,
                'artifact_checksum' => $lockedJob->file_checksum,
                'institution_id' => $user->actorScope()->institutionId,
                'template_id' => $template?->id,
                'surface_key' => $template?->surface_key ?? $pinned['surface_key'] ?? null,
                'attempt_ids' => $attemptIds,
                'session_id' => $pinned['session_id'] ?? null,
                'cycle_id' => $pinned['cycle_id'] ?? null,
            ], (string) $user->getAuthIdentifier());
        });

        return response()->json([
            'receipt_id' => $event->id,
            'printed_at' => $event->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'actor_name' => $user->name,
        ]);
    }

    private function authorizeDocumentJob(PrintJob $job, User $user, CapabilityAuthorizer $authorizer): void
    {
        abort_unless($job->artifact_type === 'office_document', 404);

        if ($user->applicantAccount()->exists()) {
            $identityId = ApplicantScope::identityIdForUser($user);
            $pinned = is_array($job->pinned_input_json) ? $job->pinned_input_json : [];
            $attemptIds = array_values(array_unique(array_map('intval', (array) ($pinned['attempt_ids'] ?? []))));
            $templateId = (int) ($pinned['template_id'] ?? 0);
            $template = $templateId > 0 ? ReportTemplate::query()->find($templateId) : null;
            $this->authorizeTemplate($user, $template);
            abort_unless($identityId !== null && $attemptIds !== [], 403);
            abort_unless(
                AdmissionAttempt::query()
                    ->whereIn('id', $attemptIds)
                    ->where('applicant_identity_id', $identityId)
                    ->count() === count($attemptIds),
                403,
            );
        }

        if ((int) $job->requested_by_user_id === (int) $user->getAuthIdentifier()) {
            return;
        }

        $pinned = is_array($job->pinned_input_json) ? $job->pinned_input_json : [];
        $templateId = (int) ($pinned['template_id'] ?? 0);
        $template = $templateId > 0 ? ReportTemplate::query()->find($templateId) : null;
        $this->authorizeTemplate($user, $template);
        $surfaceKey = $template?->surface_key ?? ($pinned['surface_key'] ?? null);
        $this->authorizePrintScope($authorizer, $user, is_string($surfaceKey) ? $surfaceKey : null);
    }

    public function markPrinted(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'template_id' => ['required', 'integer'],
            'attempt_ids' => ['nullable', 'array'],
            'attempt_ids.*' => ['integer'],
            'filename' => ['required', 'string', 'max:255'],
        ]);
        $template = ReportTemplate::query()->find((int) $validated['template_id']);
        abort_if($template === null, 422, 'Unknown or unavailable document surface.');
        $this->authorizePrintScope($authorizer, $user, (string) $template->surface_key);

        $attemptIds = array_values(array_unique(array_map('intval', $validated['attempt_ids'] ?? [])));
        if ($user->applicantAccount()->exists() && $attemptIds !== []) {
            $identityId = ApplicantScope::identityIdForUser($user);
            abort_unless($identityId !== null, 403);
            abort_unless(
                AdmissionAttempt::query()
                    ->whereIn('id', $attemptIds)
                    ->where('applicant_identity_id', $identityId)
                    ->count() === count($attemptIds),
                403,
            );
        }

        $event = $audit->handle('document_template.printed', [
            'institution_id' => $user->actorScope()->institutionId,
            'template_id' => $template->id,
            'surface_key' => $template->surface_key,
            'attempt_ids' => $attemptIds,
            'filename' => $validated['filename'],
        ], (string) $user->getAuthIdentifier());

        return response()->json([
            'receipt_id' => $event->id,
            'printed_at' => $event->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'actor_name' => $user->name,
        ]);
    }

    private function authorizeTemplate(User $user, ?ReportTemplate $template): void
    {
        if ($template?->institution_id !== null) {
            abort_unless((int) $template->institution_id === (int) $user->actorScope()->institutionId, 403);
        }

        if (
            $user->applicantAccount()->exists()
            && in_array($template?->surface_key, ['admission_slip', 'clearance_slip'], true)
        ) {
            return;
        }
        if ($template?->surface_key === 'custom_document' && $template->office !== null) {
            abort_unless($template->office === $user->actorScope()->office, 403);
        }
    }

    private function authorizeStudentPrintScope(CapabilityAuthorizer $authorizer, User $user, ?string $surfaceKey = null, array $attemptIds = []): void
    {
        if (
            $user->applicantAccount()->exists()
            && in_array($surfaceKey, ['admission_slip', 'clearance_slip'], true)
        ) {
            $identityId = ApplicantScope::identityIdForUser($user);
            abort_unless($identityId !== null, 403);
            if ($attemptIds !== []) {
                abort_unless(
                    AdmissionAttempt::query()
                        ->whereIn('id', $attemptIds)
                        ->where('applicant_identity_id', $identityId)
                        ->count() === count(array_unique($attemptIds)),
                    403,
                );
            }

            return;
        }

        $registrarAllowed = $authorizer->allowsAny($user, [
            CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
            CapabilityCatalog::REGISTRAR_REPORTS_VIEW,
            CapabilityCatalog::REGISTRAR_ADMISSION_CLEARANCE_CONFIRM,
        ]);
        $guidanceAllowed = $user->actorScope()->office === 'guidance'
            && $authorizer->allowsAny($user, [
                CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
                CapabilityCatalog::GUIDANCE_REPORTS_VIEW,
                CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
                CapabilityCatalog::GUIDANCE_SCORING_OMR,
                CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
            ]);

        abort_unless($registrarAllowed || $guidanceAllowed, 403);
    }

    private function authorizePrintScope(
        CapabilityAuthorizer $authorizer,
        User $user,
        ?string $surfaceKey,
        ?string $grain = null,
        array $attemptIds = []
    ): void {
        $applicantAllowed = $user->applicantAccount()->exists()
            && in_array($surfaceKey, ['admission_slip', 'clearance_slip'], true);
        $allowed = $applicantAllowed || ($surfaceKey !== null && DocumentSurfaceRegistry::canViewSurface($authorizer, $user, $surfaceKey));
        if (! $allowed && in_array($surfaceKey, ['admission_slip', 'clearance_slip'], true)) {
            $allowed = $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW);
        }
        abort_unless($allowed, 403);

        if ($grain === MountSlotRegistry::GRAIN_STUDENT) {
            $this->authorizeStudentPrintScope($authorizer, $user, $surfaceKey, $attemptIds);
        }
    }

    /**
     * @return array{0: string, 1: float}
     */
    private function resolveTemplateHtml(
        ?ReportTemplate $template,
        ?DocumentSurfaceInterface $surface,
        DocumentBlueprintCompiler $compiler,
        string $paperSize,
        string $orientation
    ): array {
        $marginMm = 12.0;
        if ($template !== null) {
            if (is_array($template->builder_json) && ! empty($template->builder_json['components'])) {
                $marginMm = (float) ($template->builder_json['page']['margin_mm'] ?? 12.0);
                if (is_string($template->compiled_html) && trim($template->compiled_html) !== '') {
                    return [$template->compiled_html, $marginMm];
                }

                $document = ResultSheetBuilderSchema::normalize($template->builder_json);
                $compiled = $compiler->compile($document);
                $html = is_array($compiled) ? (string) ($compiled['html'] ?? '') : (string) $compiled;
                if (trim($html) !== '') {
                    return [$html, $marginMm];
                }
            }

            foreach ([$template->compiled_html, $template->content] as $html) {
                if (is_string($html) && trim($html) !== '') {
                    return [$html, $marginMm];
                }
            }
        }

        if ($surface !== null) {
            $blueprint = $surface->defaultBlueprint($paperSize, $orientation);
            $marginMm = (float) ($blueprint['page']['margin_mm'] ?? 12.0);
            $document = ResultSheetBuilderSchema::normalize($blueprint);
            $compiled = $compiler->compile($document);
            $html = is_array($compiled) ? (string) ($compiled['html'] ?? '') : (string) $compiled;
            if (trim($html) !== '') {
                return [$html, $marginMm];
            }
        }

        abort(422, 'The selected document template has no printable HTML source.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStudentContext(AdmissionAttempt $attempt, int $rowNo, ?ReportTemplate $template = null, ?User $user = null): array
    {
        $identity = $attempt->identity;
        $referenceCode = (string) $attempt->reference_code;
        $firstName = (string) ($identity?->first_name ?? '');
        $familyName = (string) ($identity?->last_name ?? '');
        $applicantName = trim($firstName.' '.$familyName) ?: $referenceCode;
        $programCode = (string) ($attempt->preferred_program_code ?: $attempt->program_preference_1_code ?: '');
        $clearanceReceipt = $attempt->latestClearanceReceipt;
        $documentProgramCode = (string) ($clearanceReceipt?->program?->code ?? $programCode);
        $admissionStatus = ucfirst(str_replace('_', ' ', (string) $attempt->status));
        $gwa = $attempt->gwa === null ? '' : number_format((float) $attempt->gwa, 2);

        /** @var ExamAssignment|null $activeAssignment */
        $activeAssignment = $attempt->examAssignments->first(static fn (ExamAssignment $assignment): bool => $assignment->isActive())
            ?? $attempt->examAssignments->first();
        $examSession = $activeAssignment?->examSession;
        $institution = $attempt->academicCycle?->institution;

        $locatorQrToken = ApplicantQrCode::issue($attempt);
        $locatorQrUrl = ApplicantQrCode::entryUrl($attempt);
        $qrPng = (new DynamicAssetResolutionService)->renderVerificationQr($locatorQrUrl, 400, 4);
        $qrDataUri = 'data:image/png;base64,'.base64_encode($qrPng);

        return [
            'applicant_reference' => $referenceCode,
            'reference_code' => $referenceCode,
            'applicant_no' => $referenceCode,
            'applicant_number' => $referenceCode,
            'examinee_number' => ApplicantReference::paddedDigits($referenceCode, $attempt->academicCycle) ?? $referenceCode,
            'sequence_number' => ApplicantReference::compactNumber($referenceCode, $attempt->academicCycle) ?? $referenceCode,
            'applicant_name' => $applicantName,
            'first_name' => $firstName,
            'family_name' => $familyName,
            ...TemplateShortValue::fields(
                $attempt->last_school_enrolled,
                $attempt->strand,
            ),
            'strand' => (string) ($attempt->strand ?? ''),
            'strand_or_prev_course' => (string) ($attempt->strand ?? ''),
            'gwa' => $gwa,
            'program_code' => $documentProgramCode,
            'course_applied_code' => $programCode,
            'admission_status' => $admissionStatus,
            'academic_cycle' => (string) ($attempt->academicCycle?->staffLabel() ?? ''),
            'institution_name' => $institution?->name ?? 'Institution',
            'academic_cycle_name' => $attempt->academicCycle?->name ?? 'Current Cycle',
            'prepared_by_name' => $user?->name ?? 'Authorized Staff',
            'document_title' => $template?->name ?? 'Office Document',
            'custom_heading' => $template?->name ?? 'Office Document',
            'custom_note' => '',
            'shs_strand' => $attempt->strand ?? 'N/A',
            'gpa_or_gwa' => $attempt->gwa !== null ? number_format((float) $attempt->gwa, 2) : 'N/A',
            'application_status' => (string) ($attempt->status ?? 'pending'),
            'clearance_reference_no' => $clearanceReceipt?->receipt_number ?? $attempt->reference_code,
            'first_choice_program' => $attempt->preferred_program_code ?? 'N/A',
            'recommended_program' => $attempt->preferred_program_code ?? 'N/A',
            'counselor_decision' => (string) ($attempt->status ?? 'Advised'),
            'report_date' => now()->format('F j, Y'),
            'generated_at' => now()->format('F j, Y'),
            'exam_date' => $examSession?->starts_at?->format('F j, Y') ?? '',
            'exam_time' => $examSession?->starts_at?->format('g:i A') ?? '',
            'exam_time_window' => $examSession?->starts_at?->format('g:i A') ?? '',
            'clearance_status' => $clearanceReceipt?->status === AdmissionClearanceReceipt::STATUS_CONFIRMED
                ? 'Confirmed'
                : '',
            'receipt_number' => (string) ($clearanceReceipt?->receipt_number ?? ''),
            'confirmed_at' => $clearanceReceipt?->confirmed_at?->format('F j, Y g:i A') ?? '',
            'verified_by' => (string) ($clearanceReceipt?->confirmedBy?->name ?? ''),
            'applicant_locator_qr' => $qrDataUri,
            'verification_qr' => $qrDataUri,
            'qr_code_svg' => (new OmrQrCodeRenderer)->svg($locatorQrUrl, 240),
            'qr' => $locatorQrToken,
            'verification_code' => $locatorQrToken,
            'qualifiers' => [[
                'row_no' => $rowNo,
                'reference_code' => $referenceCode,
                'applicant_name' => $applicantName,
                'strand' => (string) ($attempt->strand ?? ''),
                'gwa' => $gwa,
                'program_code' => $programCode,
                'admission_status' => $admissionStatus,
                'accepted_at' => $attempt->accepted_at?->format('Y-m-d') ?? '',
            ]],
        ];
    }
}

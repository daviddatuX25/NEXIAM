<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Actions\Omr\ConfirmOmrCaptureBatch;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\RejectOmrCaptureBatch;
use App\Assessment\Actions\Omr\ReplaceOmrSheetPage;
use App\Assessment\Actions\Omr\RetryOmrCaptureSheet;
use App\Assessment\Actions\Omr\ReviewOmrCaptureSheet;
use App\Assessment\Actions\Omr\UploadOmrCaptureSheet;
use App\Http\Requests\Assessment\ReviewOmrSheetRequest;
use App\Http\Requests\Assessment\StoreOmrBatchRequest;
use App\Http\Requests\Assessment\StoreOmrSheetRequest;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\OpenGradingSession;
use App\Assessment\Support\GradingRoster;
use App\Http\Controllers\Controller;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Http\Requests\Assessment\OpenGradingSessionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AptitudeArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class GradingController extends Controller
{
    public function index(Request $request): Response
    {
        $areaCount = AptitudeArea::query()->where('is_active', true)->count();

        $sessions = GradingSession::query()
            ->with(['examSession.room', 'examSession.academicCycle'])
            ->orderByDesc('opened_at')
            ->limit(50)
            ->get();

        $sessionIds = $sessions->pluck('exam_session_id')->all();

        $eligibleAssignments = GradingRoster::queryForSessionIds($sessionIds)
            ->get()
            ->groupBy('exam_session_id');

        $absentBySession = ExamAssignment::query()
            ->whereIn('exam_session_id', $sessionIds)
            ->active()
            ->whereHas('attendanceRecord', fn ($query): \Illuminate\Database\Eloquent\Builder => $query->where('outcome', ExamAttendanceRecord::OUTCOME_ABSENT))
            ->get()
            ->groupBy('exam_session_id');

        $records = ScoreRecord::query()
            ->whereIn('grading_session_id', $sessions->pluck('id'))
            ->get(['grading_session_id', 'admission_attempt_id', 'aptitude_area_id']);

        $sessionsData = $sessions->map(
            fn (GradingSession $gs): array => $this->sessionRow(
                $gs,
                $eligibleAssignments->get($gs->exam_session_id, collect()),
                $absentBySession->get($gs->exam_session_id, collect()),
                $records->where('grading_session_id', $gs->id),
                $areaCount,
            ),
        )->values()->all();

        $candidates = ExamSession::query()
            ->where('status', ExamSession::STATUS_COMPLETED)
            ->whereNotIn('id', $sessions->pluck('exam_session_id'))
            ->with('room')
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get()
            ->map(fn (ExamSession $session): array => [
                'id' => $session->id,
                'code' => $session->code,
                'starts_at' => $session->starts_at?->format('Y-m-d H:i'),
                'room_name' => $session->room?->name ?? '—',
            ])
            ->values()
            ->all();

        $pageState = config('nexi.mode') === 'degraded' ? 'degraded' : ($sessionsData === [] ? 'empty' : 'ready');

        return Inertia::render('Assessment/Grading', [
            'page_state' => $pageState,
            'sessions' => $sessionsData,
            'candidate_exam_sessions' => $candidates,
            'aptitude_areas_count' => $areaCount,
            'degraded' => config('nexi.mode') === 'degraded',
        ]);
    }

    public function store(OpenGradingSessionRequest $request, OpenGradingSession $action): RedirectResponse
    {
        $examSession = ExamSession::query()->findOrFail($request->integer('exam_session_id'));

        try {
            $gradingSession = $action->handle($examSession, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['open' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return redirect()->route('grading.sessions.show', $gradingSession->id)
            ->with('status', $gradingSession->wasRecentlyCreated
                ? 'Grading session opened.'
                : 'Grading session already exists.');
    }

    public function show(Request $request, GradingSession $gradingSession): Response
    {
        $areaCount = AptitudeArea::query()->where('is_active', true)->count();

        $assignments = ExamAssignment::query()
            ->where('exam_session_id', $gradingSession->exam_session_id)
            ->active()
            ->with(['attendanceRecord', 'submissionRecord', 'admissionAttempt.identity'])
            ->get();

        $eligible = GradingRoster::filterEligible($assignments);

        $records = $gradingSession->scoreRecords()
            ->whereIn('admission_attempt_id', $eligible->pluck('admission_attempt_id'))
            ->get(['admission_attempt_id', 'aptitude_area_id']);

        $applicants = $eligible->map(function (ExamAssignment $assignment) use ($records, $areaCount): array {
            $identity = $assignment->admissionAttempt?->identity;
            $name = $identity
                ? trim(implode(' ', array_filter([
                    $identity->first_name,
                    $identity->middle_name,
                    $identity->last_name,
                    $identity->suffix,
                ])))
                : '—';

            $domainsComplete = $records
                ->where('admission_attempt_id', $assignment->admission_attempt_id)
                ->pluck('aptitude_area_id')
                ->unique()
                ->count();

            return [
                'attempt_id' => $assignment->admission_attempt_id,
                'reference_code' => $assignment->admissionAttempt?->reference_code ?? '—',
                'name' => $name !== '' ? $name : '—',
                'domains_complete' => $domainsComplete,
                'domains_total' => $areaCount,
                'scored' => $areaCount > 0 && $domainsComplete >= $areaCount,
            ];
        })->values()->all();

        $stats = $this->rosterStats($assignments);

        $pageState = config('nexi.mode') === 'degraded' ? 'degraded' : 'ready';

        return Inertia::render('Assessment/GradingSession', [
            'page_state' => $pageState,
            'degraded' => config('nexi.mode') === 'degraded',
            'session' => [
                'id' => $gradingSession->id,
                'exam_session_id' => $gradingSession->exam_session_id,
                'exam_code' => $gradingSession->examSession?->code ?? '—',
                'starts_at' => $gradingSession->examSession?->starts_at?->format('Y-m-d H:i'),
                'room_name' => $gradingSession->examSession?->room?->name ?? '—',
                'status' => $gradingSession->status,
                'version' => (int) $gradingSession->version,
                'opened_at' => $gradingSession->opened_at?->toIso8601String(),
                'finalized_at' => $gradingSession->finalized_at?->toIso8601String(),
                'finalized_by_name' => $gradingSession->finalizedBy?->name,
            ],
            'applicants' => $applicants,
            'roster_stats' => $stats,
            'progress_stats' => [
                'total' => $stats['present_and_submitted'],
                'scored' => collect($applicants)->where('scored', true)->count(),
                'pending' => $stats['present_and_submitted'] - collect($applicants)->where('scored', true)->count(),
            ],
            'aptitude_areas_count' => $areaCount,
            'can_finalize' => $gradingSession->status !== GradingSession::STATUS_FINALIZED
                && $stats['present_and_submitted'] > 0
                && collect($applicants)->where('scored', true)->count() === $stats['present_and_submitted'],
        ]);
    }
    public function omr(Request $request, GradingSession $gradingSession): Response
    {
        $batch = OmrCaptureBatch::query()
            ->where('grading_session_id', $gradingSession->id)
            ->whereIn('status', OmrCaptureBatch::ACTIVE_STATUSES)
            ->with([
                'templateVersion',
                'templateVersion.areas.aptitudeArea',
                'bookletVersion.questions.templateQuestion',
                'bookletVersion.questions.aptitudeArea',
                'sheets' => fn ($query) => $query->orderByDesc('created_at')->with('pages'),
            ])
            ->first();
        $template = $batch?->templateVersion;
        $identityRegions = $template?->identity_regions_json ?? [];
        $identityRegion = $identityRegions['name'] ?? $identityRegions['applicant_number'] ?? null;
        $templateItems = $batch?->bookletVersion?->questions
            ->sortBy('question_position')
            ->map(fn ($item): array => [
                'position' => (int) $item->question_position,
                'area_id' => (int) $item->aptitude_area_id,
                'area_code' => $item->aptitudeArea?->code ?? '—',
                'area_name' => $item->aptitudeArea?->name ?? 'Aptitude area',
                'question_no' => (int) ($item->templateQuestion?->question_no ?? $item->question_position),
                'correct_option_code' => (string) $item->correct_option_code,
                'region' => [
                    'x' => (float) ($item->templateQuestion?->region_x ?? 0),
                    'y' => (float) ($item->templateQuestion?->region_y ?? 0),
                    'w' => (float) ($item->templateQuestion?->region_w ?? 1),
                    'h' => (float) ($item->templateQuestion?->region_h ?? 1),
                ],
            ])->values()->all() ?? [];

        $roster = GradingRoster::queryFor($gradingSession)
            ->with('admissionAttempt.identity')
            ->get()
            ->map(function (ExamAssignment $assignment): array {
                $identity = $assignment->admissionAttempt?->identity;

                return [
                    'id' => $assignment->admission_attempt_id,
                    'reference_code' => $assignment->admissionAttempt?->reference_code ?? '—',
                    'name' => $identity
                        ? trim(implode(' ', array_filter([$identity->first_name, $identity->last_name])))
                        : '—',
                ];
            })
            ->values()
            ->all();

        $pairs = OmrTemplateBookletPair::query()
            ->with(['templateVersion.template', 'bookletVersion.booklet'])
            ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
            ->get()
            ->filter(fn (OmrTemplateBookletPair $pair): bool => $pair->isUsable())
            ->map(fn (OmrTemplateBookletPair $pair): array => [
                'template_version_id' => $pair->template_version_id,
                'booklet_version_id' => $pair->booklet_version_id,
                'label' => sprintf(
                    '%s v%s · %s v%s',
                    $pair->templateVersion?->template?->name ?? 'Template',
                    $pair->templateVersion?->version ?? '?',
                    $pair->bookletVersion?->booklet?->name ?? 'Booklet',
                    $pair->bookletVersion?->version ?? '?',
                ),
            ])->values()->all();

        return Inertia::render('Assessment/Omr/Capture', [
            'page_state' => $batch ? 'ready' : 'empty',
            'camera_enabled' => (bool) config('omr.camera_enabled', true),
            'provider' => [
                'name' => config('omr.provider.name', 'nexiam.fake'),
                'version' => config('omr.provider.version', '1'),
                'synthetic' => config('omr.provider.driver', 'fake') === 'fake',
            ],
            'available_pairs' => $pairs,
            'roster' => $roster,
            'session' => [
                'id' => $gradingSession->id,
                'exam_code' => $gradingSession->examSession?->code ?? '—',
                'room_name' => $gradingSession->examSession?->room?->name ?? '—',
                'status' => $gradingSession->status,
            ],
            'batch' => $batch ? [
                'id' => $batch->id,
                'status' => $batch->status,
                'version' => (int) $batch->version,
                'template' => [
                    'page_width' => (int) ($template?->page_width ?? 1),
                    'page_height' => (int) ($template?->page_height ?? 1),
                    'page_count' => (int) ($template?->pageCount() ?? 1),
                    'identity_region' => $identityRegion,
                    'items' => $templateItems,
                ],
                'sheets' => $batch->sheets->map(fn (OmrCaptureSheet $sheet): array => [
                    'id' => $sheet->id,
                    'status' => $sheet->status,
                    'capture_mode' => $sheet->capture_mode,
                    'client_capture_id' => $sheet->client_capture_id,
                    'expected_page_count' => (int) $sheet->expected_page_count,
                    'confidence' => $sheet->sheet_confidence,
                    'identity_confidence' => $sheet->detected_applicant_confidence,
                    'detected_applicant_number' => $sheet->detected_applicant_number,
                    'admission_attempt_id' => $sheet->admission_attempt_id,
                    'proposal' => $sheet->proposal_json,
                    'reviewed' => $sheet->reviewed_json,
                    'image_url' => route('grading.sessions.omr.sheets.image', [
                        'gradingSession' => $gradingSession,
                        'sheet' => $sheet,
                    ]),
                    'detected_birthdate' => $sheet->detected_birthdate,
                    'pages' => $sheet->pages->sortBy('page_no')->map(fn (OmrCaptureSheetPage $page): array => [
                        'page_no' => (int) $page->page_no,
                        'status' => $page->status,
                        'captured' => $page->asset_path !== null,
                        'image_url' => route('grading.sessions.omr.sheets.pages.image', [
                            'gradingSession' => $gradingSession,
                            'sheet' => $sheet,
                            'page' => (int) $page->page_no,
                        ]),
                        'detected_applicant_number' => is_array($page->proposal_json)
                            ? ($page->proposal_json['applicant_number_proposal'] ?? null)
                            : null,
                        'detected_birthdate' => is_array($page->proposal_json)
                            ? ($page->proposal_json['birthdate_proposal'] ?? null)
                            : null,
                    ])->values()->all(),
                    'created_at' => $sheet->created_at?->toIso8601String(),
                ])->values()->all(),
            ] : null,
        ]);
    }

    public function omrImage(
        GradingSession $gradingSession,
        int $sheet,
        \App\Support\SensitiveAssetStore $assetStore,
        int $page = 1,
    ): \Illuminate\Http\Response {
        $capture = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();

        if ((int) $capture->expected_page_count <= 1 && $capture->pages()->doesntExist()) {
            $assetPath = $capture->aligned_asset_path ?: $capture->asset_path;
            $assetHmac = $capture->aligned_asset_hmac ?: $capture->asset_hmac;
            $mime = $capture->aligned_mime ?: $capture->original_mime;
        } else {
            $pageRow = $capture->pages()->where('page_no', $page)->firstOrFail();
            $assetPath = $pageRow->aligned_asset_path ?: $pageRow->asset_path;
            $assetHmac = $pageRow->aligned_asset_hmac ?: $pageRow->asset_hmac;
            $mime = $pageRow->aligned_mime ?: $pageRow->original_mime;
        }

        try {
            $bytes = $assetStore->read((string) $assetPath, (string) $assetHmac);
        } catch (RuntimeException) {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type' => (string) $mime,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeOmrBatch(
        StoreOmrBatchRequest $request,
        GradingSession $gradingSession,
        CreateOmrCaptureBatch $action,
    ): RedirectResponse {
        try {
            $batch = $action->handle(
                $gradingSession,
                $request->user(),
                (int) $request->integer('template_version_id'),
                (int) $request->integer('booklet_version_id'),
                (string) $request->string('idempotency_key'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('OMR batch #%d is ready.', $batch->id));
    }

    public function storeOmrSheet(
        StoreOmrSheetRequest $request,
        GradingSession $gradingSession,
        UploadOmrCaptureSheet $upload,
    ): RedirectResponse {
        $batch = OmrCaptureBatch::query()
            ->where('grading_session_id', $gradingSession->id)
            ->whereIn('status', OmrCaptureBatch::ACTIVE_STATUSES)
            ->firstOrFail();

        try {
            $sheet = $upload->handle(
                $batch,
                (string) $request->file('image')->get(),
                (string) $request->file('image')->getMimeType(),
                (string) $request->string('capture_mode'),
                (string) $request->string('client_capture_id'),
                $request->user(),
                $request->integer('admission_attempt_id') ?: null,
                (int) ($request->integer('page_no') ?: 1),
            );
            $sheet->refresh();
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return redirect()->route('grading.sessions.omr', $gradingSession)->with('status', sprintf('Scan #%d is queued for analysis.', $sheet->id));
    }
    public function reviewOmrSheet(
        ReviewOmrSheetRequest $request,
        GradingSession $gradingSession,
        int $sheet,
        ReviewOmrCaptureSheet $action,
    ): RedirectResponse {
        $capture = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();

        try {
            $action->handle(
                $capture,
                $request->user(),
                $request->input('action') === 'abstain' ? (string) $request->input('abstain_reason') : null,
                $request->input('adjusted_scores', []),
                $request->integer('admission_attempt_id') ?: null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('Scan #%d review saved.', $capture->id));
    }

    public function confirmOmrBatch(
        Request $request,
        GradingSession $gradingSession,
        OmrCaptureBatch $batch,
        ConfirmOmrCaptureBatch $action,
    ): RedirectResponse {
        if ((int) $batch->grading_session_id !== (int) $gradingSession->id) {
            abort(404);
        }

        try {
            $action->handle($batch, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('OMR batch #%d confirmed.', $batch->id));
    }
    public function rejectOmrBatch(
        Request $request,
        GradingSession $gradingSession,
        OmrCaptureBatch $batch,
        RejectOmrCaptureBatch $action,
    ): RedirectResponse {
        if ((int) $batch->grading_session_id !== (int) $gradingSession->id) {
            abort(404);
        }

        try {
            $action->handle($batch, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('OMR batch #%d rejected.', $batch->id));
    }

    public function retryOmrSheet(
        Request $request,
        GradingSession $gradingSession,
        int $sheet,
        RetryOmrCaptureSheet $action,
    ): RedirectResponse {
        $capture = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();

        try {
            $action->handle($capture, $request->user(), (int) ($request->integer('page_no') ?: 1));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('Scan #%d queued for re-analysis.', $capture->id));
    }

    public function replaceOmrSheetPage(
        Request $request,
        GradingSession $gradingSession,
        int $sheet,
        int $page,
        ReplaceOmrSheetPage $action,
    ): RedirectResponse {
        $capture = OmrCaptureSheet::query()
            ->whereKey($sheet)
            ->whereHas('batch', fn ($query) => $query->where('grading_session_id', $gradingSession->id))
            ->firstOrFail();

        $file = $request->file('image');
        if ($file === null) {
            return back()->withErrors(['omr' => 'A replacement image is required.']);
        }

        try {
            $action->handle($capture, $page, (string) $file->get(), (string) $file->getMimeType(), $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr' => $exception->getMessage()]);
        }

        return back()->with('status', sprintf('Scan #%d page %d replaced.', $capture->id, $page));
    }

    public function finalize(Request $request, GradingSession $gradingSession, FinalizeGradingSession $action): RedirectResponse
    {
        try {
            $action->handle($gradingSession, $request->user(), (int) $request->integer('expected_version', $gradingSession->version));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['finalize' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Grading session finalized.');
    }

    /**
     * @param  Collection<int, ExamAssignment>  $assignments
     * @return array{assigned: int, present_and_submitted: int, absent: int, pending_attendance: int, no_submission: int}
     */
    private function rosterStats(Collection $assignments): array
    {
        $absent = 0;
        $pending = 0;
        $present = 0;
        $noSubmission = 0;

        foreach ($assignments as $assignment) {
            $outcome = $assignment->attendanceRecord?->outcome;

            if ($outcome === null) {
                $pending++;
            } elseif ($outcome === ExamAttendanceRecord::OUTCOME_ABSENT) {
                $absent++;
            } else {
                $present++;
                if ($assignment->submissionRecord === null) {
                    $noSubmission++;
                }
            }
        }

        return [
            'assigned' => $assignments->count(),
            'present_and_submitted' => $present - $noSubmission,
            'absent' => $absent,
            'pending_attendance' => $pending,
            'no_submission' => $noSubmission,
        ];
    }

    /**
     * @param  Collection<int, ExamAssignment>  $assignments
     * @param  Collection<int, ExamAssignment>  $absentAssignments
     * @param  Collection<int, ScoreRecord>  $records
     * @return array<string, mixed>
     */
    private function sessionRow(GradingSession $gradingSession, Collection $assignments, Collection $absentAssignments, Collection $records, int $areaCount): array
    {
        $scoredAttempts = $records
            ->groupBy('admission_attempt_id')
            ->filter(fn (Collection $byAttempt): bool => $byAttempt->pluck('aptitude_area_id')->unique()->count() >= $areaCount)
            ->count();

        return [
            'id' => $gradingSession->id,
            'exam_session_id' => $gradingSession->exam_session_id,
            'exam_code' => $gradingSession->examSession?->code ?? '—',
            'starts_at' => $gradingSession->examSession?->starts_at?->format('Y-m-d H:i'),
            'room_name' => $gradingSession->examSession?->room?->name ?? '—',
            'status' => $gradingSession->status,
            'opened_at' => $gradingSession->opened_at?->toIso8601String(),
            'finalized_at' => $gradingSession->finalized_at?->toIso8601String(),
            'applicants_total' => $assignments->count(),
            'applicants_scored' => $areaCount > 0 ? $scoredAttempts : 0,
            'applicants_absent' => $absentAssignments->count(),
        ];
    }
}

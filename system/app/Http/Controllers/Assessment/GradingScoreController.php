<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Actions\ClearScores;
use App\Assessment\Actions\RecordScores;
use App\Assessment\Support\GradingRoster;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\RecordScoresRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Foundation\AptitudeArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class GradingScoreController extends Controller
{
    public function show(Request $request, GradingSession $gradingSession, AdmissionAttempt $attempt): Response
    {
        $this->assertInRoster($gradingSession, $attempt);

        $domains = AptitudeArea::query()->where('is_active', true)->orderBy('id')->get();

        $existingScores = $gradingSession->scoreRecords()
            ->where('admission_attempt_id', $attempt->id)
            ->get()
            ->keyBy('aptitude_area_id');

        $identity = $attempt->identity;
        $name = $identity
            ? trim(implode(' ', array_filter([
                $identity->first_name,
                $identity->middle_name,
                $identity->last_name,
                $identity->suffix,
            ])))
            : '—';

        $finalized = $gradingSession->status === GradingSession::STATUS_FINALIZED;

        return Inertia::render('Assessment/GradingScoreInput', [
            'page_state' => $finalized ? 'locked' : 'ready',
            'degraded' => config('nexi.mode') === 'degraded',
            'session_id' => $gradingSession->id,
            'session_status' => $gradingSession->status,
            'finalized' => $finalized,
            'attempt' => [
                'id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'name' => $name !== '' ? $name : '—',
            ],
            'exam' => [
                'code' => $gradingSession->examSession?->code ?? '—',
                'starts_at' => $gradingSession->examSession?->starts_at?->format('Y-m-d H:i'),
                'room_name' => $gradingSession->examSession?->room?->name ?? '—',
            ],
            'domains' => $domains->map(function (AptitudeArea $area) use ($existingScores): array {
                $area->load('conversionRows');

                return [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                    'scoring_method' => $area->scoring_method,
                    'max_items' => $area->max_items,
                    'conversion_rows' => $area->conversionRows->map(fn ($row): array => [
                        'min_score' => (int) $row->min_score,
                        'max_score' => (int) $row->max_score,
                        'output_value' => $row->output_value,
                    ])->values()->all(),
                    'score' => $existingScores->get($area->id)?->score ?? null,
                    'converted_score' => $existingScores->get($area->id)?->converted_score ?? null,
                ];
            })->values()->all(),
        ]);
    }

    public function update(RecordScoresRequest $request, GradingSession $gradingSession, AdmissionAttempt $attempt, RecordScores $action): RedirectResponse
    {
        $this->assertInRoster($gradingSession, $attempt);

        try {
            $action->handle(
                $gradingSession,
                $attempt,
                $request->validated('scores'),
                $request->user(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scores' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return redirect()->route('grading.sessions.show', $gradingSession->id)
            ->with('status', 'Scores saved.');
    }

    public function destroy(Request $request, GradingSession $gradingSession, AdmissionAttempt $attempt, ClearScores $action): RedirectResponse
    {
        $this->assertInRoster($gradingSession, $attempt);

        try {
            $action->handle($gradingSession, $attempt, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scores' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return redirect()->route('grading.sessions.show', $gradingSession->id)
            ->with('status', 'Scores cleared.');
    }

    private function assertInRoster(GradingSession $gradingSession, AdmissionAttempt $attempt): void
    {
        if (! GradingRoster::includesAttempt($gradingSession, (int) $attempt->id)) {
            abort(SymfonyResponse::HTTP_NOT_FOUND, 'Attempt is not part of this grading session.');
        }
    }
}

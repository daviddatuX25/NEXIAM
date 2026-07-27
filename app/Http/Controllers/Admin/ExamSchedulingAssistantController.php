<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ExamSchedulingConversation;
use App\Models\ExamSession;
use App\Models\Room;
use App\Services\ExamSchedulingAssistantService;
use App\Services\ExamSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExamSchedulingAssistantController extends Controller
{
    public function __construct(
        private ExamSchedulingAssistantService $assistantService
    ) {}

    /**
     * POST chat — send message, get AI reply, optionally get structured schedule.
     * Generate schedule (request_structured) requires OPENROUTER_API_KEY; plain chat is allowed and will fail with a clear error if key is missing.
     */
    public function chat(Request $request): JsonResponse
    {
        $this->authorize('create', ExamSession::class);

        $request->validate(['message' => 'required|string|max:4000']);

        if (! config('services.openrouter.key')) {
            return response()->json([
                'message' => 'AI scheduling requires OPENROUTER_API_KEY in .env.',
            ], 403);
        }

        $requestStructured = (bool) $request->input('request_structured', false);

        $user = $request->user();
        $message = $request->input('message');

        $activeAcademicYear = AcademicYear::active();
        $applicantCount = Applicant::query()
            ->whereHas('application', fn ($q) => $q->where('status', 'accepted'))
            ->whereDoesntHave('examSessions', fn ($q) => $q->whereNotIn('status', [ExamSession::STATUS_CANCELLED]))
            ->when($activeAcademicYear, fn ($q) => $q->whereHas('application', fn ($aq) => $aq->where('academic_year_id', $activeAcademicYear->id)))
            ->count();

        Log::info('[AI-SCHEDULER-DEBUG] Unassigned applicants query', [
            'applicantCount' => $applicantCount,
            'activeAcademicYear' => $activeAcademicYear?->id,
        ]);

        $rooms = Room::query()
            ->where('is_active', true)
            ->orderBy('building')
            ->orderBy('name')
            ->get(['id', 'name', 'building', 'capacity'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name ?? '—',
                'capacity' => $r->capacity ?? 0,
            ])
            ->values()
            ->all();

        Log::info('[AI-SCHEDULER-DEBUG] Rooms being sent to AI', [
            'count' => count($rooms),
            'rooms' => $rooms,
        ]);

        $applicantSummary = Applicant::query()
            ->whereHas('application', fn ($q) => $q->where('status', 'accepted'))
            ->whereDoesntHave('examSessions', fn ($q) => $q->whereNotIn('status', [ExamSession::STATUS_CANCELLED]))
            ->when($activeAcademicYear, fn ($q) => $q->whereHas('application', fn ($aq) => $aq->where('academic_year_id', $activeAcademicYear->id)))
            ->orderBy('id')
            ->limit(100)
            ->get(['id'])
            ->map(fn ($a) => ['id' => $a->id])
            ->values()
            ->all();

        Log::info('[AI-SCHEDULER-DEBUG] Applicant summary being sent to AI', [
            'count' => count($applicantSummary),
            'ids' => collect($applicantSummary)->pluck('id')->toArray(),
        ]);

        $draftSessions = [];
        if ($activeAcademicYear) {
            $draftSessions = ExamSession::query()
                ->where('status', ExamSession::STATUS_DRAFT)
                ->forAcademicYear($activeAcademicYear)
                ->with('room:id,name,building,capacity')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'room_id' => $s->room_id,
                    'room' => $s->room ? [
                        'name' => $s->room->name,
                        'capacity' => $s->room->capacity,
                    ] : ['name' => '?', 'capacity' => 0],
                    'date' => $s->date?->format('Y-m-d'),
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'current_count' => $s->applicants()->count(),
                    'capacity' => $s->room?->capacity ?? 0,
                ])
                ->values()
                ->all();
        }

        $existingSessions = [];
        if ($activeAcademicYear) {
            $existingSessions = ExamSession::query()
                ->whereNotIn('status', [ExamSession::STATUS_DRAFT, ExamSession::STATUS_CANCELLED])
                ->forAcademicYear($activeAcademicYear)
                ->with('room:id,name,building,capacity')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'room_id' => $s->room_id,
                    'room' => $s->room ? [
                        'name' => $s->room->name,
                        'capacity' => $s->room->capacity,
                    ] : ['name' => '?', 'capacity' => 0],
                    'date' => $s->date?->format('Y-m-d'),
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'current_count' => $s->applicants()->count(),
                    'capacity' => $s->room?->capacity ?? 0,
                ])
                ->values()
                ->all();
        }

        Log::info('[AI-SCHEDULER-DEBUG] Existing sessions being sent to AI', [
            'count' => count($existingSessions),
            'sessions' => $existingSessions,
        ]);

        $conversation = ExamSchedulingConversation::query()
            ->latestForUser($user->id)
            ->first();

        $existingMessages = $conversation?->messages ?? [];
        $newMessages = array_merge($existingMessages, [
            ['role' => 'user', 'content' => $message],
        ]);

        // Scrub hallucinated claims from conversation history before sending to AI
        $scrubbedMessages = $this->scrubContradictingMessages($newMessages, $applicantCount);

        try {
            $result = $this->assistantService->chat($scrubbedMessages, [
                'applicant_count' => $applicantCount,
                'rooms' => $rooms,
                'applicant_summary' => $applicantSummary,
                'draft_sessions' => $draftSessions,
                'existing_sessions' => $existingSessions,
            ], $requestStructured);

            $reply = $result['reply'];

            if ($reply === '') {
                return response()->json([
                    'message' => 'The AI returned an empty response. Please try again.',
                ], 502);
            }

            $lastMessage = ['role' => 'assistant', 'content' => $reply];
            if (isset($result['structured_schedule'])) {
                $lastMessage['schedule'] = $this->normalizeStructuredSchedule($result['structured_schedule']);
            }
            $newMessages[] = $lastMessage;

            if (! $conversation) {
                $conversation = ExamSchedulingConversation::create([
                    'user_id' => $user->id,
                    'messages' => $newMessages,
                ]);
            } else {
                $conversation->update(['messages' => $newMessages]);
            }

            $response = ['reply' => $reply];
            if (isset($result['structured_schedule'])) {
                $response['structured_schedule'] = $this->normalizeStructuredSchedule($result['structured_schedule']);
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::warning('ExamSchedulingAssistant chat error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $code = str_contains($e->getMessage(), 'rate limit') ? 429 : 502;

            return response()->json([
                'message' => $e->getMessage() ?: 'Request failed. Please try again.',
            ], $code);
        }
    }

    /**
     * DELETE conversation — delete the current user's conversation to start fresh.
     */
    public function clearConversation(Request $request): JsonResponse
    {
        $this->authorize('create', ExamSession::class);

        $deleted = ExamSchedulingConversation::query()
            ->where('user_id', $request->user()->id)
            ->delete();

        Log::info('[AI-SCHEDULER-DEBUG] Conversation cleared', [
            'user_id' => $request->user()->id,
            'deleted_count' => $deleted,
        ]);

        return response()->json(['message' => 'Conversation reset.']);
    }

    /**
     * POST apply-schedule — validate and apply structured schedule (create sessions, assign applicants).
     */
    public function applySchedule(Request $request): JsonResponse
    {
        $this->authorize('create', ExamSession::class);

        $request->validate([
            'sessions' => 'required|array',
            'sessions.*.action' => 'nullable|string|in:create,assign,edit,delete',
            'sessions.*.applicant_ids' => 'nullable|array',
            'sessions.*.applicant_ids.*' => 'integer|exists:applicants,id',
            'sessions.*.exam_session_id' => 'nullable|integer|exists:exam_sessions,id',
            'sessions.*.room_id' => 'nullable|integer|exists:rooms,id',
            'sessions.*.date' => 'nullable|string|date_format:Y-m-d',
            'sessions.*.start_time' => 'nullable|string',
            'sessions.*.end_time' => 'nullable|string',
        ]);

        $payload = $request->input('sessions');

        try {
            DB::transaction(function () use ($payload, $request) {
                $service = app(ExamSchedulingService::class);
                foreach ($payload as $item) {
                    $action = $item['action'] ?? $this->inferAction($item);
                    match ($action) {
                        'create' => $service->createDraftSession($item, $request->user()),
                        'edit' => $service->updateDraftSession(
                            ExamSession::findOrFail($item['exam_session_id']), $item
                        ),
                        'assign' => $service->assignApplicants(
                            ExamSession::findOrFail($item['exam_session_id']),
                            $item['applicant_ids'] ?? []
                        ),
                        'delete' => $service->deleteDraftSession(
                            ExamSession::findOrFail($item['exam_session_id'])
                        ),
                        default => throw new \RuntimeException("Unknown action: {$action}"),
                    };
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('ExamSchedulingAssistant applySchedule error', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to apply schedule. Please try again.'], 500);
        }

        $request->session()->flash('success', 'Schedule applied successfully.');

        return response()->json([
            'message' => 'Schedule applied successfully.',
            'redirect_url' => route('admin.exam-scheduling.index'),
        ]);
    }

    /**
     * Infer action based on item payload.
     */
    private function inferAction(array $item): string
    {
        $sessionId = $item['exam_session_id'] ?? null;
        if (! $sessionId) {
            return 'create';
        }

        return (isset($item['room_id']) || isset($item['date']) || isset($item['start_time']) || isset($item['end_time'])) ? 'edit' : 'assign';
    }

    /**
     * Coerce AI-returned structured schedule to the canonical field set.
     * Handles common hallucinated aliases so the frontend always gets clean data.
     */
    private function normalizeStructuredSchedule(array $schedule): array
    {
        $sessions = $schedule['sessions'] ?? [];
        $normalised = [];

        foreach ($sessions as $s) {
            $normalised[] = [
                'action' => $s['action'] ?? null,
                'exam_session_id' => $s['exam_session_id'] ?? $s['session_id'] ?? null,
                'room_id' => $s['room_id'] ?? $s['roomId'] ?? $s['room']['id'] ?? null,
                'date' => $s['date'] ?? $s['session_date'] ?? $s['exam_date'] ?? null,
                'start_time' => $s['start_time'] ?? $s['startTime'] ?? $s['time_start'] ?? null,
                'end_time' => $s['end_time'] ?? $s['endTime'] ?? $s['time_end'] ?? null,
                'applicant_ids' => $s['applicant_ids'] ?? $s['applicantIds'] ?? $s['applicants'] ?? [],
            ];
        }

        return ['sessions' => $normalised];
    }

    /**
     * Scrub assistant messages that contain factual claims contradicting current data.
     * Narrow pattern matching only — catches the known hallucination pattern.
     */
    private function scrubContradictingMessages(array $messages, int $applicantCount): array
    {
        $hallucinationPatterns = [
            '/\bno\s+(unassigned|unscheduled)\s+applicants?\b/i',
            '/\b0\s+applicants?\b/i',
            '/\bzero\s+applicants?\b/i',
            '/\bthere\s+are\s+no\s+applicants?\b/i',
            '/\bno\s+applicants?\s+(left|remaining|to\s+schedule|waiting)\b/i',
        ];

        $placeholder = '[The AI previously made a statement here that contradicted the current data and it has been corrected.]';

        return array_map(function ($msg) use ($hallucinationPatterns, $applicantCount, $placeholder) {
            if ($msg['role'] !== 'assistant') {
                return $msg;
            }

            $content = $msg['content'] ?? '';

            // If the message is just the placeholder, leave it
            if ($content === $placeholder) {
                return $msg;
            }

            // If applicantCount is 0 and the message claims zero — that's accurate, leave it
            if ($applicantCount === 0) {
                return $msg;
            }

            // Check against hallucination patterns
            foreach ($hallucinationPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    Log::info('[AI-SCHEDULER-DEBUG] Scrubbing hallucinated message', [
                        'pattern' => $pattern,
                        'content' => $content,
                    ]);

                    return ['role' => 'assistant', 'content' => $placeholder];
                }
            }

            return $msg;
        }, $messages);
    }
}

<?php

namespace App\Ai\Skills;

use App\Ai\Grants\TaskGrantService;
use App\Ai\Orchestration\RunEventAppender;
use App\Models\Ai\NexiRun;
use App\Models\Ai\NexiTaskGrant;
use App\Models\Ai\NexiTurn;
use App\Models\User;
use RuntimeException;

/**
 * Deterministic Assistant turn planner for schedule.exam_session@1 (OpenSpec 8).
 *
 * Model/gateway may later recommend tools; this planner is the fake-provider stand-in
 * that still must pass SkillEngine allowlist + Kernel.
 */
final class ExamSessionAssistantTurnService
{
    public function __construct(
        private readonly TaskGrantService $grants,
        private readonly SkillEngine $skills,
        private readonly SkillToolInvoker $invoker,
        private readonly RunEventAppender $events,
    ) {}

    /**
     * @return array{
     *     content: string,
     *     proposal: array<string, mixed>|null,
     *     tool_events: list<array<string, mixed>>
     * }
     */
    public function handle(NexiRun $run, NexiTurn $turn, User $actor): array
    {
        $grant = $this->findActiveExamGrant($actor);
        if ($grant === null) {
            $this->events->append($run, 'skill.missing_grant', [
                'skill_key' => ExamSessionSchedulingSkill::KEY.'@'.ExamSessionSchedulingSkill::VERSION,
            ]);

            return [
                'content' => 'Start the exam scheduling skill first (Assistant mode on an exam sessions page), then ask me to open reservation, assign, or publish. The page buttons still work.',
                'proposal' => null,
                'tool_events' => [],
            ];
        }

        $plan = $this->plan($turn->input_text, $grant);
        $this->events->append($run, 'skill.plan', [
            'grant_id' => $grant->id,
            'capability_id' => $plan['capability_id'],
            'reason' => $plan['reason'],
            'input' => $plan['input'],
        ]);

        if ($plan['capability_id'] === null) {
            return [
                'content' => $plan['message'],
                'proposal' => null,
                'tool_events' => [],
            ];
        }

        try {
            $this->skills->assertToolAllowed($grant->fresh() ?? $grant, $plan['capability_id']);
        } catch (RuntimeException $e) {
            $this->events->append($run, 'skill.rejected', [
                'capability_id' => $plan['capability_id'],
                'error' => $e->getMessage(),
            ]);

            return [
                'content' => 'That tool is not legal for the current skill step ('.$e->getMessage().'). Load the session first, or confirm/cancel a pending proposal.',
                'proposal' => null,
                'tool_events' => [],
            ];
        }

        $this->events->append($run, 'skill.tool', [
            'capability_id' => $plan['capability_id'],
            'input' => $plan['input'],
            'phase' => 'invoke',
        ]);

        try {
            $result = $this->invoker->invoke(
                actor: $actor,
                grantId: $grant->id,
                capabilityId: $plan['capability_id'],
                input: $plan['input'],
                conversationId: $run->conversation_id,
            );
        } catch (RuntimeException $e) {
            $this->events->append($run, 'skill.result', [
                'capability_id' => $plan['capability_id'],
                'ok' => false,
                'error' => $e->getMessage(),
            ]);

            return [
                'content' => 'I could not run '.$plan['capability_id'].' ('.$e->getMessage().'). Use the ordinary page control if needed.',
                'proposal' => null,
                'tool_events' => [],
            ];
        }

        $this->events->append($run, 'skill.result', [
            'capability_id' => $plan['capability_id'],
            'ok' => true,
            'mode' => $result['result']['mode'] ?? 'read',
            'proposal_id' => $result['proposal']['id'] ?? null,
        ]);

        if ($result['proposal'] !== null) {
            $this->events->append($run, 'skill.proposal', [
                'proposal_id' => $result['proposal']['id'],
                'capability_id' => $result['proposal']['capability_id'],
                'summary' => $result['proposal']['summary'] ?? [],
                'expires_at' => $result['proposal']['expires_at'] ?? null,
            ]);

            $cap = (string) $result['proposal']['capability_id'];
            $impact = is_array($result['proposal']['summary']['impact'] ?? null)
                ? $result['proposal']['summary']['impact']
                : (is_array($result['proposal']['summary'] ?? null) ? $result['proposal']['summary'] : []);
            $code = (string) ($impact['code'] ?? ($impact['session_id'] ?? ''));

            return [
                'content' => "Proposed {$cap} for session {$code}. Review the confirm gate — same Action as the page button. I will not apply it until you confirm.",
                'proposal' => $result['proposal'],
                'tool_events' => [],
            ];
        }

        return [
            'content' => $this->readSummary($plan['capability_id'], $result['result']),
            'proposal' => null,
            'tool_events' => [],
        ];
    }

    private function findActiveExamGrant(User $actor): ?NexiTaskGrant
    {
        $grants = NexiTaskGrant::query()
            ->where('actor_id', (string) $actor->id)
            ->where('skill_key', ExamSessionSchedulingSkill::KEY)
            ->where('skill_version', ExamSessionSchedulingSkill::VERSION)
            ->where('status', NexiTaskGrant::STATUS_ACTIVE)
            ->latest('created_at')
            ->get();

        foreach ($grants as $grant) {
            $active = $this->grants->findActive($grant->id);
            if ($active !== null) {
                return $active;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     capability_id: string|null,
     *     input: array<string, mixed>,
     *     reason: string,
     *     message: string
     * }
     */
    private function plan(string $prompt, NexiTaskGrant $grant): array
    {
        $needle = mb_strtolower(trim($prompt));
        $sessionId = (int) ($grant->scope_bounds['exam_session_id'] ?? 0);
        if (preg_match('/\bsession\s*(?:id|#)?\s*(\d+)\b/i', $prompt, $m) === 1) {
            $sessionId = (int) $m[1];
        }

        if ($sessionId < 1 && (
            str_contains($needle, 'open reservation')
            || str_contains($needle, 'assign')
            || str_contains($needle, 'publish')
            || str_contains($needle, 'show session')
        )) {
            return [
                'capability_id' => null,
                'input' => [],
                'reason' => 'session_id_required',
                'message' => 'I need an exam session id (open the session Show page and start the skill there, or say “session 12”).',
            ];
        }

        if (str_contains($needle, 'list session') || str_contains($needle, 'list exam')) {
            return [
                'capability_id' => 'exams.sessions.list@1',
                'input' => [],
                'reason' => 'list',
                'message' => '',
            ];
        }

        if (str_contains($needle, 'show') || str_contains($needle, 'status') || str_contains($needle, 'roster')) {
            return [
                'capability_id' => 'exams.session.show@1',
                'input' => ['session_id' => $sessionId],
                'reason' => 'show',
                'message' => '',
            ];
        }

        if (str_contains($needle, 'open reservation') || str_contains($needle, 'open booking') || str_contains($needle, 'open for reservation')) {
            try {
                $version = $this->sessionVersion($sessionId);
            } catch (RuntimeException $e) {
                return [
                    'capability_id' => null,
                    'input' => [],
                    'reason' => $e->getMessage(),
                    'message' => 'I could not find that exam session. Open it on the page or give a valid session id.',
                ];
            }

            return [
                'capability_id' => 'exams.session.reservation.open@1',
                'input' => [
                    'session_id' => $sessionId,
                    'expected_version' => $version,
                ],
                'reason' => 'open_reservation',
                'message' => '',
            ];
        }

        if (str_contains($needle, 'assign')) {
            $attemptId = null;
            if (preg_match('/\battempt\s*(?:id|#)?\s*(\d+)\b/i', $prompt, $m) === 1) {
                $attemptId = (int) $m[1];
            } elseif (preg_match('/\b(\d{2,})\b/', $prompt, $m) === 1 && (int) $m[1] !== $sessionId) {
                $attemptId = (int) $m[1];
            }
            if ($attemptId === null) {
                return [
                    'capability_id' => null,
                    'input' => [],
                    'reason' => 'attempt_id_required',
                    'message' => 'To assign, include the admission attempt id (e.g. “assign attempt 15 to this session”).',
                ];
            }

            return [
                'capability_id' => 'exams.session.assign@1',
                'input' => [
                    'session_id' => $sessionId,
                    'admission_attempt_id' => $attemptId,
                ],
                'reason' => 'assign',
                'message' => '',
            ];
        }

        if (str_contains($needle, 'publish')) {
            try {
                $version = $this->sessionVersion($sessionId);
            } catch (RuntimeException $e) {
                return [
                    'capability_id' => null,
                    'input' => [],
                    'reason' => $e->getMessage(),
                    'message' => 'I could not find that exam session. Open it on the page or give a valid session id.',
                ];
            }

            return [
                'capability_id' => 'exams.session.publish@1',
                'input' => [
                    'session_id' => $sessionId,
                    'expected_version' => $version,
                ],
                'reason' => 'publish',
                'message' => '',
            ];
        }

        return [
            'capability_id' => $sessionId > 0 ? 'exams.session.show@1' : 'exams.sessions.list@1',
            'input' => $sessionId > 0 ? ['session_id' => $sessionId] : [],
            'reason' => 'default_read',
            'message' => '',
        ];
    }

    private function sessionVersion(int $sessionId): int
    {
        $session = \App\Models\Examination\ExamSession::query()->find($sessionId);
        if ($session === null) {
            throw new RuntimeException('session_not_found');
        }

        return (int) $session->version;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function readSummary(string $capabilityId, array $result): string
    {
        if ($capabilityId === 'exams.sessions.list@1') {
            $count = is_array($result['sessions'] ?? null) ? count($result['sessions']) : (int) ($result['count'] ?? 0);

            return "I loaded {$count} exam session(s). Ask to show a session, open reservation, assign, or publish — confirm still required for writes.";
        }

        if ($capabilityId === 'exams.session.show@1') {
            $code = (string) ($result['code'] ?? ($result['session']['code'] ?? 'session'));
            $status = (string) ($result['status'] ?? ($result['session']['status'] ?? 'unknown'));

            return "Session {$code} is “{$status}”. You can ask me to open reservation, assign an attempt, or publish — I will propose first.";
        }

        return 'Tool completed. Use Confirm if a proposal gate appears; otherwise continue or use the page controls.';
    }
}

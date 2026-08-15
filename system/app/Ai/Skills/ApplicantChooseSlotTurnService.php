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
 * Deterministic Assistant turn planner for applicant.choose_slot@1 (Slice 9).
 *
 * Model/gateway may later recommend tools; this planner is the fake-provider
 * stand-in that still must pass SkillEngine allowlist + Kernel.
 */
final class ApplicantChooseSlotTurnService
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
        $grant = $this->findActiveApplicantGrant($actor);
        if ($grant === null) {
            $this->events->append($run, 'skill.missing_grant', [
                'skill_key' => ApplicantChooseSlotSkill::KEY.'@'.ApplicantChooseSlotSkill::VERSION,
            ]);

            return [
                'content' => 'Start the slot skill on the schedule page first, then ask me to list your slots or propose one. The calendar buttons still work.',
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
                'content' => 'That step is not allowed right now ('.$e->getMessage().'). Load your slots first, or confirm/cancel a pending proposal.',
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
                'content' => 'I could not run '.$plan['capability_id'].' ('.$e->getMessage().'). Use the calendar page if needed.',
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
            $code = (string) ($impact['code'] ?? ($impact['exam_session_id'] ?? ''));

            return [
                'content' => "Proposed {$cap} for exam slot {$code}. Review the confirm gate — same Action as the page button. I will not reserve until you confirm.",
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

    private function findActiveApplicantGrant(User $actor): ?NexiTaskGrant
    {
        $grants = NexiTaskGrant::query()
            ->where('actor_id', (string) $actor->id)
            ->where('skill_key', ApplicantChooseSlotSkill::KEY)
            ->where('skill_version', ApplicantChooseSlotSkill::VERSION)
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
        $attemptId = (int) ($grant->scope_bounds['admission_attempt_id'] ?? 0);
        if (preg_match('/\battempt\s*(?:id|#)?\s*(\d+)\b/i', $prompt, $m) === 1) {
            $attemptId = (int) $m[1];
        }

        if (str_contains($needle, 'reserve') || str_contains($needle, 'book')
            || str_contains($needle, 'pick') || str_contains($needle, 'choose')) {
            $sessionId = 0;
            if (preg_match('/\bsession\s*(?:id|#)?\s*(\d+)\b/i', $prompt, $m) === 1) {
                $sessionId = (int) $m[1];
            } elseif (preg_match('/\bslot\s*(?:id|#)?\s*(\d+)\b/i', $prompt, $m) === 1) {
                $sessionId = (int) $m[1];
            }

            if ($sessionId < 1 || $attemptId < 1) {
                return [
                    'capability_id' => null,
                    'input' => [],
                    'reason' => 'slot_and_attempt_required',
                    'message' => 'To propose a reservation, tell me the exam slot id and your attempt id (e.g. “reserve slot 7 for attempt 12”).',
                ];
            }

            return [
                'capability_id' => 'exams.slot.reserve@1',
                'input' => [
                    'exam_session_id' => $sessionId,
                    'admission_attempt_id' => $attemptId,
                ],
                'reason' => 'reserve',
                'message' => '',
            ];
        }

        if (str_contains($needle, 'list') || str_contains($needle, 'slot')
            || str_contains($needle, 'available') || str_contains($needle, 'what are my')) {
            return [
                'capability_id' => 'exams.slots.list@1',
                'input' => [],
                'reason' => 'list_slots',
                'message' => '',
            ];
        }

        if (str_contains($needle, 'assigned') || str_contains($needle, 'my seat')
            || str_contains($needle, 'my schedule') || str_contains($needle, 'where')) {
            return [
                'capability_id' => 'exams.slots.list@1',
                'input' => [],
                'reason' => 'seat_status',
                'message' => '',
            ];
        }

        return [
            'capability_id' => 'exams.slots.list@1',
            'input' => [],
            'reason' => 'default_read',
            'message' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function readSummary(string $capabilityId, array $result): string
    {
        if ($capabilityId === 'exams.slots.list@1') {
            $mode = (string) ($result['mode'] ?? 'open');

            if ($mode === 'assigned') {
                $session = is_array($result['assignment']['session'] ?? null) ? $result['assignment']['session'] : [];
                $code = (string) ($session['code'] ?? 'session');
                $published = ! empty($session['published']);

                return $published
                    ? "You are assigned to exam {$code} — your seat is confirmed."
                    : "You are assigned to exam {$code} — staff will publish the schedule when ready.";
            }

            $slots = is_array($result['slots'] ?? null) ? $result['slots'] : [];
            if ($slots === []) {
                return 'There are no open reservation slots right now. Check back after Guidance opens a session, or use the calendar page.';
            }

            $open = collect($slots)->filter(fn ($s) => ! ($s['full'] ?? false))->count();

            return "I found ".count($slots)." open slot(s), {$open} with space. Say “reserve slot <id> for attempt <id>” to propose one — I will not reserve until you confirm.";
        }

        return 'Tool completed. Confirm the proposal gate if one appears, or use the calendar page.';
    }
}

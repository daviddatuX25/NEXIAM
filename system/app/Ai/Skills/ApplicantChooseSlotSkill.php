<?php

namespace App\Ai\Skills;

/**
 * Code-owned applicant slot skill (Slice 9 — applicant.choose_slot@1).
 *
 * Allowlist + engine recipe over Kernel capabilities that wrap the applicant
 * reservation core. OWN scope; no office capability; propose → confirm → reserve.
 */
final class ApplicantChooseSlotSkill
{
    public const KEY = 'applicant.choose_slot';

    public const VERSION = 1;

    public const CURSOR_STARTED = 'started';

    public const CURSOR_SLOTS_LOADED = 'slots_loaded';

    public const CURSOR_AWAITING_CONFIRM = 'awaiting_confirm';

    public const CURSOR_DONE = 'done';

    /**
     * @return list<string>
     */
    public static function allowedCapabilityIds(): array
    {
        return [
            'exams.slots.list@1',
            'exams.slot.reserve@1',
        ];
    }

    public static function guidanceDigest(): string
    {
        return <<<'TXT'
Applicant slot skill (guidance only — server allowlist is authority):
1) Read exams.slots.list to see your own open exam slots.
2) Propose one slot (exams.slot.reserve) — this only proposes, never reserves.
3) Wait for the human to confirm on the confirm gate.
4) Never invent session ids or attempt ids; only use Kernel read results.
5) Prefer the calendar page buttons if Assistant is degraded.
TXT;
    }

    /**
     * Cursor → capability ids legal to invoke next (engine gate before Kernel).
     *
     * @return array<string, list<string>>
     */
    public static function legalTransitions(): array
    {
        return [
            self::CURSOR_STARTED => ['exams.slots.list@1'],
            self::CURSOR_SLOTS_LOADED => ['exams.slots.list@1', 'exams.slot.reserve@1'],
            self::CURSOR_AWAITING_CONFIRM => [], // only HTTP confirm endpoint
            self::CURSOR_DONE => ['exams.slots.list@1'],
        ];
    }
}

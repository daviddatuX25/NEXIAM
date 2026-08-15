<?php

namespace App\Ai\Skills;

/**
 * Code-owned exam scheduling skill (OpenSpec 8).
 *
 * Allowlist + engine recipe over Kernel capabilities that wrap Examination Actions.
 * Optional guidance digest is for the model only — never grants tools (founder lock).
 */
final class ExamSessionSchedulingSkill
{
    public const KEY = 'schedule.exam_session';

    public const VERSION = 1;

    public const CURSOR_STARTED = 'started';

    public const CURSOR_SESSION_LOADED = 'session_loaded';

    public const CURSOR_AWAITING_CONFIRM = 'awaiting_confirm';

    public const CURSOR_DONE = 'done';

    /**
     * @return list<string>
     */
    public static function allowedCapabilityIds(): array
    {
        return [
            'exams.sessions.list@1',
            'exams.session.show@1',
            'exams.session.reservation.open@1',
            'exams.session.assign@1',
            'exams.session.publish@1',
        ];
    }

    public static function guidanceDigest(): string
    {
        return <<<'TXT'
Exam scheduling skill (guidance only — server allowlist is authority):
1) Read exams.sessions.list / exams.session.show for the target session.
2) Propose open reservation, assign one eligible attempt, or publish — then wait for human confirm.
3) Never invent session ids or attempt ids; only use Kernel read results.
4) Prefer ordinary page buttons if Assistant is degraded.
TXT;
    }

    /**
     * Cursor → capability ids legal to invoke next (engine gate before Kernel).
     *
     * @return array<string, list<string>>
     */
    public static function legalTransitions(): array
    {
        $reads = ['exams.sessions.list@1', 'exams.session.show@1'];
        $mutators = [
            'exams.session.reservation.open@1',
            'exams.session.assign@1',
            'exams.session.publish@1',
        ];

        return [
            self::CURSOR_STARTED => $reads,
            self::CURSOR_SESSION_LOADED => array_merge($reads, $mutators),
            self::CURSOR_AWAITING_CONFIRM => [], // only HTTP confirm endpoint
            self::CURSOR_DONE => $reads,
        ];
    }
}

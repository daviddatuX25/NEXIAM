<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Examination\Actions\OpenExamSessionReservation;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Propose (default) or execute open-reservation — same Action as the page.
 *
 * @phpstan-type Input array{session_id: int, expected_version: int, execute?: bool, reason?: string}
 */
final class ExamSessionReservationOpenHandler
{
    public function __construct(private readonly OpenExamSessionReservation $action) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        $sessionId = (int) ($input['session_id'] ?? 0);
        $expectedVersion = (int) ($input['expected_version'] ?? 0);
        $execute = (bool) ($input['execute'] ?? false);
        $session = ExamSession::query()->find($sessionId);
        if ($session === null) {
            throw new RuntimeException('session_not_found');
        }

        if ((int) $session->version !== $expectedVersion) {
            throw new RuntimeException('stale_session_version');
        }

        $impact = [
            'session_id' => $session->id,
            'code' => $session->code,
            'expected_version' => $expectedVersion,
            'action' => 'open_reservation',
            'reason' => isset($input['reason']) && is_string($input['reason']) ? $input['reason'] : null,
        ];

        if (! $execute) {
            return [
                'mode' => 'propose',
                'capability_id' => 'exams.session.reservation.open@1',
                'impact' => $impact,
                'evidence' => [
                    'outcome' => 'pass',
                    'checks' => ['version_match', 'not_terminal_precheck'],
                ],
            ];
        }

        $actor = Auth::user();
        if (! $actor instanceof User) {
            throw new RuntimeException('unauthenticated');
        }

        $updated = $this->action->handle($session, $actor, $expectedVersion, $impact['reason']);

        return [
            'mode' => 'execute',
            'session_id' => $updated->id,
            'booking_mode' => $updated->booking_mode,
            'version' => $updated->version,
            'status' => $updated->status,
        ];
    }
}

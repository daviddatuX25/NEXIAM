<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Examination\Actions\PublishExamSession;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class ExamSessionPublishHandler
{
    public function __construct(private readonly PublishExamSession $action) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        $sessionId = (int) ($input['session_id'] ?? 0);
        $expectedVersion = (int) ($input['expected_version'] ?? 0);
        $execute = (bool) ($input['execute'] ?? false);
        $session = ExamSession::query()
            ->withCount(['assignments as assigned_count' => fn ($q) => $q->active()])
            ->find($sessionId);
        if ($session === null) {
            throw new RuntimeException('session_not_found');
        }

        if ((int) $session->version !== $expectedVersion) {
            throw new RuntimeException('stale_session_version');
        }

        if ($session->status !== ExamSession::STATUS_DRAFT) {
            throw new RuntimeException('only_draft_may_publish');
        }

        if ((int) ($session->assigned_count ?? 0) < 1) {
            throw new RuntimeException('assign_required_before_publish');
        }

        $impact = [
            'session_id' => $session->id,
            'code' => $session->code,
            'expected_version' => $expectedVersion,
            'action' => 'publish',
            'assigned_count' => (int) $session->assigned_count,
        ];

        if (! $execute) {
            return [
                'mode' => 'propose',
                'capability_id' => 'exams.session.publish@1',
                'impact' => $impact,
                'evidence' => [
                    'outcome' => 'pass',
                    'checks' => ['draft', 'has_assignments', 'version_match'],
                ],
            ];
        }

        $actor = Auth::user();
        if (! $actor instanceof User) {
            throw new RuntimeException('unauthenticated');
        }

        $published = $this->action->handle($session, $actor, $expectedVersion);

        return [
            'mode' => 'execute',
            'session_id' => $published->id,
            'status' => $published->status,
            'version' => $published->version,
        ];
    }
}

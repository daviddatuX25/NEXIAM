<?php

namespace App\Examination\Queries;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Support\ExamSessionPresenter;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

class ExamBookletDistributionQuery
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ExamSessionPresenter $presenter,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @return array{snapshot_id: int, revision: int, generated_at: string, sets_summary: array<string, int>, roster: list<array{applicant_name: string, applicant_reference: string|null, set_code: string}>}
     */
    public function handle(ExamSession $session, User $actor, ?int $expectedRevision = null): array
    {
        $session = ExamSession::query()->forWorkspace()->with(['academicCycle', 'currentLogisticsPlan'])->findOrFail($session->id);
        $actor = User::query()->findOrFail($actor->id);
        $institutionId = (int) Institution::current()->id;
        $scope = $actor->actorScope();
        if ($scope->institutionId !== $institutionId
            || (int) $session->academicCycle?->institution_id !== $institutionId) {
            throw new AuthorizationException('Exam session is outside the current workspace.');
        }

        $canManage = $this->authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, [
            'institution_id' => $institutionId,
            'office' => 'guidance',
            'cycle' => $session->academicCycle->code,
        ])->allowed;

        if (! $canManage) {
            throw new AuthorizationException('Exam session is outside the current workspace.');
        }

        $allowedStatuses = [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS, ExamSession::STATUS_COMPLETED];
        if (! in_array($session->status, $allowedStatuses, true)) {
            throw new RuntimeException('Booklet distribution list is unavailable outside a published exam.');
        }

        if ($session->currentLogisticsPlan !== null) {
            throw new RuntimeException('Booklet distribution requires an exact material snapshot; physical seating is independent of form selection.');
        }
        $snapshot = $session->currentMaterialSnapshot;
        if ($snapshot === null || $snapshot->seating_mode === ExamSession::SEATING_MODE_SIMPLE_CAPACITY) {
            throw new RuntimeException('Booklet distribution list is unavailable for single-set exams.');
        }
        if ($expectedRevision !== null && $expectedRevision !== (int) $snapshot->revision) {
            throw new RuntimeException('Exam material snapshot revision has changed.');
        }

        $seats = $snapshot->seats()->with([
            'bookletSet:id,set_code',
            'activeAllocation.assignment' => fn ($query) => $query->active()->with('admissionAttempt.identity'),
        ])->get();
        $requiredSetCodes = $snapshot->required_set_codes ?? [];
        if (! in_array($snapshot->seating_mode, ExamSession::SEATING_MODES, true)) {
            $requiredSetCodes = array_values(array_unique([
                ...$requiredSetCodes,
                ...$seats->pluck('bookletSet.set_code')->filter()->all(),
            ]));
        }
        if (count($requiredSetCodes) <= 1) {
            throw new RuntimeException('Booklet distribution list is unavailable for single-set exams.');
        }

        $counts = array_fill_keys($requiredSetCodes, 0);
        $roster = [];
        foreach ($seats as $seat) {
            $assignment = $seat->activeAllocation?->assignment;
            if ($assignment === null || (int) $assignment->exam_session_id !== (int) $session->id) {
                continue;
            }
            $code = $seat->bookletSet?->set_code;
            if ($code === null || ! array_key_exists($code, $counts)) {
                throw new RuntimeException('Published booklet distribution is incomplete.');
            }
            $counts[$code]++;
            $roster[] = [
                'applicant_name' => $this->presenter->attemptName($assignment->admissionAttempt),
                'applicant_reference' => $assignment->admissionAttempt?->reference_code,
                'set_code' => $code,
            ];
        }
        usort($roster, fn (array $left, array $right): int => [$left['applicant_name'], $left['applicant_reference']] <=> [$right['applicant_name'], $right['applicant_reference']]);

        if (count($roster) !== $session->assignments()->active()->count()) {
            throw new RuntimeException('Published booklet distribution is incomplete.');
        }
        $currentSnapshot = $session->currentMaterialSnapshot()->first();
        if ($currentSnapshot === null || (int) $currentSnapshot->id !== (int) $snapshot->id) {
            throw new RuntimeException('Exam material snapshot revision has changed.');
        }
        $this->recordAuditEvent->handle('exam.distribution_list_viewed', [
            'institution_id' => $institutionId,
            'academic_cycle_id' => $session->academic_cycle_id,
            'exam_session_id' => $session->id,
            'snapshot_id' => $snapshot->id,
            'revision' => $snapshot->revision,
        ], (string) $actor->id);

        return [
            'snapshot_id' => (int) $snapshot->id,
            'revision' => (int) $snapshot->revision,
            'generated_at' => now()->toIso8601String(),
            'sets_summary' => $counts,
            'roster' => $roster,
        ];
    }
}

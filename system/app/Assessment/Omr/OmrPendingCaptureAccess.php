<?php

namespace App\Assessment\Omr;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrPendingCapture;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class OmrPendingCaptureAccess
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /**
     * @return array{institution_id:int,academic_cycle_id:int,cycle:string}
     */
    public function actorScope(User $actor): array
    {
        $scope = $actor->actorScope();
        if ($scope->institutionId === null || $scope->activeCycleCode === null || $scope->office !== 'guidance') {
            throw new AuthorizationException('OMR scanner scope is not available.');
        }

        $cycle = AcademicCycle::query()
            ->whereKey($this->cycleIdForActor($scope->institutionId, $scope->activeCycleCode))
            ->where('institution_id', $scope->institutionId)
            ->where('code', $scope->activeCycleCode)
            ->first();
        if ($cycle === null) {
            throw new AuthorizationException('OMR scanner scope is not available.');
        }

        $decision = $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, [
            'office' => 'guidance',
            'institution_id' => (int) $cycle->institution_id,
            'cycle' => (string) $cycle->code,
        ]);
        if (! $decision->allowed) {
            throw new AuthorizationException('OMR scanner scope is not available.');
        }

        return [
            'institution_id' => (int) $cycle->institution_id,
            'academic_cycle_id' => (int) $cycle->id,
            'cycle' => (string) $cycle->code,
        ];
    }

    public function assertPending(User $actor, OmrPendingCapture $pending, ?GradingSession $session = null): void
    {
        $scope = $this->actorScope($actor);
        $ownerId = $pending->getAttribute('uploaded_by_user_id');
        if (! is_numeric($ownerId) || (int) $ownerId < 1 || (int) $ownerId !== (int) $actor->getAuthIdentifier()) {
            throw new AuthorizationException('OMR pending capture is not available.');
        }
        if ((int) ($pending->institution_id ?? 0) !== $scope['institution_id']
            || (int) ($pending->academic_cycle_id ?? 0) !== $scope['academic_cycle_id']) {
            throw new AuthorizationException('OMR pending capture is outside the active scope.');
        }

        if ($session !== null) {
            $session->loadMissing('examSession.academicCycle');
            $examSession = $session->examSession;
            $cycle = $examSession?->academicCycle;
            if ($cycle === null
                || (int) $cycle->id !== $scope['academic_cycle_id']
                || (int) $cycle->institution_id !== $scope['institution_id']) {
                throw new AuthorizationException('OMR grading session is outside the active scope.');
            }
        }
    }

    private function cycleIdForActor(int $institutionId, string $cycleCode): int
    {
        return (int) AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('code', $cycleCode)
            ->value('id');
    }
}

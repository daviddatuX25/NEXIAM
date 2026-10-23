<?php

namespace App\Application\Capabilities\Handlers\Assessment;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Assessment\Queries\UnscoredExamineesQuery;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class UnscoredExamineesQueryHandler implements ContextualReadToolHandler
{
    public function __construct(private readonly UnscoredExamineesQuery $query) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        unset($input, $actor);

        return ['page_state' => 'unavailable', 'reason' => 'context_required'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        if (! $this->actorMatchesContext($actor, $context)) {
            return ['page_state' => 'denied', 'reason' => 'actor_context_mismatch'];
        }

        if ($context->actorType !== 'staff' || ! $actor->staffProfile()->exists()) {
            return ['page_state' => 'denied', 'reason' => 'staff_actor_required'];
        }

        $institutionId = $actor->actorScope()->institutionId;
        if ($institutionId === null || $context->institutionId === null) {
            return ['page_state' => 'unavailable', 'reason' => 'institution_context_unavailable'];
        }

        if ($institutionId !== $context->institutionId) {
            return ['page_state' => 'denied', 'reason' => 'institution_scope_mismatch'];
        }

        $cycleId = isset($input['cycle_id']) && is_int($input['cycle_id'])
            ? $input['cycle_id']
            : null;

        return $this->query->handle($institutionId, $cycleId);
    }

    private function actorMatchesContext(User $actor, InvocationContext $context): bool
    {
        $actorType = $actor->staffProfile()->exists()
            ? 'staff'
            : ($actor->applicantAccount()->exists() ? 'applicant' : null);

        return (string) $actor->getKey() === $context->actorId
            && $actorType !== null
            && $actorType === $context->actorType;
    }
}

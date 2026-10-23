<?php

namespace App\Application\Capabilities\Handlers;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\ResultSheet\Queries\ResultSheetTemplatesListQuery;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ResultSheetTemplatesListHandler implements ContextualReadToolHandler
{
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
        unset($input);

        if (! $this->actorMatchesContext($actor, $context)) {
            return ['page_state' => 'denied', 'reason' => 'actor_context_mismatch'];
        }

        if ($context->actorType !== 'staff' || ! $actor->staffProfile()->exists()) {
            return ['page_state' => 'denied', 'reason' => 'staff_actor_required'];
        }

        try {
            $currentInstitutionId = (int) Institution::current()->getKey();
        } catch (ModelNotFoundException) {
            return ['page_state' => 'unavailable', 'reason' => 'institution_unavailable'];
        }

        $institutionId = $actor->actorScope()->institutionId;

        if ($institutionId === null || $context->institutionId === null
            || $institutionId !== $context->institutionId
            || $context->institutionId !== $currentInstitutionId) {
            return ['page_state' => 'denied', 'reason' => 'institution_context_mismatch'];
        }

        return app(ResultSheetTemplatesListQuery::class)->forXiamSafe();
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

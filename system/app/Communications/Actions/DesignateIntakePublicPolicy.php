<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DesignateIntakePublicPolicy
{
    public function __construct(private readonly PublicPolicyCommandSupport $commands) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, PublicPolicy $policy, array $data): PublicPolicy
    {
        $validated = Validator::make($data, [
            'request_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'expected_intake_policy_id' => ['present', 'nullable', 'integer', 'min:1'],
            'kind' => ['prohibited'],
            'is_designated_intake' => ['prohibited'],
        ])->validate();
        $expectedSelectionId = isset($validated['expected_intake_policy_id']) ? (int) $validated['expected_intake_policy_id'] : null;

        return $this->commands->run(
            $actor,
            (string) $validated['request_id'],
            'designate_intake',
            (int) $policy->getKey(),
            (int) $validated['expected_revision'],
            '',
            ['expected_intake_policy_id' => $expectedSelectionId],
            function (User $_freshActor, ?PublicPolicy $locked, ?int $_targetId, string $_sourceKey) use ($expectedSelectionId): PublicPolicy {
                if ($locked === null) {
                    throw ValidationException::withMessages(['policy' => 'The selected policy is unavailable.']);
                }

                $institutionId = (int) $locked->institution_id;
                $current = PublicPolicy::currentIntakeSelection($institutionId, lockForUpdate: true);
                $currentId = $current === null ? null : (int) $current->getKey();
                if ($currentId !== $expectedSelectionId) {
                    throw ValidationException::withMessages(['expected_intake_policy_id' => "The institution's Application Privacy Policy selection changed. Reload before selecting a policy."]);
                }
                if ($current !== null && (int) $current->getKey() === (int) $locked->getKey()) {
                    throw ValidationException::withMessages(['policy' => 'This policy is already designated as the Application Privacy Policy.']);
                }
                if (! str_starts_with($locked->publisher_key, 'office:') || $locked->kind !== 'privacy' || $locked->program_id !== null || $locked->status !== 'published') {
                    throw ValidationException::withMessages(['policy' => 'Only a published general privacy policy can be designated as the Application Privacy Policy.']);
                }

                $revision = PublicPolicyRevision::query()
                    ->whereKey($locked->published_revision_id)
                    ->lockForUpdate()
                    ->first();
                if ($revision === null
                    || (int) $revision->public_policy_id !== (int) $locked->getKey()
                    || (int) $revision->institution_id !== $institutionId
                    || $revision->publisher_key !== $locked->publisher_key
                    || $revision->publisher_label !== $locked->publisher_label
                    || $revision->program_id !== null
                    || $revision->state !== 'published'
                    || $revision->kind !== 'privacy') {
                    throw ValidationException::withMessages(['policy' => 'The policy has no valid published privacy snapshot. Publish a current privacy revision first.']);
                }

                if ($current !== null) {
                    $current->forceFill(['is_designated_intake' => false])->save();
                }
                $locked->forceFill(['is_designated_intake' => true])->save();

                return $locked->refresh();
            },
            requiresPublish: true,
        );
    }
}

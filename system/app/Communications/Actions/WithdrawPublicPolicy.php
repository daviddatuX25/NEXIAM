<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Ai\Knowledge\InvalidateDependentFaqsOnPolicyChange;
use App\Communications\PublicPolicySourceAuthorizer;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class WithdrawPublicPolicy
{
    public function __construct(
        private readonly PublicPolicyCommandSupport $commands,
        private readonly PublicPolicySourceAuthorizer $sources,
        private readonly InvalidateDependentFaqsOnPolicyChange $invalidateDependentFaqs,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, PublicPolicy $policy, array $data): PublicPolicy
    {
        $validated = Validator::make($data, [
            'request_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
            'kind' => ['prohibited'],
            'is_designated_intake' => ['prohibited'],
        ])->validate();
        $reason = trim($validated['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Provide a reason for withdrawing this policy.']);
        }

        return $this->commands->run($actor, (string) $validated['request_id'], 'withdraw', (int) $policy->getKey(), (int) $validated['expected_revision'], '', ['reason' => $reason],
            function (User $freshActor, ?PublicPolicy $locked, ?int $_targetId, string $_sourceKey) use ($reason): PublicPolicy {
                if ($locked === null || $locked->status !== 'published') {
                    throw ValidationException::withMessages(['status' => 'Only a published policy can be withdrawn.']);
                }
                $this->sources->assertCanTarget($freshActor, $locked->publisher_key, $locked->program_id === null ? null : (int) $locked->program_id);
                $revision = $locked->revision + 1;
                $locked->forceFill([
                    'revision' => $revision, 'status' => 'withdrawn', 'withdrawn_by' => $freshActor->getKey(),
                    'withdrawn_at' => now(), 'withdrawal_reason' => $reason,
                ])->save();
                PublicPolicyRevision::query()->create([
                    'public_policy_id' => $locked->getKey(), 'revision' => $revision,
                    'institution_id' => $locked->institution_id, 'publisher_key' => $locked->publisher_key,
                    'publisher_label' => $locked->publisher_label, 'program_id' => $locked->program_id,
                    'title' => $locked->title, 'content' => $locked->content, 'kind' => $locked->kind, 'state' => 'withdrawn',
                    'actor_id' => $freshActor->getKey(), 'actor_name' => $freshActor->name,
                    'reason' => $reason, 'created_at' => now(),
                ]);
                $this->invalidateDependentFaqs->handle($locked);

                return $locked->refresh();
            }, requiresPublish: true);
    }
}

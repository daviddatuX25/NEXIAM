<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Communications\PublicPolicySourceAuthorizer;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SavePublicPolicy
{
    public function __construct(private readonly PublicPolicyCommandSupport $commands, private readonly PublicPolicySourceAuthorizer $sources) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): PublicPolicy
    {
        $creating = ! isset($data['policy_id']);
        $validated = Validator::make($data, [
            'policy_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'publisher_key' => [$creating ? 'required' : 'prohibited', 'string', 'max:100'],
            'program_id' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:1'],
            'kind' => ['sometimes', 'required', 'in:policy,privacy'],
            'is_designated_intake' => ['prohibited'],
            'request_id' => ['required', 'uuid'],
            'expected_revision' => [$creating ? 'prohibited' : 'required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string', 'max:10000'],
        ])->validate();
        $title = trim($validated['title']);
        $content = trim($validated['content']);
        if (mb_strlen($title) < 1) {
            throw ValidationException::withMessages(['title' => 'Enter a policy title.']);
        }
        if (mb_strlen($content) < 1) {
            throw ValidationException::withMessages(['content' => 'Enter policy content.']);
        }

        $policyId = isset($validated['policy_id']) ? (int) $validated['policy_id'] : null;
        $expectedRevision = isset($validated['expected_revision']) ? (int) $validated['expected_revision'] : null;
        $publisherKey = $policyId === null ? (string) $validated['publisher_key'] : '';
        $targetId = isset($validated['program_id']) ? (int) $validated['program_id'] : null;
        $payload = ['title' => $title, 'content' => $content, 'kind' => $validated['kind'] ?? null];
        if ($creating) {
            $payload['program_id'] = $targetId;
            $payload['kind'] ??= 'policy';
        }

        return $this->commands->run($actor, (string) $validated['request_id'], 'save', $policyId, $expectedRevision, $publisherKey, $payload,
            function (User $freshActor, ?PublicPolicy $policy, ?int $resolvedTarget, string $sourceKey) use ($title, $content, $validated): PublicPolicy {
                if ($policy !== null && $policy->status === 'withdrawn') {
                    throw ValidationException::withMessages(['status' => 'Withdrawn policies are read-only. Create a new policy instead.']);
                }
                $effectiveKind = $validated['kind'] ?? $policy?->kind ?? 'policy';
                if ($effectiveKind === 'privacy' && ($resolvedTarget !== null || ! str_starts_with($sourceKey, 'office:'))) {
                    throw ValidationException::withMessages(['kind' => 'Privacy policies must be general documents issued by an authorized institution office.']);
                }

                if ($policy === null) {
                    $source = $this->sources->resolve($freshActor, $sourceKey);
                    $label = $source !== null && $source['program_code'] !== null
                        ? (string) Program::query()->where('institution_id', $freshActor->actorScope()->institutionId)->where('code', $source['program_code'])->value('name')
                        : $this->sources->officeLabel((string) ($source['office'] ?? ''));
                    $policy = PublicPolicy::query()->create([
                        'institution_id' => $freshActor->actorScope()->institutionId,
                        'publisher_key' => $sourceKey,
                        'publisher_label' => $label,
                        'program_id' => $resolvedTarget,
                        'kind' => $effectiveKind,
                        'title' => $title,
                        'content' => $content,
                        'revision' => 1,
                        'status' => 'draft',
                        'created_by' => $freshActor->getKey(),
                    ]);
                } else {
                    $requestedKind = $effectiveKind;
                    if ($requestedKind !== $policy->kind) {
                        $hasConsentReferences = AdmissionAttempt::query()->where('public_policy_id', $policy->getKey())->exists();
                        if ($policy->published_revision_id !== null || $hasConsentReferences) {
                            throw ValidationException::withMessages(['kind' => 'A published or referenced policy cannot change kind. Create a new policy instead.']);
                        }
                    }

                    $policy->forceFill([
                        'kind' => $requestedKind,
                        'title' => $title,
                        'content' => $content,
                        'revision' => $policy->revision + 1,
                    ])->save();
                    $policy->refresh();
                }

                $snapshotState = $policy->published_revision_id !== null ? 'draft' : $policy->status;
                $this->recordRevision($policy, $freshActor, $snapshotState);

                return $policy;
            });
    }

    private function recordRevision(PublicPolicy $policy, User $actor, string $state): void
    {
        PublicPolicyRevision::query()->create([
            'public_policy_id' => $policy->getKey(),
            'revision' => $policy->revision,
            'institution_id' => $policy->institution_id,
            'publisher_key' => $policy->publisher_key,
            'publisher_label' => $policy->publisher_label,
            'program_id' => $policy->program_id,
            'title' => $policy->title,
            'content' => $policy->content,
            'kind' => $policy->kind,
            'state' => $state,
            'actor_id' => $actor->getKey(),
            'actor_name' => $actor->name,
            'created_at' => now(),
        ]);
    }
}

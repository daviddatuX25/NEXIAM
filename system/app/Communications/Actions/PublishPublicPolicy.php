<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Ai\Knowledge\InvalidateDependentFaqsOnPolicyChange;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PublishPublicPolicy
{
    public function __construct(
        private readonly PublicPolicyCommandSupport $commands,
        private readonly InvalidateDependentFaqsOnPolicyChange $invalidateDependentFaqs,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, PublicPolicy $policy, array $data): PublicPolicy
    {
        $validated = Validator::make($data, [
            'request_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'kind' => ['prohibited'],
            'is_designated_intake' => ['prohibited'],
        ])->validate();

        return $this->commands->run($actor, (string) $validated['request_id'], 'publish', (int) $policy->getKey(), (int) $validated['expected_revision'], '', [],
            function (User $freshActor, ?PublicPolicy $locked, ?int $_targetId, string $_sourceKey): PublicPolicy {
                if ($locked === null || $locked->status === 'withdrawn') {
                    throw ValidationException::withMessages(['status' => 'Withdrawn policies cannot be republished.']);
                }
                $currentPublishedRevision = $locked->published_revision_id === null
                    ? null
                    : PublicPolicyRevision::query()->whereKey($locked->published_revision_id)
                        ->where('public_policy_id', $locked->getKey())
                        ->where('state', 'published')
                        ->first();
                $hasPendingDraft = $currentPublishedRevision === null
                    ? $locked->published_revision_id === null
                    : (int) $locked->revision > (int) $currentPublishedRevision->revision;
                $needsPrivacyKindEvidence = $locked->kind === 'privacy'
                    && $currentPublishedRevision !== null
                    && $currentPublishedRevision->kind === null;
                if (! $hasPendingDraft && ! $needsPrivacyKindEvidence) {
                    throw ValidationException::withMessages(['status' => 'There is no saved draft or missing privacy-kind evidence to publish.']);
                }
                $revision = $locked->revision + 1;
                $locked->forceFill([
                    'revision' => $revision, 'status' => 'published',
                    'published_by' => $freshActor->getKey(), 'published_at' => now(),
                ])->save();
                $snapshot = PublicPolicyRevision::query()->create([
                    'public_policy_id' => $locked->getKey(), 'revision' => $revision,
                    'institution_id' => $locked->institution_id, 'publisher_key' => $locked->publisher_key,
                    'publisher_label' => $locked->publisher_label, 'program_id' => $locked->program_id,
                    'title' => $locked->title, 'content' => $locked->content, 'kind' => $locked->kind, 'state' => 'published',
                    'actor_id' => $freshActor->getKey(), 'actor_name' => $freshActor->name,
                    'created_at' => now(),
                ]);
                $locked->forceFill(['published_revision_id' => $snapshot->getKey()])->save();
                $this->invalidateDependentFaqs->handle($locked);

                return $locked->refresh();
            }, requiresPublish: true);
    }
}

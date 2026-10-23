<?php

namespace App\Application\Capabilities\Handlers;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Ai\Knowledge\EvidenceBundle;
use App\Ai\Knowledge\GroundingRouter;
use App\Ai\Policy\InputMinimizer;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class KnowledgeSearchReadHandler implements ContextualReadToolHandler
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        unset($input, $actor);

        return ['outcome' => 'unavailable', 'reason' => 'context_required', 'citations' => []];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        if ((string) $actor->getKey() !== $context->actorId
            || $context->actorType !== 'staff'
            || ! $actor->staffProfile()->exists()) {
            return ['page_state' => 'denied', 'reason' => 'actor_context_mismatch'];
        }

        $institutionId = $actor->actorScope()->institutionId;
        if ($institutionId === null || $context->institutionId === null) {
            return ['outcome' => 'unavailable', 'reason' => 'institution_context_unavailable', 'citations' => []];
        }

        if ($institutionId !== $context->institutionId) {
            return ['page_state' => 'denied', 'reason' => 'institution_context_mismatch'];
        }

        $query = $input['query'] ?? null;
        if (! is_string($query) || trim($query) === '') {
            return ['page_state' => 'denied', 'reason' => 'query_invalid'];
        }

        $sourceClass = $input['source_class'] ?? EvidenceBundle::SOURCE_INSTITUTION_POLICY;
        if (! is_string($sourceClass) || ! in_array($sourceClass, [
            EvidenceBundle::SOURCE_PUBLIC_FAQ,
            EvidenceBundle::SOURCE_INSTITUTION_POLICY,
        ], true)) {
            return ['page_state' => 'denied', 'reason' => 'source_class_invalid'];
        }

        $groundingContext = new InvocationContext(
            actorId: $context->actorId,
            actorType: $context->actorType,
            institutionId: $context->institutionId,
            surfaceProfile: $context->surfaceProfile,
            mode: $context->mode,
            contextEpoch: $context->contextEpoch,
            purpose: 'policy_inquiry',
            correlationId: $context->correlationId,
            browserSessionId: $context->browserSessionId,
            routeName: $context->routeName,
            executionId: $context->executionId,
            canonicalDigest: $context->canonicalDigest,
            grantId: $context->grantId,
            conversationId: $context->conversationId,
        );
        $minimizer = app(InputMinimizer::class);
        $query = $minimizer->minimize(trim($query));
        if ($query === '') {
            return ['page_state' => 'denied', 'reason' => 'query_invalid'];
        }

        $bundle = app(GroundingRouter::class)->resolve($query, $groundingContext, $sourceClass);
        if (! in_array($sourceClass, $bundle->sourceClasses, true)) {
            return ['outcome' => 'unavailable', 'reason' => 'source_unavailable', 'citations' => []];
        }

        $sanitize = static fn (string $text): string => str_replace(
            ['<', '>'],
            ['&lt;', '&gt;'],
            $minimizer->minimize($text),
        );

        if ($bundle->citations === []) {
            return ['outcome' => 'no_support', 'citations' => []];
        }

        return [
            'outcome' => 'support',
            'hit_count' => count($bundle->citations),
            'citations' => array_map(static fn (array $citation): array => [
                'citation_id' => (string) $citation['citation_id'],
                'source_class' => (string) $citation['source_class'],
                'version_id' => (string) $citation['version_id'],
                'source_id' => (string) $citation['source_id'],
                'title' => $sanitize((string) $citation['title']),
                'excerpt' => $sanitize((string) $citation['excerpt']),
                'score' => (float) $citation['score'],
                'locator' => $citation['locator'] ?? null,
                'as_of' => $citation['as_of'] ?? null,
            ], $bundle->citations),
        ];
    }
}

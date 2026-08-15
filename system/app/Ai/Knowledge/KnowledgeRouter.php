<?php

namespace App\Ai\Knowledge;

use App\Ai\Contracts\KnowledgeIndexPort;
use Illuminate\Support\Str;

/**
 * Provider-bound policy retrieval entry (AD-21). Auth filters applied before chunks leave the port.
 */
final class KnowledgeRouter
{
    public function __construct(private readonly KnowledgeIndexPort $index) {}

    /**
     * @param  array{actor_type: string, institution_id?: int|null, audiences?: list<string>, purpose?: string}  $trustedContext
     */
    public function resolvePolicyInquiry(string $query, array $trustedContext): KnowledgeRouterResult
    {
        $decisionId = (string) Str::uuid();
        $actorType = (string) ($trustedContext['actor_type'] ?? '');
        $audiences = $trustedContext['audiences'] ?? $this->defaultAudiencesFor($actorType);
        $purpose = (string) ($trustedContext['purpose'] ?? 'policy_inquiry');

        $hits = $this->index->retrieve(trim($query), 5, [
            'audiences' => $audiences,
            'institution_id' => $trustedContext['institution_id'] ?? null,
            'purpose' => $purpose,
        ]);

        if ($hits === []) {
            return new KnowledgeRouterResult(
                decisionId: $decisionId,
                outcome: 'no_support',
                hits: [],
                reason: 'no_support',
            );
        }

        return new KnowledgeRouterResult(
            decisionId: $decisionId,
            outcome: 'support',
            hits: $hits,
            reason: null,
        );
    }

    /**
     * @return list<string>
     */
    private function defaultAudiencesFor(string $actorType): array
    {
        return match ($actorType) {
            'applicant' => ['staff_and_applicant_inquiry', 'applicant_inquiry'],
            default => ['staff_and_applicant_inquiry', 'staff_only'],
        };
    }
}

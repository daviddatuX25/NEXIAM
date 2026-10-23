<?php

namespace App\Ai\Knowledge;

use App\Ai\Applicant\ApplicantOwnDataProjector;
use App\Ai\Context\InvocationContext;
use App\Ai\Contracts\KnowledgeIndexPort;
use App\Models\User;
use Illuminate\Support\Str;

final class GroundingRouter
{
    /** @var list<string> */
    public const INDEXED_DOCUMENT_CLASSES = [
        EvidenceBundle::SOURCE_PUBLIC_FAQ,
        EvidenceBundle::SOURCE_INSTITUTION_POLICY,
    ];

    /**
     * Structured institutional and operational source classes require code-owned
     * GroundingSourceDefinition contracts and authorized, capability- and
     * resource-scoped domain Queries before they may emit citations. Until those
     * Queries are supplied under scoped authority, these classes fail closed and
     * yield no grounding support.
     *
     * @var list<string>
     */
    public const UNBACKED_STRUCTURED_SOURCE_CLASSES = [
        EvidenceBundle::SOURCE_INSTITUTION_REQUIREMENT,
        EvidenceBundle::SOURCE_ADMISSION_MODEL_FACT,
        EvidenceBundle::SOURCE_SCORING_EVALUATION_FACT,
        EvidenceBundle::SOURCE_EXAMINATION_FACT,
        EvidenceBundle::SOURCE_STAFF_OPERATIONAL_FACT,
    ];

    private readonly GroundingSourceRegistry $registry;

    public function __construct(
        private readonly KnowledgeIndexPort $index,
        private readonly ApplicantOwnDataProjector $ownData,
        private readonly ApplicantPublicFaqRetrievalAdapter $applicantFaqAdapter,
        ?GroundingSourceRegistry $registry = null,
    ) {
        $this->registry = $registry ?? new GroundingSourceRegistry;
    }

    public function resolve(string $query, InvocationContext $context, ?string $sourceClassHint = null): EvidenceBundle
    {
        $query = trim($query);
        $decisionId = (string) Str::uuid();
        $sourceClasses = $this->sourceClassesFor($context);
        $documentOnlyHint = $sourceClassHint !== null;

        if ($documentOnlyHint) {
            if (! in_array($sourceClassHint, self::INDEXED_DOCUMENT_CLASSES, true)) {
                $sourceClasses = [];
            } else {
                $definition = $this->registry->get($sourceClassHint);
                $sourceClasses = in_array($sourceClassHint, $sourceClasses, true)
                    && $definition !== null
                    && $definition->isSupported()
                    ? [$sourceClassHint]
                    : [];
            }
        }

        $indexScope = $this->staffIndexFilters($context);
        $citations = [];
        $seen = [];

        foreach ($sourceClasses as $sourceClass) {
            $def = $this->registry->get($sourceClass);
            if ($def === null || ! $def->isSupported()) {
                continue;
            }

            if ($indexScope === null || ! $def->isIndexed()) {
                continue;
            }
            $filters = $indexScope;
            $filters['source_classes'] = [$sourceClass];
            foreach ($this->index->retrieve($query, 2, $filters) as $hit) {
                $versionId = (string) ($hit['version_id'] ?? '');
                $sourceId = (string) ($hit['source_id'] ?? '');
                $hitSourceClass = (string) ($hit['source_class'] ?? '');
                $audience = (string) ($hit['audience'] ?? '');
                $classification = (string) ($hit['classification'] ?? '');
                $owningOffice = (string) ($hit['owning_office'] ?? '');
                $hitInstitutionId = $hit['institution_id'] ?? null;
                $visibilityIsValid = match ($audience) {
                    'staff_and_applicant_inquiry' => $classification === 'public_institutional',
                    'staff_only' => $classification === 'office_internal'
                        && $owningOffice === $indexScope['owning_office'],
                    'cross_office_staff' => $classification === 'office_internal'
                        && in_array($owningOffice, ['registrar', 'guidance'], true)
                        && ($hit['cross_office_approved'] ?? false) === true,
                    default => false,
                };

                if ($versionId === '' || $sourceId === '' || $hitSourceClass !== $sourceClass
                    || ! is_int($hitInstitutionId) || $hitInstitutionId !== $context->institutionId
                    || ! in_array($audience, $indexScope['audiences'], true)
                    || ! in_array($owningOffice, ['registrar', 'guidance'], true)
                    || ! $visibilityIsValid) {
                    continue;
                }

                $key = $sourceClass.':'.$versionId.':'.$sourceId;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $excerpt = trim((string) ($hit['excerpt'] ?? ''));
                if ($excerpt === '') {
                    continue;
                }

                $citations[] = [
                    'citation_id' => 'cite_'.substr(hash('sha256', $key), 0, 24),
                    'source_class' => $sourceClass,
                    'version_id' => $versionId,
                    'source_id' => $sourceId,
                    'title' => (string) ($hit['source_title'] ?? $hit['source_key'] ?? $sourceId),
                    'excerpt' => $excerpt,
                    'score' => max(0.0, min(1.0, (float) ($hit['score'] ?? 0.0))),
                    'locator' => isset($hit['source_key']) ? (string) $hit['source_key'] : null,
                    'as_of' => now()->toIso8601String(),
                ];
                if (count($citations) >= 12) {
                    break 2;
                }
            }
        }
        if ($context->actorType === 'applicant' && in_array(EvidenceBundle::SOURCE_PUBLIC_FAQ, $sourceClasses, true)) {
            array_push($citations, ...$this->applicantFaqAdapter->retrieve($query, $context));
        }

        $projections = [];
        if (! $documentOnlyHint && $context->actorType === 'applicant' && in_array(EvidenceBundle::SOURCE_ACTOR_OWNED_RECORD, $sourceClasses, true)) {
            $actor = User::query()->find($context->actorId);
            if ($actor instanceof User) {
                $projection = $this->ownData->forUser($actor);
                if ($this->ownData->relevantTo($query, $projection)) {
                    $projections['actor_owned_record'] = $projection;
                }
            }
        }

        return new EvidenceBundle(
            decisionId: $decisionId,
            actorId: $context->actorId,
            contextEpoch: $context->contextEpoch,
            sourceClasses: $sourceClasses,
            citations: $citations,
            projections: $projections,
            reason: $citations === [] && $projections === [] ? 'no_support' : null,
        );
    }

    /** @return list<string> */
    private function sourceClassesFor(InvocationContext $context): array
    {
        $classes = EvidenceBundle::SOURCE_CLASSES;
        if ($context->actorType !== 'applicant') {
            $classes = array_values(array_diff($classes, [EvidenceBundle::SOURCE_ACTOR_OWNED_RECORD]));
        }

        return $classes;
    }

    /**
     * @return array{
     *     actor_id: string,
     *     actor_type: 'staff',
     *     institution_id: int,
     *     owning_office: 'registrar'|'guidance',
     *     audiences: list<string>,
     *     classifications: list<string>,
     *     purpose: string
     * }|null
     */
    private function staffIndexFilters(InvocationContext $context): ?array
    {
        if ($context->actorType !== 'staff' || $context->actorId === ''
            || $context->institutionId === null || $context->institutionId < 1) {
            return null;
        }

        $actor = User::query()
            ->with('staffProfile')
            ->whereKey($context->actorId)
            ->first();
        $profile = $actor?->staffProfile;

        if ($profile === null
            || ! $profile->isActive()
            || (int) $profile->institution_id !== $context->institutionId
            || ! in_array($profile->office, KnowledgeSourceAccess::OFFICES, true)) {
            return null;
        }

        return [
            'actor_id' => $context->actorId,
            'actor_type' => 'staff',
            'institution_id' => $context->institutionId,
            'owning_office' => $profile->office,
            'audiences' => ['staff_and_applicant_inquiry', 'staff_only', 'cross_office_staff'],
            'classifications' => ['public_institutional', 'office_internal'],
            'purpose' => $context->purpose,
        ];
    }
}

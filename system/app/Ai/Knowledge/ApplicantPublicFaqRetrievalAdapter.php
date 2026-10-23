<?php

declare(strict_types=1);

namespace App\Ai\Knowledge;

use App\Ai\Context\InvocationContext;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;

final class ApplicantPublicFaqRetrievalAdapter
{
    public function __construct(private readonly FaqPolicyDependencyValidator $validator) {}

    /** @return list<array<string, mixed>> */
    public function retrieve(string $query, InvocationContext $context, int $limit = 2): array
    {
        $institutionId = (int) $context->institutionId;
        if ($context->actorType !== 'applicant' || $context->institutionId === null || $institutionId < 1 || $limit < 1) {
            return [];
        }

        $sources = KnowledgeSource::query()
            ->where('institution_id', $institutionId)
            ->where('source_class', KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ)
            ->where('audience', KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY)
            ->with(['versions' => static function ($query): void {
                $query->orderByDesc('version')->with(['policyDependencies.policy', 'policyDependencies.revision']);
            }])
            ->get();

        $needle = mb_strtolower(trim($query));
        $citations = [];
        foreach ($sources as $source) {
            /** @var KnowledgeSourceVersion|null $latestVersion */
            $latestVersion = $source->versions->first();
            if ($latestVersion === null
                || $latestVersion->status !== KnowledgeSourceVersion::STATUS_ACTIVE
                || $this->validator->isEligible($latestVersion) !== true) {
                continue;
            }

            $question = (string) ($latestVersion->question ?? $source->title);
            $answer = (string) ($latestVersion->answer ?? $latestVersion->body_text);
            if ($needle !== '' && ! str_contains(mb_strtolower($question.' '.$answer), $needle)) {
                continue;
            }

            $key = (string) $source->key;
            $versionId = (string) $latestVersion->getKey();
            $sourceId = (string) $source->getKey();
            $citationKey = EvidenceBundle::SOURCE_PUBLIC_FAQ.':'.$versionId.':'.$sourceId;
            $citations[] = [
                'citation_id' => 'cite_'.substr(hash('sha256', $citationKey), 0, 24),
                'source_class' => EvidenceBundle::SOURCE_PUBLIC_FAQ,
                'version_id' => $versionId,
                'source_id' => $sourceId,
                'title' => $question,
                'excerpt' => $answer,
                'score' => 1.0,
                'locator' => '/faqs#'.$key,
                'as_of' => now()->toIso8601String(),
            ];
            if (count($citations) >= $limit) {
                break;
            }
        }

        return $citations;
    }
}

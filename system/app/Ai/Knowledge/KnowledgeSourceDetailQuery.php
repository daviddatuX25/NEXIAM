<?php

namespace App\Ai\Knowledge;

use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceApproval;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;

final class KnowledgeSourceDetailQuery
{
    public function __construct(
        private readonly KnowledgeSourceAccess $access,
        private readonly PublicationDigest $digest,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $actor, KnowledgeSource $source): array
    {
        $source->load([
            'versions' => fn ($query) => $query->orderByDesc('version')->with(['fixtures', 'officeApprovals', 'source'])->limit(1),
        ]);
        /** @var KnowledgeSourceVersion|null $latest */
        $latest = $source->versions->first();
        $this->access->requireViewSource($actor, $source, $latest);

        $currentOffice = $actor->actorScope()->office;
        $capabilities = [
            'manage' => $this->access->canManageSource($actor, $source),
            'approve' => $this->access->canApproveSource($actor, $source),
            'cross_office_approve' => false,
            'approve_current_version' => false,
            'establish_cross_office' => $this->access->canEstablishCrossOffice($actor, $source),
            'establish_cross_office_current_version' => false,
            'publish_current_version' => false,
            'revoke_current_version' => false,
            'extract_current_version' => false,
        ];

        if ($latest !== null) {
            $creatorIsActor = $latest->created_by === (string) $actor->getKey();
            $officeAlreadySigned = $latest->officeApprovals->contains(
                fn (KnowledgeSourceApproval $approval): bool => $approval->office === $currentOffice,
            );
            $canSignCrossOffice = $latest->status === KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED
                && $source->audience === KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF
                && $capabilities['approve']
                && ! $creatorIsActor
                && ! $officeAlreadySigned;
            $capabilities['approve_current_version'] = $latest->status === KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED
                && $capabilities['approve']
                && ! $creatorIsActor
                && ($source->audience !== KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF || ! $officeAlreadySigned);
            $capabilities['establish_cross_office_current_version'] = $latest->status === KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED
                && $capabilities['establish_cross_office']
                && ! $creatorIsActor;
            $capabilities['publish_current_version'] = in_array($latest->status, [
                KnowledgeSourceVersion::STATUS_APPROVED,
                KnowledgeSourceVersion::STATUS_INDEX_FAILED,
            ], true)
                && $capabilities['approve']
                && $latest->publication_digest === $this->digest->forVersion($latest)
                && ($source->audience !== KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF
                    || $this->hasCurrentJointApprovals($latest));
            $capabilities['revoke_current_version'] = in_array($latest->status, [
                KnowledgeSourceVersion::STATUS_ACTIVE,
                KnowledgeSourceVersion::STATUS_APPROVED,
                KnowledgeSourceVersion::STATUS_INDEXING,
            ], true)
                && $capabilities['approve']
                && ($source->audience !== KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF
                    || $this->hasCurrentJointApprovals($latest));
            $capabilities['extract_current_version'] = in_array($latest->status, [
                KnowledgeSourceVersion::STATUS_DRAFT,
                KnowledgeSourceVersion::STATUS_INDEX_FAILED,
                KnowledgeSourceVersion::STATUS_REJECTED,
            ], true)
                && $latest->approved_by === null
                && $latest->publication_digest === null
                && $capabilities['manage'];
        }

        return [
            'page_state' => 'ready',
            'source' => [
                'id' => $source->id,
                'key' => $source->key,
                'title' => $source->title,
                'source_class' => $source->source_class,
                'owning_office' => $source->owning_office,
                'classification' => $source->classification,
                'audience' => $source->audience,
                'purpose' => $source->purpose,
            ],
            'version' => $latest === null ? null : $this->versionPayload($latest),
            'capabilities' => $capabilities,
            'current_office' => $currentOffice,
        ];
    }

    /** @return array<string, mixed> */
    private function versionPayload(KnowledgeSourceVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'status' => $version->status,
            'title' => $version->title,
            'body_text' => $version->body_text,
            'question' => $version->question,
            'answer' => $version->answer,
            'extraction_text' => $version->extraction_text,
            'content_checksum' => $version->content_checksum,
            'extraction_digest' => $version->extraction_digest,
            'publication_digest' => $version->publication_digest,
            'created_by' => $version->created_by,
            'approved_by' => $version->approved_by,
            'approved_at' => optional($version->approved_at)?->toIso8601String(),
            'published_at' => optional($version->published_at)?->toIso8601String(),
            'revoked_at' => optional($version->revoked_at)?->toIso8601String(),
            'revoke_reason' => $version->revoke_reason,
            'failure_reason' => $version->failure_reason,
            'cross_office_approvals' => $version->officeApprovals->map(fn (KnowledgeSourceApproval $approval) => [
                'office' => $approval->office,
                'approver_id' => $approval->approver_id,
                'approved_at' => optional($approval->approved_at)?->toIso8601String(),
                'publication_digest' => $approval->publication_digest,
            ])->values()->all(),
            'fixtures' => $version->fixtures->map(fn ($fixture) => [
                'id' => $fixture->id,
                'kind' => $fixture->kind,
                'query_text' => $fixture->query_text,
                'expected_excerpt' => $fixture->expected_excerpt,
                'expected_outcome' => $fixture->expected_outcome,
            ])->values()->all(),
        ];
    }

    private function hasCurrentJointApprovals(KnowledgeSourceVersion $version): bool
    {
        if ($version->publication_digest === null || $version->publication_digest === '') {
            return false;
        }

        $approvals = $version->officeApprovals;
        if ($approvals->pluck('office')->sort()->values()->all() !== ['guidance', 'registrar']) {
            return false;
        }

        return ! $approvals->contains(fn (KnowledgeSourceApproval $approval): bool => $approval->publication_digest !== $version->publication_digest
            || $approval->approver_id === $version->created_by);
    }
}

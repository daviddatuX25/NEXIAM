<?php

namespace App\Ai\Adapters\Local;

use App\Ai\Contracts\KnowledgeIndexPort;
use App\Ai\Knowledge\EvidenceBundle;
use App\Models\Ai\KnowledgeIndexEntry;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Selected local index contract for OpenSpec 6 — lexical retrieve over active corpus.
 * (pgvector deferred; no Mixedbread authority.)
 */
final class LocalKnowledgeIndexPort implements KnowledgeIndexPort
{
    private const INDEXABLE_SOURCE_CLASSES = [
        EvidenceBundle::SOURCE_PUBLIC_FAQ,
        EvidenceBundle::SOURCE_INSTITUTION_POLICY,
    ];

    private const ALLOWED_AUDIENCES = ['staff_and_applicant_inquiry', 'staff_only', 'cross_office_staff'];

    private const ALLOWED_CLASSIFICATIONS = ['office_internal', 'public_institutional'];

    private const ALLOWED_OFFICES = ['registrar', 'guidance'];

    public function upsert(array $document): void
    {
        $sourceIsIndexable = KnowledgeSourceVersion::query()
            ->whereKey($document['version_id'])
            ->where('knowledge_source_id', $document['source_id'])
            ->whereHas('source', function (Builder $source): void {
                $source->whereIn('source_class', self::INDEXABLE_SOURCE_CLASSES);
            })
            ->exists();

        if (! $sourceIsIndexable) {
            throw new InvalidArgumentException('knowledge_source_class_not_indexable');
        }

        $existing = KnowledgeIndexEntry::query()
            ->where('knowledge_source_version_id', $document['version_id'])
            ->first();

        if ($existing !== null) {
            $existing->forceFill([
                'knowledge_source_id' => $document['source_id'],
                'status' => 'active',
                'search_text' => $document['text'],
                'meta' => $document['meta'] ?? [],
            ])->save();

            return;
        }

        KnowledgeIndexEntry::query()->create([
            'id' => (string) Str::uuid(),
            'knowledge_source_version_id' => $document['version_id'],
            'knowledge_source_id' => $document['source_id'],
            'status' => 'active',
            'search_text' => $document['text'],
            'meta' => $document['meta'] ?? [],
        ]);
    }

    public function remove(string $versionId): void
    {
        KnowledgeIndexEntry::query()
            ->where('knowledge_source_version_id', $versionId)
            ->update(['status' => 'removed']);
    }

    public function retrieve(string $query, int $limit = 5, array $filters = []): array
    {
        if ($limit < 1) {
            return [];
        }

        $scope = $this->validatedStaffScope($filters);
        $needle = mb_strtolower(trim($query));
        if ($needle === '' || $scope === null) {
            return [];
        }

        $constrainSource = static function (Builder $source) use ($scope): void {
            $source->where('institution_id', $scope['institution_id'])
                ->where('purpose', $scope['purpose'])
                ->whereIn('source_class', $scope['source_classes'])
                ->whereIn('classification', $scope['classifications'])
                ->whereIn('owning_office', self::ALLOWED_OFFICES);
        };

        $entries = KnowledgeIndexEntry::query()
            ->with(['version.source'])
            ->where('status', 'active')
            ->whereHas('version', function (Builder $version) use ($scope, $constrainSource): void {
                $version
                    ->where('status', KnowledgeSourceVersion::STATUS_ACTIVE)
                    ->whereNotNull('approved_by')
                    ->whereColumn('knowledge_source_versions.approved_by', '<>', 'knowledge_source_versions.created_by')
                    ->whereColumn('knowledge_index_entries.knowledge_source_id', 'knowledge_source_versions.knowledge_source_id')
                    ->where(function (Builder $typedContent): void {
                        $typedContent->whereHas('source', function (Builder $source): void {
                            $source->where('source_class', '<>', 'public_faq');
                        })->orWhere(function (Builder $faq): void {
                            $faq->whereHas('source', function (Builder $source): void {
                                $source->where('source_class', 'public_faq');
                            })
                                ->whereNotNull('question')
                                ->where('question', '<>', '')
                                ->whereNotNull('answer')
                                ->where('answer', '<>', '');
                        });
                    })
                    ->where(function (Builder $visibility) use ($scope, $constrainSource): void {
                        if (in_array('staff_and_applicant_inquiry', $scope['audiences'], true)) {
                            $visibility->whereHas('source', function (Builder $source) use ($constrainSource): void {
                                $constrainSource($source);
                                $source
                                    ->where('audience', 'staff_and_applicant_inquiry')
                                    ->where('classification', 'public_institutional');
                            });
                        }

                        if (in_array('staff_only', $scope['audiences'], true)) {
                            $visibility->orWhereHas('source', function (Builder $source) use ($scope, $constrainSource): void {
                                $constrainSource($source);
                                $source
                                    ->where('audience', 'staff_only')
                                    ->where('classification', 'office_internal')
                                    ->where('owning_office', $scope['owning_office']);
                            });
                        }

                        if (in_array('cross_office_staff', $scope['audiences'], true)) {
                            $visibility->orWhere(function (Builder $crossOffice) use ($constrainSource): void {
                                $crossOffice->whereHas('source', function (Builder $source) use ($constrainSource): void {
                                    $constrainSource($source);
                                    $source
                                        ->where('audience', 'cross_office_staff')
                                        ->where('classification', 'office_internal');
                                })->whereExists(function ($approval): void {
                                    $approval
                                        ->selectRaw('1')
                                        ->from('knowledge_source_approvals as registrar_approval')
                                        ->whereColumn('registrar_approval.knowledge_source_version_id', 'knowledge_source_versions.id')
                                        ->whereColumn('registrar_approval.publication_digest', 'knowledge_source_versions.publication_digest')
                                        ->whereColumn('registrar_approval.approver_id', '<>', 'knowledge_source_versions.created_by')
                                        ->where('registrar_approval.office', 'registrar')
                                        ->whereNotExists(function ($sameApprover): void {
                                            $sameApprover
                                                ->selectRaw('1')
                                                ->from('knowledge_source_approvals as duplicate_guidance_approval')
                                                ->whereColumn('duplicate_guidance_approval.knowledge_source_version_id', 'knowledge_source_versions.id')
                                                ->whereColumn('duplicate_guidance_approval.publication_digest', 'knowledge_source_versions.publication_digest')
                                                ->whereColumn('duplicate_guidance_approval.approver_id', 'registrar_approval.approver_id')
                                                ->where('duplicate_guidance_approval.office', 'guidance');
                                        });
                                })->whereExists(function ($approval): void {
                                    $approval
                                        ->selectRaw('1')
                                        ->from('knowledge_source_approvals as guidance_approval')
                                        ->whereColumn('guidance_approval.knowledge_source_version_id', 'knowledge_source_versions.id')
                                        ->whereColumn('guidance_approval.publication_digest', 'knowledge_source_versions.publication_digest')
                                        ->whereColumn('guidance_approval.approver_id', '<>', 'knowledge_source_versions.created_by')
                                        ->where('guidance_approval.office', 'guidance');
                                });
                            });
                        }
                    });
            })
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $tokens = preg_split('/\s+/', $needle) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($token) => mb_strlen($token) >= 3));
        if ($tokens === []) {
            $tokens = [$needle];
        }

        $hits = [];
        foreach ($entries as $entry) {
            $haystack = mb_strtolower($entry->search_text);
            $matched = false;
            foreach ($tokens as $token) {
                if (str_contains($haystack, $token)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                continue;
            }

            $source = $entry->version?->source;
            $excerpt = trim(mb_substr($entry->search_text, 0, 240));
            if ($source === null || $excerpt === '') {
                continue;
            }

            $hits[] = [
                'version_id' => $entry->knowledge_source_version_id,
                'institution_id' => (int) $source->institution_id,
                'source_id' => $entry->knowledge_source_id,
                'source_key' => $source->key,
                'source_title' => $source->title,
                'excerpt' => $excerpt,
                'score' => 1.0,
                'audience' => $source->audience,
                'source_class' => $source->source_class,
                'classification' => $source->classification,
                'owning_office' => $source->owning_office,
                'cross_office_approved' => $source->audience === 'cross_office_staff',
            ];
            if (count($hits) >= $limit) {
                break;
            }
        }

        return $hits;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     actor_id: string,
     *     institution_id: int,
     *     owning_office: 'registrar'|'guidance',
     *     audiences: list<string>,
     *     classifications: list<string>,
     *     purpose: string,
     *     source_classes: list<string>
     * }|null
     */
    private function validatedStaffScope(array $filters): ?array
    {
        $actorId = $filters['actor_id'] ?? null;
        $actorType = $filters['actor_type'] ?? null;
        $institutionId = $filters['institution_id'] ?? null;
        $owningOffice = $filters['owning_office'] ?? null;
        $audiences = $filters['audiences'] ?? null;
        $classifications = $filters['classifications'] ?? null;
        $purpose = $filters['purpose'] ?? null;
        $sourceClasses = $filters['source_classes'] ?? null;

        if (! is_string($actorId) || trim($actorId) === '' || $actorType !== 'staff'
            || ! is_int($institutionId) || $institutionId < 1
            || ! is_string($owningOffice) || ! in_array($owningOffice, self::ALLOWED_OFFICES, true)
            || ! $this->isAllowedList($audiences, self::ALLOWED_AUDIENCES)
            || ! $this->isAllowedList($classifications, self::ALLOWED_CLASSIFICATIONS)
            || ! is_string($purpose) || trim($purpose) === ''
            || ! $this->isAllowedList($sourceClasses, self::INDEXABLE_SOURCE_CLASSES)) {
            return null;
        }

        $actor = User::query()
            ->with('staffProfile')
            ->whereKey($actorId)
            ->first();
        $profile = $actor?->staffProfile;

        if ($profile === null
            || ! $profile->isActive()
            || (int) $profile->institution_id !== $institutionId
            || $profile->office !== $owningOffice) {
            return null;
        }

        return [
            'actor_id' => $actorId,
            'institution_id' => $institutionId,
            'owning_office' => $owningOffice,
            'audiences' => array_values(array_unique($audiences)),
            'classifications' => array_values(array_unique($classifications)),
            'purpose' => trim($purpose),
            'source_classes' => array_values(array_unique($sourceClasses)),
        ];
    }

    /**
     * @param  list<string>  $allowedValues
     */
    private function isAllowedList(mixed $values, array $allowedValues): bool
    {
        if (! is_array($values) || ! array_is_list($values) || $values === []) {
            return false;
        }

        foreach ($values as $value) {
            if (! is_string($value) || ! in_array($value, $allowedValues, true)) {
                return false;
            }
        }

        return true;
    }

    public function rebuildFromActive(): int
    {
        KnowledgeIndexEntry::query()->delete();
        $count = 0;
        KnowledgeSourceVersion::query()
            ->with('source')
            ->where('status', KnowledgeSourceVersion::STATUS_ACTIVE)
            ->whereHas('source', function (Builder $source): void {
                $source->whereIn('source_class', self::INDEXABLE_SOURCE_CLASSES);
            })
            ->each(function (KnowledgeSourceVersion $version) use (&$count): void {
                $text = $version->source?->source_class === 'public_faq'
                    ? trim((string) ($version->source?->title ?? '')."\n".(string) $version->question."\n".(string) $version->answer)
                    : (string) ($version->extraction_text ?: $version->body_text);
                if ($text === '') {
                    return;
                }

                $this->upsert([
                    'version_id' => $version->id,
                    'source_id' => $version->knowledge_source_id,
                    'text' => $text,
                    'meta' => ['rebuilt' => true],
                ]);
                $count++;
            });

        return $count;
    }
}

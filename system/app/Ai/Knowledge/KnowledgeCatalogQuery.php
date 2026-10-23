<?php

namespace App\Ai\Knowledge;

use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;

/**
 * Human and assistant-safe catalog projection for governed knowledge sources.
 */
final class KnowledgeCatalogQuery
{
    public function __construct(private readonly KnowledgeSourceAccess $access) {}

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return array{sources: list<array<string, mixed>>, page_state: string}
     */
    public function handle(User $actor, array $filters = []): array
    {
        if (! $this->access->canViewCatalog($actor)) {
            return ['sources' => [], 'page_state' => 'empty'];
        }

        $scope = $actor->actorScope();
        $status = isset($filters['status']) && is_string($filters['status']) ? $filters['status'] : null;
        $search = isset($filters['search']) && is_string($filters['search']) ? trim($filters['search']) : null;

        $sources = KnowledgeSource::query()
            ->where('institution_id', $scope->institutionId)
            ->whereNotNull('owning_office')
            ->whereIn('owning_office', KnowledgeSourceAccess::OFFICES)
            ->whereIn('source_class', [
                KnowledgeSource::SOURCE_CLASS_POLICY,
                KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
            ])
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('audience', KnowledgeSource::AUDIENCE_STAFF_ONLY)
                        ->where('classification', KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL);
                })->orWhere(function ($query): void {
                    $query->where('audience', KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY)
                        ->where('classification', KnowledgeSource::CLASSIFICATION_PUBLIC_INSTITUTIONAL);
                })->orWhere(function ($query): void {
                    $query->where('audience', KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF)
                        ->where('classification', KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL);
                });
            })
            ->where(function ($query) use ($scope): void {
                $query->where('audience', KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY)
                    ->orWhere(function ($query) use ($scope): void {
                        $query->where('audience', KnowledgeSource::AUDIENCE_STAFF_ONLY)
                            ->where('owning_office', $scope->office);
                    })
                    ->orWhere(function ($query): void {
                        $query->where('audience', KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF)
                            ->whereIn('owning_office', KnowledgeSourceAccess::OFFICES);
                    });
            })
            ->with(['versions' => fn ($query) => $query->with(['officeApprovals', 'fixtures', 'source'])->orderByDesc('version')->limit(1)])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(function (KnowledgeSource $source) use ($status, $search, $actor): ?array {
                /** @var KnowledgeSourceVersion|null $latest */
                $latest = $source->versions->first();
                if ($latest === null || ! $this->access->canViewSource($actor, $source, $latest)) {
                    return null;
                }

                if ($status !== null && $status !== '' && $latest->status !== $status) {
                    return null;
                }

                if ($search !== null && $search !== '') {
                    $haystack = mb_strtolower(implode(' ', [
                        $source->title,
                        $source->key,
                        $latest->title,
                        $latest->question ?? '',
                        $latest->answer ?? '',
                        $latest->body_text ?? '',
                        $latest->extraction_text ?? '',
                    ]));
                    if (! str_contains($haystack, mb_strtolower($search))) {
                        return null;
                    }
                }

                return [
                    'id' => $source->id,
                    'key' => $source->key,
                    'title' => $source->title,
                    'classification' => $source->classification,
                    'owning_office' => $source->owning_office,
                    'audience' => $source->audience,
                    'source_class' => $source->source_class,
                    'latest_version' => [
                        'id' => $latest->id,
                        'version' => $latest->version,
                        'status' => $latest->status,
                        'title' => $latest->title,
                        'question' => $latest->question,
                        'answer' => $latest->answer,
                        'body_text' => $latest->body_text ?: $latest->extraction_text,
                        'updated_at' => optional($latest->updated_at)?->toIso8601String(),
                        'published_at' => optional($latest->published_at)?->toIso8601String(),
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'sources' => $sources,
            'page_state' => $sources === [] ? 'empty' : 'ready',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Ai\Knowledge;

use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Builder;

final class PublicFaqQuery
{
    public function __construct(private readonly FaqPolicyDependencyValidator $validator) {}

    /** @return list<array<string, mixed>> */
    public function getPublicFaqs(?int $institutionId = null, ?string $category = null, ?string $office = null, ?string $search = null): array
    {
        $institutionId ??= (int) Institution::query()->orderBy('id')->value('id');
        if ($institutionId <= 0) {
            return [];
        }

        $sources = KnowledgeSource::query()
            ->where('source_class', KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ)
            ->where('audience', KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY)
            ->where('institution_id', $institutionId)
            ->when($category !== null && $category !== '' && $category !== 'all', fn (Builder $query) => $query->where('category', $category))
            ->when($office !== null && $office !== '', fn (Builder $query) => $query->where('owning_office', $office))
            ->with(['versions' => static function ($query): void {
                $query->orderByDesc('version')->with(['policyDependencies.policy', 'policyDependencies.revision']);
            }])
            ->get();

        $projection = new PublicFaqProjection;
        $items = [];
        foreach ($sources as $source) {
            /** @var KnowledgeSourceVersion|null $latestVersion */
            $latestVersion = $source->versions->first();
            if ($latestVersion === null || $latestVersion->status !== KnowledgeSourceVersion::STATUS_ACTIVE || ! $this->validator->isEligible($latestVersion)) {
                continue;
            }

            $item = $projection->toItem($source, $latestVersion);
            if ($search !== null && trim($search) !== '') {
                $needle = mb_strtolower(trim($search));
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($latestVersion->question ?? ''),
                    (string) ($latestVersion->answer ?? ''),
                    (string) $latestVersion->title,
                ]));
                if (! str_contains($haystack, $needle)) {
                    continue;
                }
            }
            $items[] = $item;
        }

        return $items;
    }

    /** @return list<array{id: string, label: string}> */
    public function getCategories(): array
    {
        return [
            ['id' => 'all', 'label' => 'All Topics'],
            ['id' => 'campus', 'label' => 'General / Admin'],
            ['id' => 'admissions', 'label' => 'Admissions / Reg.'],
            ['id' => 'testing', 'label' => 'Guidance / Testing'],
        ];
    }
}

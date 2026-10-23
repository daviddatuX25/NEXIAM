<?php

namespace App\ResultSheet\Queries;

use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\Models\ResultSheet\ResultSheetTemplate;
use Illuminate\Support\Collection;

final class ResultSheetTemplatesListQuery
{
    public function forHumanIndex(): Collection
    {
        $thumbnailService = app(DocumentThumbnailService::class);

        return ResultSheetTemplate::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (ResultSheetTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'mode' => $template->mode,
                'paper_size' => $template->paper_size,
                'orientation' => $template->orientation,
                'logical_unit' => $template->logical_unit,
                'watermark_text' => $template->watermark_text,
                'is_active' => (bool) $template->is_active,
                'document_extension' => $template->document_extension,
                'updated_at' => $template->updated_at,
                'thumbnail_url' => route('setup.result-templates.thumbnail', $template).'?v='.$thumbnailService->fingerprint($template->printChecksum()),
            ]);
    }

    /**
     * @return array{
     *     summary: array{total_count: int, active_count: int, active_template_name: string, active_template_mode: string, as_of: string},
     *     templates: list<array{id: int, display_name: string, is_active: bool, mode: string, orientation: string, paper_size: string}>
     * }
     */
    public function forXiamSafe(int $limit = 10): array
    {
        $totalCount = ResultSheetTemplate::query()->count();
        $activeCount = ResultSheetTemplate::query()->where('is_active', true)->count();
        $activeTemplate = ResultSheetTemplate::query()
            ->where('is_active', true)
            ->first(['id', 'name', 'mode', 'orientation', 'paper_size']);

        $templates = ResultSheetTemplate::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'mode', 'orientation', 'paper_size', 'is_active'])
            ->map(fn ($template) => [
                'id' => (int) $template->id,
                'display_name' => (string) $template->name,
                'is_active' => (bool) $template->is_active,
                'mode' => (string) $template->mode,
                'orientation' => (string) $template->orientation,
                'paper_size' => (string) $template->paper_size,
            ])
            ->all();

        return [
            'summary' => [
                'total_count' => $totalCount,
                'active_count' => $activeCount,
                'active_template_name' => $activeTemplate?->name ?? 'None',
                'active_template_mode' => $activeTemplate?->mode ?? 'none',
                'as_of' => now()->toIso8601String(),
            ],
            'templates' => $templates,
        ];
    }
}

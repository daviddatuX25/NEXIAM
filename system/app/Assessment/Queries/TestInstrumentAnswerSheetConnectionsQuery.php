<?php

namespace App\Assessment\Queries;

use App\Assessment\Support\TestInstrumentAccess;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\User;

final class TestInstrumentAnswerSheetConnectionsQuery
{
    public function __construct(private readonly TestInstrumentAccess $access) {}

    /**
     * @return array<string, array{sheet_title: string, version_number: int, pages_summary: string, mapped_items_count: int, total_items_count: int, status: string}>
     */
    public function handle(User $actor, int $testInstrumentId, ?int $versionId = null): array
    {
        $institutionId = $this->access->institutionId($actor);
        $instrumentVersion = TestInstrumentVersion::query()
            ->where('test_instrument_id', $testInstrumentId)
            ->whereHas('instrument', fn ($query) => $query->where('institution_id', $institutionId))
            ->when($versionId !== null, fn ($query) => $query->whereKey($versionId))
            ->with('subtests.items')
            ->orderByDesc('version_number')
            ->first();

        if ($instrumentVersion === null) {
            return [];
        }

        $templateVersions = OmrAnswerSheetTemplateVersion::query()
            ->where('test_instrument_version_id', $instrumentVersion->id)
            ->whereIn('status', [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE])
            ->whereHas('template', fn ($query) => $query->where('institution_id', $institutionId))
            ->with(['template', 'itemMappings'])
            ->orderByDesc('version')
            ->get();

        return $instrumentVersion->subtests->mapWithKeys(function ($subtest) use ($templateVersions): array {
            $templateVersion = $templateVersions->first();
            $mappings = $templateVersion?->itemMappings
                ->where('test_instrument_subtest_id', $subtest->id)
                ->count() ?? 0;
            $totalItems = $subtest->items->count();
            $isManual = $subtest->response_mode === 'recorded_measure';

            return [(string) $subtest->code => [
                'sheet_title' => (string) ($templateVersion?->template?->title ?? $templateVersion?->template?->name ?? 'Standard OMR Sheet'),
                'version_number' => (int) ($templateVersion?->version ?? 1),
                'pages_summary' => $isManual
                    ? 'No bubble section needed'
                    : ($mappings > 0 ? 'P1: Bubbles 1–'.$mappings : 'No bubble mappings'),
                'mapped_items_count' => $mappings,
                'total_items_count' => $totalItems,
                'status' => $isManual ? 'manual' : ($mappings >= $totalItems ? 'mapped' : ($mappings > 0 ? 'partial' : 'unmapped')),
            ]];
        })->all();
    }
}

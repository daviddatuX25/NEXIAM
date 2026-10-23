<?php

namespace App\Assessment\Queries;

use App\Assessment\Support\TestInstrumentAccess;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\TestInstrument;
use App\Models\User;

final class TestInstrumentCatalogQuery
{
    public function __construct(private readonly TestInstrumentAccess $access) {}

    /**
     * @return array{page_state: string, instruments: list<array<string, mixed>>}
     */
    public function handle(User $actor): array
    {
        $institutionId = $this->access->institutionId($actor);
        $instruments = TestInstrument::query()
            ->where('institution_id', $institutionId)
            ->with([
                'draftVersion.metrics',
                'draftVersion.subtests',
                'currentApprovedVersion.metrics',
                'currentApprovedVersion.subtests',
            ])
            ->orderBy('code')
            ->get();

        return [
            'page_state' => $instruments->isEmpty() ? 'empty_scope' : 'ready',
            'instruments' => $instruments->map(function (TestInstrument $instrument) use ($institutionId): array {
                $draft = $instrument->draftVersion;
                $approved = $instrument->currentApprovedVersion;
                $current = $draft ?? $approved;
                $isFinalOnly = $current?->isFinalOnly() ?? false;
                $readinessVersion = $approved ?? $draft;
                $metrics = $readinessVersion?->metrics ?? collect();
                $normRequired = ! $isFinalOnly && $metrics->contains(static fn ($metric): bool => (bool) $metric->is_required
                    && ($metric->required_output_scales ?? []) !== []);
                $formCode = $current?->form_code;
                $hasNormTables = ! $isFinalOnly && AssessmentNormPackage::query()
                    ->where('institution_id', $institutionId)
                    ->where(function ($query) use ($instrument, $formCode): void {
                        $query->where('code', 'INSTRUMENT_'.$instrument->id);
                        if ($formCode !== null && $formCode !== '') {
                            $query->orWhereHas('versions', static fn ($q) => $q->where('form_code', $formCode));
                        }
                    })
                    ->where(function ($query): void {
                        $query->whereHas('draftVersion.tables')
                            ->orWhereHas('currentApprovedVersion.tables');
                    })
                    ->exists();

                return [
                    'id' => (int) $instrument->id,
                    'code' => (string) $instrument->code,
                    'is_synthetic' => (bool) $instrument->is_synthetic,
                    'display_name' => $current?->display_name ?? 'No version details',
                    'form_code' => $current?->form_code,
                    'version_number' => $current === null ? null : (int) $current->version_number,
                    'status' => $draft !== null ? 'draft' : ($approved !== null ? 'approved' : 'retired'),
                    'source_readiness' => $current?->source_reviewed_at === null ? 'unverified' : 'reviewed',
                    'raw_readiness' => $approved !== null ? 'approved' : 'draft',
                    'norm_readiness' => $isFinalOnly
                        ? 'not_applicable'
                        : ($hasNormTables ? 'configured' : ($normRequired ? 'missing_package' : 'not_declared')),
                    'profile_metadata' => ['is_final_only' => $isFinalOnly],
                    'subtest_count' => $current?->subtests->count() ?? 0,
                    'required_metric_count' => $metrics->where('is_required', true)->count(),
                    'can_approve' => $draft !== null && ! $instrument->is_synthetic,
                    'can_create_successor' => $approved !== null && $draft === null,
                ];
            })->values()->all(),
        ];
    }
}

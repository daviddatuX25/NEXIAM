<?php

namespace App\Assessment\Queries;

use App\Assessment\Support\TestInstrumentAccess;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class TestInstrumentDetailQuery
{
    public function __construct(
        private readonly TestInstrumentAccess $access,
        private readonly TestInstrumentProfileValidator $validator,
    ) {}

    /** @return array<string, mixed> */
    public function show(User $actor, int $instrumentId): array
    {
        $instrument = $this->findInstrument($actor, $instrumentId);

        return [
            'page_state' => 'ready',
            'instrument' => $this->projectInstrument($instrument),
        ];
    }

    /** @return array<string, mixed> */
    public function edit(User $actor, int $instrumentId): array
    {
        $instrument = $this->findInstrument($actor, $instrumentId);
        $draft = $instrument->draftVersion;

        if ($draft === null) {
            throw ValidationException::withMessages(['version' => 'Create a successor draft before editing this instrument.']);
        }

        return [
            'page_state' => 'ready',
            'instrument' => [
                'id' => (int) $instrument->id,
                'code' => (string) $instrument->code,
                'is_synthetic' => (bool) $instrument->is_synthetic,
            ],
            'version' => [
                'id' => (int) $draft->id,
                'version_number' => (int) $draft->version_number,
                'lock_version' => (int) $draft->lock_version,
                ...$this->validator->normalizeVersion($draft),
                'profile_metadata' => ['is_final_only' => $draft->isFinalOnly()],
            ],
        ];
    }

    private function findInstrument(User $actor, int $instrumentId): TestInstrument
    {
        return TestInstrument::query()
            ->where('institution_id', $this->access->institutionId($actor))
            ->whereKey($instrumentId)
            ->with([
                'draftVersion.subtests.items',
                'draftVersion.metrics.inputs.inputMetric',
                'draftVersion.metrics.subtest',
                'currentApprovedVersion.subtests.items',
                'currentApprovedVersion.metrics.inputs.inputMetric',
                'currentApprovedVersion.metrics.subtest',
                'versions.subtests.items',
                'versions.metrics.inputs.inputMetric',
                'versions.metrics.subtest',
            ])
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function projectInstrument(TestInstrument $instrument): array
    {
        $versions = $instrument->versions->map(function (TestInstrumentVersion $version): array {
            $normalized = $this->validator->normalizeVersion($version);

            return [
                'id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
                'status' => (string) $version->status,
                'lock_version' => (int) $version->lock_version,
                'display_name' => (string) $version->display_name,
                'publisher_name' => $version->publisher_name,
                'form_code' => $version->form_code,
                'edition' => $version->edition,
                'language' => $version->language,
                'source_reference' => $version->source_reference,
                'permitted_use_reference' => $version->permitted_use_reference,
                'source_reviewed_at' => $version->source_reviewed_at?->toISOString(),
                'approved_at' => $version->approved_at?->toISOString(),
                'retired_at' => $version->retired_at?->toISOString(),
                'predecessor_version_id' => $version->predecessor_version_id === null ? null : (int) $version->predecessor_version_id,
                'content_sha256' => $version->content_sha256,
                'subtests' => $normalized['subtests'],
                'metrics' => $normalized['metrics'],
                'profile_metadata' => ['is_final_only' => $version->isFinalOnly()],
            ];
        })->values()->all();

        $currentDefinition = $instrument->draftVersion ?? $instrument->currentApprovedVersion;
        $isFinalOnly = $currentDefinition?->isFinalOnly() ?? false;

        return [
            'id' => (int) $instrument->id,
            'code' => (string) $instrument->code,
            'is_synthetic' => (bool) $instrument->is_synthetic,
            'current_approved_version_id' => $instrument->current_approved_version_id === null
                ? null
                : (int) $instrument->current_approved_version_id,
            'versions' => $versions,
            'draft_version_id' => $instrument->draftVersion === null ? null : (int) $instrument->draftVersion->id,
            'current_approved_version' => $instrument->currentApprovedVersion === null
                ? null
                : $this->versionSummary($instrument->currentApprovedVersion),
            'draft_version' => $instrument->draftVersion === null
                ? null
                : $this->versionSummary($instrument->draftVersion),
            'raw_readiness' => $instrument->currentApprovedVersion !== null
                ? 'approved'
                : ($instrument->draftVersion !== null ? 'draft' : 'unavailable'),
            'source_readiness' => $instrument->draftVersion !== null
                ? 'unverified'
                : ($instrument->currentApprovedVersion?->source_reviewed_at !== null ? 'reviewed' : 'unverified'),
            'norm_readiness' => $instrument->currentApprovedVersion === null
                ? 'unavailable'
                : ($isFinalOnly ? 'not_applicable' : 'missing_package'),
            'profile_metadata' => ['is_final_only' => $isFinalOnly],
        ];
    }

    /** @return array<string, mixed> */
    private function versionSummary(TestInstrumentVersion $version): array
    {
        $normalized = $this->validator->normalizeVersion($version);

        return [
            'id' => (int) $version->id,
            'version_number' => (int) $version->version_number,
            'lock_version' => (int) $version->lock_version,
            'status' => (string) $version->status,
            'display_name' => (string) $version->display_name,
            'form_code' => $version->form_code,
            'edition' => $version->edition,
            'source_reference' => $version->source_reference,
            'permitted_use_reference' => $version->permitted_use_reference,
            'source_reviewed_at' => $version->source_reviewed_at?->toISOString(),
            'approved_at' => $version->approved_at?->toISOString(),
            'retired_at' => $version->retired_at?->toISOString(),
            'content_sha256' => $version->content_sha256,
            'subtests' => $normalized['subtests'],
            'profile_metadata' => ['is_final_only' => $version->isFinalOnly()],
            'metrics' => $normalized['metrics'],
        ];
    }
}

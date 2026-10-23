<?php

namespace App\Assessment\Queries;

use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestKitVersion;

class ApprovedTestInstrumentSelectionQuery
{
    /**
     * Retrieve approved test instrument versions for institution selection.
     *
     * @return list<array{
     *     id: int,
     *     instrument_code: string,
     *     display_name: string,
     *     form_code: string|null,
     *     version_number: int
     * }>
     */
    public function forInstitution(int $institutionId): array
    {
        $instruments = TestInstrument::query()
            ->where('institution_id', $institutionId)
            ->whereNotNull('current_approved_version_id')
            ->with('currentApprovedVersion')
            ->get();

        $results = [];

        foreach ($instruments as $instrument) {
            $version = $instrument->currentApprovedVersion;

            if ($version === null) {
                continue;
            }

            $results[] = [
                'id' => $version->id,
                'instrument_code' => $instrument->code,
                'display_name' => $version->display_name,
                'form_code' => $version->form_code,
                'version_number' => $version->version_number,
            ];
        }

        return $results;
    }

    /**
     * Return selectable, currently active locked Test Kit versions for direct assessment.
     *
     * @return list<array{id:int,display_name:string,kit_code:string,version_number:int,profile_name:string|null}>
     */
    public function activeKitVersionsForInstitution(int $institutionId, ?int $includeVersionId = null): array
    {
        return TestKitVersion::query()
            ->where('institution_id', $institutionId)
            ->whereNotNull('test_instrument_version_id')
            ->where(function ($query) use ($includeVersionId): void {
                $query->where(function ($activeQuery): void {
                    $activeQuery
                        ->where('status', TestKitVersion::STATUS_ACTIVE)
                        ->whereNull('revoked_at');
                });
                if ($includeVersionId !== null) {
                    $query->orWhere($query->qualifyColumn('id'), $includeVersionId);
                }
            })
            ->with(['testKit:id,code,name', 'testInstrumentVersion:id,display_name'])
            ->orderBy('test_kit_id')
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (TestKitVersion $version): array => [
                'id' => (int) $version->id,
                'test_kit_id' => (int) $version->test_kit_id,
                'display_name' => (string) ($version->testKit?->name ?? $version->testKit?->code ?? 'Test Kit'),
                'kit_code' => (string) ($version->testKit?->code ?? ''),
                'version_number' => (int) $version->version_number,
                'profile_name' => $version->testInstrumentVersion?->display_name,
                'kit_url' => route('setup.exam-materials.test-kits.show', [
                    'testKit' => (int) $version->test_kit_id,
                    'version' => (int) $version->id,
                ], absolute: false),
                'test_setup_url' => route('setup.exam-materials.test-kits.test-setup', [
                    'testKit' => (int) $version->test_kit_id,
                    'test_kit_id' => (int) $version->test_kit_id,
                    'test_kit_version_id' => (int) $version->id,
                ], absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,display_name:string,kit_code:string,version_number:int,profile_name:string|null}>
     */
    public function importKitVersionsForInstitution(int $institutionId): array
    {
        return TestKitVersion::query()
            ->where('institution_id', $institutionId)
            ->whereIn('status', [
                TestKitVersion::STATUS_ACTIVE,
                TestKitVersion::STATUS_RETIRED,
            ])
            ->whereNotNull('test_instrument_version_id')
            ->whereNull('revoked_at')
            ->with(['testKit:id,code,name', 'testInstrumentVersion:id,display_name'])
            ->orderBy('test_kit_id')
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (TestKitVersion $version): array => [
                'id' => (int) $version->id,
                'display_name' => (string) ($version->testKit?->name ?? $version->testKit?->code ?? 'Test Kit'),
                'kit_code' => (string) ($version->testKit?->code ?? ''),
                'version_number' => (int) $version->version_number,
                'profile_name' => $version->testInstrumentVersion?->display_name,
            ])
            ->all();
    }
}

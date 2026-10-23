<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillDrafts();

        $pgsql = DB::connection()->getDriverName() === 'pgsql';
        if ($pgsql) {
            DB::statement('ALTER TABLE omr_answer_sheet_template_versions DISABLE TRIGGER omr_template_version_immutability');
        }
        try {
            $this->backfillVersions();
        } finally {
            if ($pgsql) {
                DB::statement('ALTER TABLE omr_answer_sheet_template_versions ENABLE TRIGGER omr_template_version_immutability');
            }
        }

        $this->assertCompleteBackfill();
    }

    public function down(): void
    {
        // Historical policy classification is intentionally preserved on rollback.
    }

    private function backfillDrafts(): void
    {
        DB::table('omr_template_authoring_drafts')
            ->select([
                'id', 'stage', 'authoring_json', 'registration_mode', 'qr_mode',
                'requested_page_count',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($drafts): void {
                foreach ($drafts as $draft) {
                    if ($this->hasCompletePolicy(
                        $draft->registration_mode,
                        $draft->qr_mode,
                        $draft->requested_page_count,
                        'draft',
                        (int) $draft->id,
                    )) {
                        continue;
                    }

                    $authoring = $this->decodeJson($draft->authoring_json, 'draft', (int) $draft->id);
                    $pages = is_array($authoring['pages'] ?? null) ? $authoring['pages'] : [];
                    $pageListCount = $pages === []
                        ? null
                        : $this->sequentialPageCount($pages, 'draft pages', (int) $draft->id);
                    $mappedPageCount = is_numeric(data_get($authoring, 'mapping.input.page_count'))
                        ? (int) data_get($authoring, 'mapping.input.page_count')
                        : null;
                    if ($mappedPageCount !== null && ($mappedPageCount < 1 || $mappedPageCount > 4)) {
                        throw new RuntimeException(
                            "OMR paper-policy backfill found invalid mapping page count for draft {$draft->id}.",
                        );
                    }
                    if ($pageListCount !== null && $mappedPageCount !== null && $pageListCount !== $mappedPageCount) {
                        throw new RuntimeException(
                            "OMR paper-policy backfill mismatch for draft {$draft->id}: pages={$pageListCount}, mapping={$mappedPageCount}.",
                        );
                    }
                    if ($pageListCount === null && $mappedPageCount === null && $draft->stage !== 'draft') {
                        throw new RuntimeException(
                            "OMR paper-policy backfill cannot derive pages for draft {$draft->id} at stage {$draft->stage}.",
                        );
                    }
                    $pageCount = $pageListCount ?? $mappedPageCount ?? 1;

                    DB::table('omr_template_authoring_drafts')
                        ->where('id', $draft->id)
                        ->whereNull('registration_mode')
                        ->whereNull('qr_mode')
                        ->whereNull('requested_page_count')
                        ->update([
                            'registration_mode' => 'fiducial_anchored',
                            'qr_mode' => 'qr_signed',
                            'requested_page_count' => $pageCount,
                        ]);
                }
            });
    }

    private function backfillVersions(): void
    {
        DB::table('omr_answer_sheet_template_versions')
            ->select([
                'id', 'native_bundle_json', 'registration_mode', 'qr_mode', 'page_count',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($versions): void {
                foreach ($versions as $version) {
                    if ($this->hasCompletePolicy(
                        $version->registration_mode,
                        $version->qr_mode,
                        $version->page_count,
                        'version',
                        (int) $version->id,
                    )) {
                        continue;
                    }

                    $native = $this->decodeJson($version->native_bundle_json, 'version', (int) $version->id);
                    $nativePages = is_array(data_get($native, 'template.pages'))
                        ? data_get($native, 'template.pages')
                        : [];
                    $nativeCount = $nativePages === []
                        ? null
                        : $this->sequentialPageCount($nativePages, 'version native bundle', (int) $version->id);

                    $persistedPageNumbers = DB::table('omr_answer_sheet_pages')
                        ->where('template_version_id', $version->id)
                        ->orderBy('page_no')
                        ->pluck('page_no')
                        ->map(static fn (mixed $pageNo): int => (int) $pageNo)
                        ->all();
                    $persistedCount = $persistedPageNumbers === []
                        ? null
                        : $this->assertSequentialNumbers($persistedPageNumbers, 'version pages', (int) $version->id);

                    if ($nativeCount === null && $persistedCount === null) {
                        throw new RuntimeException(
                            "OMR paper-policy backfill cannot derive pages for immutable version {$version->id}.",
                        );
                    }
                    if ($nativeCount !== null && $persistedCount !== null && $nativeCount !== $persistedCount) {
                        throw new RuntimeException(
                            "OMR paper-policy backfill mismatch for version {$version->id}: native={$nativeCount}, persisted={$persistedCount}.",
                        );
                    }

                    DB::table('omr_answer_sheet_template_versions')
                        ->where('id', $version->id)
                        ->whereNull('registration_mode')
                        ->whereNull('qr_mode')
                        ->whereNull('page_count')
                        ->update([
                            'registration_mode' => 'fiducial_anchored',
                            'qr_mode' => 'qr_signed',
                            'page_count' => $nativeCount ?? $persistedCount,
                        ]);
                }
            });
    }

    private function hasCompletePolicy(
        mixed $registrationMode,
        mixed $qrMode,
        mixed $pageCount,
        string $recordType,
        int $recordId,
    ): bool {
        $present = (int) ($registrationMode !== null)
            + (int) ($qrMode !== null)
            + (int) ($pageCount !== null);
        if ($present === 0) {
            return false;
        }
        if ($present !== 3) {
            throw new RuntimeException(
                "OMR paper-policy backfill found partial policy for {$recordType} {$recordId}.",
            );
        }
        if (! in_array($registrationMode, ['fiducial_anchored', 'document_bound'], true)
            || ! in_array($qrMode, ['qr_signed', 'qr_disabled'], true)
            || (int) $pageCount < 1
            || (int) $pageCount > 4) {
            throw new RuntimeException(
                "OMR paper-policy backfill found invalid policy for {$recordType} {$recordId}.",
            );
        }

        return true;
    }

    private function assertCompleteBackfill(): void
    {
        $invalidDrafts = DB::table('omr_template_authoring_drafts')
            ->whereNull('registration_mode')
            ->orWhereNull('qr_mode')
            ->orWhereNull('requested_page_count')
            ->orWhereNotBetween('requested_page_count', [1, 4])
            ->count();
        $invalidVersions = DB::table('omr_answer_sheet_template_versions')
            ->whereNull('registration_mode')
            ->orWhereNull('qr_mode')
            ->orWhereNull('page_count')
            ->orWhereNotBetween('page_count', [1, 4])
            ->count();

        if ($invalidDrafts > 0 || $invalidVersions > 0) {
            throw new RuntimeException(
                "OMR paper-policy backfill incomplete: drafts={$invalidDrafts}, versions={$invalidVersions}.",
            );
        }
    }

    /** @return array<string, mixed> */
    private function decodeJson(mixed $value, string $recordType, int $recordId): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }

        try {
            $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "OMR paper-policy backfill found invalid JSON for {$recordType} {$recordId}.",
                previous: $exception,
            );
        }

        if (! is_array($decoded)) {
            throw new RuntimeException(
                "OMR paper-policy backfill expected an object for {$recordType} {$recordId}.",
            );
        }

        return $decoded;
    }

    /** @param list<mixed> $pages */
    private function sequentialPageCount(array $pages, string $recordType, int $recordId): int
    {
        $numbers = [];
        foreach (array_values($pages) as $index => $page) {
            if (! is_array($page)) {
                throw new RuntimeException(
                    "OMR paper-policy backfill found an invalid page for {$recordType} {$recordId}.",
                );
            }
            $numbers[] = isset($page['page_no']) ? (int) $page['page_no'] : $index + 1;
        }

        return $this->assertSequentialNumbers($numbers, $recordType, $recordId);
    }

    /** @param list<int> $numbers */
    private function assertSequentialNumbers(array $numbers, string $recordType, int $recordId): int
    {
        $count = count($numbers);
        if ($count < 1 || $count > 4 || $numbers !== range(1, $count)) {
            throw new RuntimeException(
                "OMR paper-policy backfill found non-sequential or out-of-range pages for {$recordType} {$recordId}: "
                .implode(',', $numbers).'.',
            );
        }

        return $count;
    }
};

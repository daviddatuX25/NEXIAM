<?php

namespace Tests\Support;

use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\Models\Reports\ReportTemplate;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\ResultSheet\ResultSheetTemplateDraft;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Read-only, test-owned snapshots for the document preview transport probe.
 * Fixture creation remains owned by the isolated recovery harness.
 */
final class DocumentPreviewTransportFixture
{
    /**
     * Capture source, draft, and optional queued-render state without mutating
     * the isolated fixture database or storage.
     *
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    public static function readOnlySnapshot(array $case, ?int $printJobId = null): array
    {
        $connection = self::assertIsolatedEnvironment();
        $pdo = $connection->getPdo();
        $queryOnlyWasEnabled = (int) $pdo->query('PRAGMA query_only')->fetchColumn() === 1;

        if (! $queryOnlyWasEnabled) {
            $pdo->exec('PRAGMA query_only = ON');
        }

        try {
            return self::capture($case, $printJobId);
        } finally {
            if (! $queryOnlyWasEnabled) {
                $pdo->exec('PRAGMA query_only = OFF');
            }
        }
    }

    private static function assertIsolatedEnvironment(): Connection
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Document preview snapshots require the isolated testing environment.');
        }

        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Document preview snapshots require the isolated SQLite fixture database.');
        }

        $database = $connection->getConfig('database');
        $storage = app()->storagePath();
        $resolvedDatabase = is_string($database) ? realpath($database) : false;
        $resolvedStorage = realpath($storage);

        if (! is_string($resolvedDatabase)
            || ! is_string($resolvedStorage)
            || dirname($resolvedDatabase) !== $resolvedStorage
            || basename($resolvedDatabase) !== 'recovery.sqlite') {
            throw new RuntimeException('Refusing to inspect a database outside the isolated recovery storage mount.');
        }

        if ((string) config('session.driver') !== 'file'
            || (string) config('cache.default') !== 'file'
            || (string) config('queue.default') !== 'database'
            || (string) config('mail.default') !== 'array') {
            throw new RuntimeException('Refusing to inspect a fixture with non-isolated session, cache, queue, or mail state.');
        }

        foreach (['local', 'public', 'sensitive'] as $diskName) {
            $root = config("filesystems.disks.{$diskName}.root");
            if (! is_string($root) || ! self::pathIsWithin($root, $resolvedStorage)) {
                throw new RuntimeException("Refusing to inspect a fixture with a non-isolated {$diskName} disk.");
            }
        }

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    private static function capture(array $case, ?int $printJobId): array
    {
        $caseId = self::requiredString($case, 'id');
        $surface = self::requiredString($case, 'surface');
        $templateId = filter_var($case['template_id'] ?? null, FILTER_VALIDATE_INT);
        if (! is_int($templateId) || $templateId < 1) {
            throw new RuntimeException('The fixture case must name a positive template_id.');
        }

        $actorId = self::actorId($case);
        $template = match ($surface) {
            'result' => ResultSheetTemplate::query()->find($templateId),
            'passing_report', 'course_quota_qualifiers' => ReportTemplate::query()
                ->where('surface_key', $surface)
                ->find($templateId),
            default => throw new RuntimeException("Unsupported document preview surface [{$surface}]."),
        };

        if ($template === null) {
            return [
                'case_id' => $caseId,
                'surface' => $surface,
                'template_id' => $templateId,
                'actor_id' => $actorId,
                'template' => null,
                'draft' => null,
                'print_job' => self::printJobSnapshot($printJobId),
                'observed_at' => now()->toIso8601String(),
            ];
        }

        $sourcePath = $template->document_path;
        $sourceBytesSha256 = null;
        if (is_string($sourcePath) && $sourcePath !== '') {
            $disk = Storage::disk('local');
            if ($disk->exists($sourcePath)) {
                $sourceBytesSha256 = hash('sha256', (string) $disk->get($sourcePath));
            }
        }

        $builderJson = is_array($template->builder_json ?? null) ? $template->builder_json : null;
        $templateSnapshot = [
            'id' => (int) $template->getKey(),
            'name' => (string) $template->name,
            'surface' => $surface,
            'source_kind' => (string) ($template->source_kind ?? ''),
            'builder_schema_version' => $template->builder_schema_version,
            'compiler_version' => $template->compiler_version,
            'is_active' => (bool) $template->is_active,
            'paper_size' => $template->paper_size,
            'orientation' => $template->orientation,
            'document_extension' => $template->document_extension,
            'document_checksum' => $template->document_checksum,
            'source_bytes_sha256' => $sourceBytesSha256,
            'builder_json_sha256' => $builderJson === null
                ? null
                : hash('sha256', json_encode($builderJson, JSON_THROW_ON_ERROR)),
            'template_checksum' => $template instanceof ResultSheetTemplate
                ? $template->printChecksum()
                : $template->sourceChecksum(),
            'lock_version' => $template->lock_version,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];

        $draft = $template instanceof ResultSheetTemplate
            ? self::resultDraftSnapshot($template, $actorId)
            : null;

        $reportDraft = $template instanceof ReportTemplate
            ? self::reportDraftSnapshot($surface, $templateId, $actorId)
            : null;

        return [
            'case_id' => $caseId,
            'surface' => $surface,
            'template_id' => $templateId,
            'actor_id' => $actorId,
            'template' => $templateSnapshot,
            'draft' => $draft ?? $reportDraft,
            'print_job' => self::printJobSnapshot($printJobId),
            'observed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Snapshot only the current actor/surface/template report editor cache entry.
     *
     * @return array<string, mixed>|null
     */
    private static function reportDraftSnapshot(string $surface, int $templateId, ?int $actorId): ?array
    {
        if ($actorId === null) {
            return null;
        }

        $draftId = 'report_draft_'.$surface.'_'.$actorId.'_'.$templateId;
        $draft = Cache::get("report_draft_{$surface}_{$actorId}_{$draftId}");
        if (! is_array($draft)) {
            $draft = Cache::get("report_draft_{$surface}_{$actorId}_{$templateId}");
        }
        if (! is_array($draft)) {
            return null;
        }

        $state = is_array($draft['state'] ?? null) ? $draft['state'] : [];

        return [
            'id' => (string) ($draft['id'] ?? ''),
            'source_template_id' => isset($draft['source_template_id']) ? (int) $draft['source_template_id'] : null,
            'stage' => (int) ($draft['stage'] ?? 1),
            'lock_version' => (int) ($draft['lock_version'] ?? 1),
            'state_sha256' => hash('sha256', json_encode($state, JSON_THROW_ON_ERROR)),
            'updated_at' => isset($draft['updated_at']) ? (string) $draft['updated_at'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private static function actorId(array $case): ?int
    {
        $actorId = filter_var($case['actor_id'] ?? $case['actor']['id'] ?? null, FILTER_VALIDATE_INT);
        if (is_int($actorId) && $actorId > 0) {
            return $actorId;
        }

        $email = $case['actor']['email'] ?? null;
        if (! is_string($email) || $email === '') {
            return null;
        }

        $id = User::query()->where('email', $email)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resultDraftSnapshot(ResultSheetTemplate $template, ?int $actorId): ?array
    {
        $query = ResultSheetTemplateDraft::query()->where('source_template_id', $template->getKey());
        if ($actorId !== null) {
            $query->where('owner_user_id', $actorId);
        }

        $draft = $query->latest('id')->first();
        if ($draft === null) {
            return null;
        }

        $state = is_array($draft->state) ? $draft->state : [];

        return [
            'id' => (int) $draft->getKey(),
            'source_template_id' => $draft->source_template_id === null ? null : (int) $draft->source_template_id,
            'owner_user_id' => (int) $draft->owner_user_id,
            'stage' => (int) $draft->stage,
            'lock_version' => (int) $draft->lock_version,
            'state_sha256' => hash('sha256', json_encode($state, JSON_THROW_ON_ERROR)),
            'updated_at' => $draft->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function printJobSnapshot(?int $printJobId): ?array
    {
        if ($printJobId === null || $printJobId < 1) {
            return null;
        }

        $job = PrintJob::query()->find($printJobId);
        if ($job === null) {
            return ['id' => $printJobId, 'missing' => true];
        }

        $pinned = is_array($job->pinned_input_json) ? $job->pinned_input_json : [];
        $templateLookup = is_array($pinned['template_lookup'] ?? null) ? $pinned['template_lookup'] : [];
        $templateSnapshot = is_array($pinned['template_snapshot'] ?? null) ? $pinned['template_snapshot'] : [];
        $outputManifest = is_array($job->output_manifest) ? $job->output_manifest : [];
        $items = PrintJobItem::query()->where('print_job_id', $job->getKey())->orderBy('item_index')->get();

        return [
            'id' => (int) $job->getKey(),
            'artifact_type' => (string) $job->artifact_type,
            'is_preview' => (bool) $job->is_preview,
            'status' => (string) $job->status,
            'scope_type' => (string) $job->scope_type,
            'scope_id' => (int) $job->scope_id,
            'requested_by_user_id' => $job->requested_by_user_id === null ? null : (int) $job->requested_by_user_id,
            'attempt_count' => (int) ($job->attempt_count ?? 0),
            'claim_present' => is_string($job->claim_token) && $job->claim_token !== '',
            'claim_token_sha256' => is_string($job->claim_token) && $job->claim_token !== ''
                ? hash('sha256', $job->claim_token)
                : null,
            'claimed_at' => $job->claimed_at?->toIso8601String(),
            'claim_expires_at' => $job->claim_expires_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'safe_error' => $job->safe_error,
            'file_checksum' => $job->file_checksum,
            'pinned_input_sha256' => hash('sha256', json_encode($pinned, JSON_THROW_ON_ERROR)),
            'pinned_template_id' => is_numeric($pinned['template_id'] ?? null)
                ? (int) $pinned['template_id']
                : (is_numeric($templateSnapshot['id'] ?? null) ? (int) $templateSnapshot['id'] : null),
            'pinned_template_checksum' => $templateLookup['template_checksum'] ?? null,
            'pinned_document_checksum' => $pinned['source_checksum'] ?? $templateSnapshot['document_checksum'] ?? null,
            'pinned_document_extension' => $pinned['source_extension'] ?? $templateSnapshot['document_extension'] ?? null,
            'output_manifest_sha256' => hash('sha256', json_encode($outputManifest, JSON_THROW_ON_ERROR)),
            'items' => $items->map(static fn (PrintJobItem $item): array => [
                'id' => (int) $item->getKey(),
                'status' => (string) $item->status,
                'attempt_count' => (int) ($item->attempt_count ?? 0),
                'claimed_at' => $item->claimed_at?->toIso8601String(),
                'claim_expires_at' => $item->claim_expires_at?->toIso8601String(),
                'safe_error' => $item->safe_error,
                'file_checksum' => $item->file_checksum,
                'docx_checksum' => $item->docx_checksum,
            ])->values()->all(),
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
        ];
    }

    private static function pathIsWithin(string $path, string $root): bool
    {
        $resolvedRoot = realpath($root);
        $resolvedPath = realpath($path);
        $normalizedRoot = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $resolvedRoot === false ? $root : $resolvedRoot), '/');
        $normalizedPath = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $resolvedPath === false ? $path : $resolvedPath), '/');

        if (! str_starts_with($normalizedRoot, '/') || ! str_starts_with($normalizedPath, '/')) {
            return false;
        }

        if ($normalizedPath === $normalizedRoot) {
            return true;
        }

        $withinRoot = str_starts_with($normalizedPath, $normalizedRoot.'/');
        $containsTraversal = preg_match('~(^|/)\\.\\.?(/|$)~', $normalizedPath) === 1;

        return $withinRoot && ! $containsTraversal;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function requiredString(array $input, string $key): string
    {
        $value = $input[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("The fixture case is missing [{$key}].");
        }

        return trim($value);
    }
}

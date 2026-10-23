<?php

namespace App\Assessment\Actions\Omr;

use App\Models\Assessment\AnswerSheetImportOperation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class InspectAnswerSheetImport
{
    private const LEASE_SECONDS = 600;

    private const REVIEWED_ORIGINAL_SHA256 = 'dec8d98522f7aa968735f1071579afdec64a7a8e67253b4123b4cfd18f6318f8';

    private const REVIEWED_DERIVATIVE_SHA256 = '35bf1fbd5677a31217219ffe8592091493bcc3932d64f152927e9dee6b1870c6';

    private const REVIEWED_LEDGER_SHA256 = 'db4c5c9ba7832b8bd60b23c79bf5af936788e2d35db0f8cd749239f3501c3925';

    public function __construct(
        private readonly ImportAnswerSheetBundle $bundleImporter,
        private readonly AnswerSheetImportAuthorization $authorization,
    ) {}

    public function handle(string $operationId, int $generation): void
    {
        $claim = $this->claim($operationId, $generation);
        if ($claim === null) {
            return;
        }

        try {
            $operation = $claim['operation'];
            $actor = $this->authorization->freshOwner($operation);
            $archivePath = Storage::disk($operation->archive_disk)->path($operation->archive_path);
            $progress = fn (int $done, int $total, string $member): bool => $this->recordProgress(
                $operationId,
                $generation,
                $claim['lease_token'],
                $done,
                $total,
            );

            $inspection = $this->bundleImporter->inspect($archivePath, $progress);
            $issues = [];
            $notes = (array) $inspection['notes'];
            $recovery = $this->reviewedRecovery($operation);
            if ($recovery['issue'] !== null) {
                $issues[] = $recovery['issue'];
            }
            if ($recovery['note'] !== null) {
                $notes[] = $recovery['note'];
            }

            $this->publishReport(
                $operationId,
                $generation,
                $claim['lease_token'],
                $inspection['summary'],
                $issues,
                $notes,
                $issues === [],
                $issues === [] ? null : 'review_required',
                $issues === [] ? null : $issues[0]['cause'],
            );
        } catch (InvalidArgumentException $exception) {
            if (str_starts_with($exception->getMessage(), 'bundle_inspection_superseded:')) {
                return;
            }

            $issue = $this->issueFor($exception, $claim['operation']);
            $this->publishReport(
                $operationId,
                $generation,
                $claim['lease_token'],
                [],
                [$issue],
                [],
                false,
                (string) $issue['code'],
                (string) $issue['cause'],
            );
        } catch (AuthorizationException) {
            $this->finishFailure(
                $operationId,
                $generation,
                $claim['lease_token'],
                'authorization_revoked',
                'Current Answer Sheet access is no longer available. Contact your administrator before trying again.',
            );
        } catch (Throwable $exception) {
            $this->releaseForQueueRetry($operationId, $generation, $claim['lease_token']);
            throw $exception;
        }
    }

    /** @return array{operation: AnswerSheetImportOperation, lease_token: string}|null */
    private function claim(string $operationId, int $generation): ?array
    {
        return DB::transaction(function () use ($operationId, $generation): ?array {
            $operation = AnswerSheetImportOperation::query()
                ->whereKey($operationId)
                ->lockForUpdate()
                ->first();
            if ($operation === null || (int) $operation->generation !== $generation) {
                return null;
            }

            $leaseActive = $operation->lease_expires_at !== null && $operation->lease_expires_at->isFuture();
            if ($operation->phase === AnswerSheetImportOperation::PHASE_CHECKING && $leaseActive) {
                return null;
            }
            if (! in_array($operation->phase, [
                AnswerSheetImportOperation::PHASE_QUEUED,
                AnswerSheetImportOperation::PHASE_CHECKING,
            ], true)) {
                return null;
            }

            $leaseToken = (string) Str::uuid();
            $operation->forceFill([
                'phase' => AnswerSheetImportOperation::PHASE_CHECKING,
                'revision' => (int) $operation->revision + 1,
                'lease_token' => $leaseToken,
                'lease_expires_at' => now()->addSeconds(self::LEASE_SECONDS),
                'last_heartbeat_at' => now(),
                'progress_done' => 0,
                'progress_total' => 0,
                'progress_unit' => 'archive_members',
                'safe_error' => null,
                'error_code' => null,
            ])->save();

            return ['operation' => $operation->fresh(), 'lease_token' => $leaseToken];
        }, attempts: 3);
    }

    private function recordProgress(string $operationId, int $generation, string $leaseToken, int $done, int $total): bool
    {
        return DB::transaction(function () use ($operationId, $generation, $leaseToken, $done, $total): bool {
            $updated = AnswerSheetImportOperation::query()
                ->whereKey($operationId)
                ->where('generation', $generation)
                ->where('phase', AnswerSheetImportOperation::PHASE_CHECKING)
                ->where('lease_token', $leaseToken)
                ->update([
                    'progress_done' => $done,
                    'progress_total' => $total,
                    'progress_unit' => 'archive_members',
                    'revision' => DB::raw('revision + 1'),
                    'lease_expires_at' => now()->addSeconds(self::LEASE_SECONDS),
                    'last_heartbeat_at' => now(),
                    'updated_at' => now(),
                ]);

            return $updated === 1;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $summary @param list<array<string, mixed>> $issues @param list<array<string, mixed>> $notes */
    private function publishReport(
        string $operationId,
        int $generation,
        string $leaseToken,
        array $summary,
        array $issues,
        array $notes,
        bool $canRestore,
        ?string $errorCode,
        ?string $safeError,
    ): void {
        $recoveryProvenance = null;
        foreach ($notes as &$note) {
            if (is_array($note['recovery_provenance'] ?? null)) {
                $recoveryProvenance = $note['recovery_provenance'];
                unset($note['recovery_provenance']);
            }
        }
        unset($note);

        $report = [
            'summary' => $summary,
            'issues' => array_values($issues),
            'notes' => array_values($notes),
            'recovery_provenance' => $recoveryProvenance,
            'can_restore' => $canRestore,
        ];
        $reportSha = hash('sha256', json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        DB::transaction(function () use ($operationId, $generation, $leaseToken, $report, $reportSha, $canRestore, $errorCode, $safeError): void {
            $operation = AnswerSheetImportOperation::query()
                ->whereKey($operationId)
                ->lockForUpdate()
                ->first();
            if ($operation === null
                || (int) $operation->generation !== $generation
                || $operation->phase !== AnswerSheetImportOperation::PHASE_CHECKING
                || ! hash_equals((string) $operation->lease_token, $leaseToken)) {
                return;
            }

            $operation->forceFill([
                'phase' => $canRestore
                    ? AnswerSheetImportOperation::PHASE_READY_TO_RESTORE
                    : AnswerSheetImportOperation::PHASE_NEEDS_REVIEW,
                'revision' => (int) $operation->revision + 1,
                'review_revision' => (int) $operation->review_revision + 1,
                'parser_version' => 'sheet-bundle-v1',
                'report_json' => $report,
                'report_sha256' => $reportSha,
                'progress_done' => (int) $operation->progress_total,
                'progress_unit' => 'archive_members',
                'error_code' => $errorCode,
                'safe_error' => $safeError,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
            ])->save();
        }, attempts: 3);
    }

    private function finishFailure(
        string $operationId,
        int $generation,
        string $leaseToken,
        string $errorCode,
        string $safeError,
    ): void {
        AnswerSheetImportOperation::query()
            ->whereKey($operationId)
            ->where('generation', $generation)
            ->where('phase', AnswerSheetImportOperation::PHASE_CHECKING)
            ->where('lease_token', $leaseToken)
            ->update([
                'phase' => AnswerSheetImportOperation::PHASE_FAILED,
                'revision' => DB::raw('revision + 1'),
                'error_code' => $errorCode,
                'safe_error' => $safeError,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function releaseForQueueRetry(string $operationId, int $generation, string $leaseToken): void
    {
        AnswerSheetImportOperation::query()
            ->whereKey($operationId)
            ->where('generation', $generation)
            ->where('phase', AnswerSheetImportOperation::PHASE_CHECKING)
            ->where('lease_token', $leaseToken)
            ->update([
                'phase' => AnswerSheetImportOperation::PHASE_QUEUED,
                'revision' => DB::raw('revision + 1'),
                'error_code' => 'inspection_worker_error',
                'safe_error' => 'The archive check stopped unexpectedly. It will be tried again or you can retry it later.',
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /** @return array{issue: ?array<string, mixed>, note: ?array<string, mixed>} */
    private function reviewedRecovery(AnswerSheetImportOperation $operation): array
    {
        $archiveHash = (string) $operation->archive_sha256;
        $ledgerHash = $operation->ledger_sha256 === null ? null : (string) $operation->ledger_sha256;

        if ($archiveHash === self::REVIEWED_ORIGINAL_SHA256) {
            return [
                'issue' => $this->issue(
                    'reviewed_replacement_required',
                    'The original backup has known damaged export fields and cannot be restored as uploaded.',
                    'Select the separately reviewed replacement archive and its matching recovery ledger. No automatic repairs are made.',
                ),
                'note' => null,
            ];
        }

        if ($archiveHash === self::REVIEWED_DERIVATIVE_SHA256) {
            if ($ledgerHash !== self::REVIEWED_LEDGER_SHA256 || $operation->ledger_path === null) {
                return [
                    'issue' => $this->issue(
                        'reviewed_recovery_ledger_required',
                        'This reviewed replacement archive requires its matching recovery ledger.',
                        'Upload the exact reviewed ledger supplied with this replacement archive, then create a linked replacement attempt.',
                    ),
                    'note' => null,
                ];
            }

            $ledgerBytes = Storage::disk($operation->ledger_disk)->get($operation->ledger_path);
            if (! is_string($ledgerBytes) || ! hash_equals(self::REVIEWED_LEDGER_SHA256, hash('sha256', $ledgerBytes))) {
                return [
                    'issue' => $this->issue(
                        'reviewed_recovery_ledger_invalid',
                        'The uploaded recovery ledger does not match the reviewed replacement archive.',
                        'Select the exact reviewed ledger and matching replacement archive; arbitrary ledgers are not accepted as proof.',
                    ),
                    'note' => null,
                ];
            }

            $ledger = json_decode($ledgerBytes, true);
            if (! is_array($ledger)
                || ($ledger['original_zip_sha256'] ?? null) !== self::REVIEWED_ORIGINAL_SHA256
                || ($ledger['recovered_zip_sha256'] ?? null) !== self::REVIEWED_DERIVATIVE_SHA256
                || ($ledger['original_unchanged'] ?? null) !== true) {
                return [
                    'issue' => $this->issue(
                        'reviewed_recovery_ledger_invalid',
                        'The reviewed ledger does not identify the protected original and replacement archive.',
                        'Select the exact reviewed ledger and matching replacement archive; arbitrary ledgers are not accepted as proof.',
                    ),
                    'note' => null,
                ];
            }

            return [
                'issue' => null,
                'note' => [
                    'code' => 'reviewed_recovery_provenance',
                    'severity' => 'information',
                    'cause' => 'This archive matches the separately reviewed replacement and its verified recovery ledger.',
                    'action' => 'The original remains unchanged. Twenty-two redundant raw bounds were deterministically reconstructed; byte-exact original authoring values are not claimed.',
                    'recovery_provenance' => [
                        'original_archive_sha256' => self::REVIEWED_ORIGINAL_SHA256,
                        'replacement_archive_sha256' => self::REVIEWED_DERIVATIVE_SHA256,
                        'recovery_ledger_sha256' => self::REVIEWED_LEDGER_SHA256,
                        'original_unchanged' => (bool) $ledger['original_unchanged'],
                        'native_mirrors_and_uncorrupted_values_agree' => (bool) ($ledger['native_mirrors_and_uncorrupted_values_agree'] ?? false),
                        'uncorrupted_mapping_disagreements' => (array) ($ledger['uncorrupted_mapping_disagreements'] ?? []),
                        'repaired_non_asset_scalars' => (array) ($ledger['repaired_non_asset_scalars'] ?? []),
                        'raw_input_reconstruction_limit' => (array) ($ledger['raw_input_reconstruction_limit'] ?? []),
                        'limits' => (string) ($ledger['limits'] ?? ''),
                    ],
                ],
            ];
        }

        if ($ledgerHash !== null) {
            return [
                'issue' => $this->issue(
                    'unrecognized_recovery_ledger',
                    'This recovery ledger is not the reviewed ledger for the supplied archive.',
                    'Remove the unverified ledger and use a supported clean archive, or select the exact reviewed replacement and ledger.',
                ),
                'note' => null,
            ];
        }

        return ['issue' => null, 'note' => null];
    }

    private function issueFor(InvalidArgumentException $exception, AnswerSheetImportOperation $operation): array
    {
        $message = $exception->getMessage();
        $code = str_contains($message, 'damaged numeric or text fields')
            ? 'legacy_scalar_corruption'
            : (strstr($message, ':', true) ?: 'archive_invalid');
        $member = null;
        if (preg_match('/(?:file|SVG reference|SVG) "([^"\r\n]{1,200})"/i', $message, $matches) === 1) {
            $member = $matches[1];
        }

        if ((string) $operation->archive_sha256 === self::REVIEWED_ORIGINAL_SHA256) {
            $code = 'reviewed_replacement_required';
            $cause = 'The original backup has known damaged export fields and cannot be restored as uploaded.';
            $remedy = 'Select the separately reviewed replacement archive and its matching recovery ledger. No automatic repairs are made.';
        } else {
            [$cause, $remedy] = match ($code) {
                'bundle_archive_corrupt', 'bundle_manifest_missing', 'bundle_manifest_unreadable' => [
                    'The archive is incomplete or cannot be opened safely.',
                    'Choose the original unmodified ZIP or a reviewed replacement archive. This importer does not repair unknown corruption.',
                ],
                'bundle_checksum_mismatch', 'bundle_file_missing', 'bundle_size_limit_exceeded', 'bundle_entry_limit_exceeded' => [
                    'An archive member is missing, too large, or does not match its checksum.',
                    'Re-export the answer sheet or choose a verified archive copy. Do not continue with partial files.',
                ],
                'bundle_path_traversal', 'bundle_extension_prohibited', 'bundle_svg_invalid', 'bundle_svg_prohibited' => [
                    'The archive contains a member path or image type that is not safe to restore.',
                    'Re-export from a trusted source with safe member paths and images.',
                ],
                'bundle_page_manifest_invalid', 'bundle_page_identity_invalid', 'bundle_asset_page_invalid', 'bundle_authoring_page_invalid', 'bundle_native_pages_invalid' => [
                    'Page identities are missing, repeated, or outside the declared Answer Sheet.',
                    'Choose a complete archive with consistent page assignments. Import will not renumber or move pages.',
                ],
                'bundle_physical_inventory_invalid' => [
                    'Printed-section locations do not agree with the archive’s own page, guide, label, or option data.',
                    'Correct the source archive and re-export it. No section, guide, or option is guessed.',
                ],
                'legacy_scalar_corruption' => [
                    'This older export contains damaged geometry values in fields that are not asset references.',
                    'Use the separately reviewed replacement archive and matching ledger, or re-export from a compatible version. No guessed repair is available.',
                ],
                default => [
                    'The archive does not meet the supported Answer Sheet bundle format.',
                    'Re-export the Answer Sheet or choose a reviewed replacement archive. The archive was not restored.',
                ],
            };
        }

        return $this->issue($code, $cause, $remedy, $member);
    }

    /** @return array<string, mixed> */
    private function issue(string $code, string $cause, string $remedy, ?string $member = null): array
    {
        return [
            'id' => hash('sha256', $code.'|'.($member ?? 'archive')),
            'code' => $code,
            'severity' => 'blocking',
            'artifact' => 'archive',
            'member' => $member,
            'page_no' => $member !== null && preg_match('/(?:page|p)[-_]?(\d+)/i', $member, $matches) === 1
                ? (int) $matches[1]
                : null,
            'cause' => $cause,
            'evidence' => $member === null ? 'The supplied archive could not be validated.' : 'The affected member is '.$member.'.',
            'remedy' => $remedy,
            'allowed_actions' => ['replace_source', 'cancel'],
        ];
    }
}

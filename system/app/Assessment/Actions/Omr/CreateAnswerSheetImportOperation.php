<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Support\MaterialOwnerAccess;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\InspectAnswerSheetImportJob;
use App\Models\Assessment\AnswerSheetImportOperation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CreateAnswerSheetImportOperation
{
    private const PRIVATE_DISK = 'sensitive';

    public function __construct(
        private readonly AnswerSheetImportAuthorization $authorization,
        private readonly MaterialOwnerAccess $materialOwnerAccess,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        UploadedFile $archive,
        ?UploadedFile $recoveryLedger,
        User $actor,
        ?AnswerSheetImportOperation $replacementFor = null,
    ): AnswerSheetImportOperation {
        $institutionId = $replacementFor === null
            ? $this->materialOwnerAccess->institutionId($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR)
            : $this->authorization->assertOwner($actor, $replacementFor);

        if ($replacementFor !== null && ! in_array($replacementFor->phase, [
            AnswerSheetImportOperation::PHASE_NEEDS_REVIEW,
            AnswerSheetImportOperation::PHASE_FAILED,
            AnswerSheetImportOperation::PHASE_CANCELLED,
        ], true)) {
            throw new RuntimeException('This import is not ready for a replacement archive.');
        }

        $id = (string) Str::uuid();
        $directory = 'answer-sheet-imports/'.$id;
        $archivePath = $directory.'/source.zip';
        $ledgerPath = $recoveryLedger === null ? null : $directory.'/recovery-ledger.json';
        $archiveHash = hash_file('sha256', (string) $archive->getRealPath());
        $ledgerHash = $recoveryLedger === null
            ? null
            : hash_file('sha256', (string) $recoveryLedger->getRealPath());

        if (! is_string($archiveHash) || ($recoveryLedger !== null && ! is_string($ledgerHash))) {
            throw new RuntimeException('The uploaded file could not be read safely. Select the file again.');
        }

        $disk = Storage::disk(self::PRIVATE_DISK);
        try {
            $storedArchivePath = $archive->storeAs($directory, 'source.zip', self::PRIVATE_DISK);
            $storedLedgerPath = $recoveryLedger?->storeAs($directory, 'recovery-ledger.json', self::PRIVATE_DISK);
            if (! is_string($storedArchivePath)
                || ($recoveryLedger !== null && ! is_string($storedLedgerPath))) {
                throw new RuntimeException('The uploaded archive could not be stored privately. Retry the upload.');
            }
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);
            throw $exception;
        }

        $originalFilename = basename(str_replace('\\', '/', $archive->getClientOriginalName()));
        $originalFilename = substr((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $originalFilename), 0, 255);

        try {
            return DB::transaction(function () use (
                $actor,
                $institutionId,
                $id,
                $archive,
                $recoveryLedger,
                $archiveHash,
                $ledgerHash,
                $storedArchivePath,
                $storedLedgerPath,
                $originalFilename,
                $replacementFor,
            ): AnswerSheetImportOperation {
                $replacementForId = null;
                if ($replacementFor !== null) {
                    $parent = AnswerSheetImportOperation::query()
                        ->whereKey($replacementFor->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $this->authorization->assertOwner($actor, $parent);
                    if (! in_array($parent->phase, [
                        AnswerSheetImportOperation::PHASE_NEEDS_REVIEW,
                        AnswerSheetImportOperation::PHASE_FAILED,
                        AnswerSheetImportOperation::PHASE_CANCELLED,
                    ], true)) {
                        throw new RuntimeException('This import is no longer ready for a replacement archive.');
                    }
                    $replacementForId = (string) $parent->id;
                }
                $operation = AnswerSheetImportOperation::query()->create([
                    'id' => $id,
                    'institution_id' => $institutionId,
                    'owner_user_id' => $actor->getKey(),
                    'replacement_for_id' => $replacementForId,
                    'phase' => AnswerSheetImportOperation::PHASE_QUEUED,
                    'revision' => 1,
                    'generation' => 1,
                    'review_revision' => 0,
                    'original_filename' => $originalFilename !== '' ? $originalFilename : 'answer-sheet-bundle.zip',
                    'archive_disk' => self::PRIVATE_DISK,
                    'archive_path' => $storedArchivePath,
                    'archive_mime' => (string) ($archive->getMimeType() ?? 'application/zip'),
                    'archive_bytes' => (int) $archive->getSize(),
                    'archive_sha256' => $archiveHash,
                    'ledger_disk' => $recoveryLedger === null ? null : self::PRIVATE_DISK,
                    'ledger_path' => $storedLedgerPath,
                    'ledger_bytes' => $recoveryLedger === null ? null : (int) $recoveryLedger->getSize(),
                    'ledger_sha256' => $recoveryLedger === null ? null : $ledgerHash,
                    'progress_done' => 0,
                    'progress_total' => 0,
                ]);

                $this->recordAuditEvent->handle('omr.answer_sheet_import_queued', [
                    'operation_id' => $operation->id,
                    'institution_id' => $institutionId,
                    'archive_bytes' => (int) $archive->getSize(),
                    'archive_sha256' => $archiveHash,
                    'replacement_for_id' => $replacementForId,
                ], (string) $actor->getKey());

                InspectAnswerSheetImportJob::dispatch((string) $operation->id, 1)->afterCommit();

                return $operation;
            }, attempts: 3);
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);
            throw $exception;
        }
    }
}

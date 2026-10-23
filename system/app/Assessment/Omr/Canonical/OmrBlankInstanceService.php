<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\OmrQrMode;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBlankInstancePage;
use App\Models\Assessment\OmrBlankInstancePageClaim;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrPaperReproductionAuthorization;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function in_array;
use function random_bytes;

final class OmrBlankInstanceException extends RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $context = [])
    {
        parent::__construct($errorCode.': '.$message);
    }
}

final class OmrBlankInstanceService
{
    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(
        private readonly OmrQrPayload $qr,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function createPrintedSet(
        OmrAnswerSheetTemplateVersion $version,
        User $actor,
        int $pageCount,
        array $pageArtifactHashes,
        ?int $printJobId = null,
        ?string $keyId = null,
        ?string $instanceCode = null,
        ?string $displayCode = null,
        string $copyPolicy = OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
        int $intendedCopyCount = 1,
        ?int $institutionId = null,
        ?int $academicCycleId = null,
    ): OmrBlankInstance {
        $scope = $this->resolveScope($actor, $institutionId, $academicCycleId);
        if ($version->status !== OmrAnswerSheetTemplateVersion::STATUS_COMPILED
            && $version->status !== OmrAnswerSheetTemplateVersion::STATUS_ACTIVE) {
            throw new OmrBlankInstanceException('template_not_active', 'Blank instances require sealed printable material.');
        }
        if (! in_array($copyPolicy, [
            OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
            OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER,
        ], true)) {
            throw new OmrBlankInstanceException('copy_policy_invalid', 'The paper copy policy is unsupported.');
        }
        if ($intendedCopyCount < 1) {
            throw new OmrBlankInstanceException('copy_count_invalid', 'The intended copy count must be positive.');
        }
        if ($pageCount < 1 || count($pageArtifactHashes) !== $pageCount) {
            throw new OmrBlankInstanceException('page_set_incomplete', 'A complete page artifact is required for every page.');
        }
        foreach ($pageArtifactHashes as $hashValue) {
            if (! is_string($hashValue) || preg_match('/^[a-f0-9]{64}$/', $hashValue) !== 1) {
                throw new OmrBlankInstanceException('print_artifact_invalid', 'Every page artifact must have a SHA-256 hash.');
            }
        }
        $this->assertSignedPrintVersion($version, $pageCount);
        $keyId ??= (string) config('omr.qr-keyring.current', 'dev-v1');

        return DB::transaction(function () use (
            $version,
            $actor,
            $pageCount,
            $pageArtifactHashes,
            $printJobId,
            $keyId,
            $instanceCode,
            $displayCode,
            $copyPolicy,
            $intendedCopyCount,
            $scope,
        ): OmrBlankInstance {
            $lockedVersion = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($lockedVersion->status !== OmrAnswerSheetTemplateVersion::STATUS_COMPILED
                && $lockedVersion->status !== OmrAnswerSheetTemplateVersion::STATUS_ACTIVE) {
                throw new OmrBlankInstanceException('template_not_active', 'Blank instances require sealed printable material.');
            }
            $this->assertSignedPrintVersion($lockedVersion, $pageCount);
            $pageSetNo = ((int) OmrBlankInstance::query()->where('template_version_id', $lockedVersion->id)->max('page_set_no')) + 1;
            $instanceCode ??= $this->uniqueInstanceCode();
            $displayCode ??= $this->uniqueDisplayCode($instanceCode);
            $payloads = [];
            $pages = [];
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $payload = $this->qr->sign($instanceCode, $pageNo, $pageCount, $keyId);
                $payloadHash = $this->qr->payloadHash($payload);
                $payloads[] = $payloadHash;
                $pages[] = [
                    'page_no' => $pageNo,
                    'qr_payload_hash' => $payloadHash,
                    'signing_key_id' => $keyId,
                    'print_artifact_hash' => $pageArtifactHashes[$pageNo - 1],
                    'status' => OmrBlankInstancePage::STATUS_PRINTED,
                ];
            }
            $instance = OmrBlankInstance::query()->create([
                'template_version_id' => $lockedVersion->id,
                'institution_id' => $scope['institution_id'],
                'academic_cycle_id' => $scope['academic_cycle_id'],
                'instance_code' => $instanceCode,
                'display_code' => $displayCode,
                'page_set_no' => $pageSetNo,
                'page_count' => $pageCount,
                'copy_policy' => $copyPolicy,
                'intended_copy_count' => $intendedCopyCount,
                'status' => OmrBlankInstance::STATUS_PRINTED,
                'print_job_id' => $printJobId,
                'qr_manifest_hash' => $this->qr->manifestHash($payloads),
                'signing_key_id' => $keyId,
                'signature_version' => OmrQrPayload::SIGNATURE_VERSION,
                'created_by_user_id' => $actor->id,
            ]);
            $instance->pages()->createMany($pages);

            return $instance->fresh('pages');
        });
    }

    private function assertSignedPrintVersion(OmrAnswerSheetTemplateVersion $version, int $pageCount): void
    {
        if ($version->qr_mode !== OmrQrMode::Signed) {
            throw new OmrBlankInstanceException('qr_mode_disabled', 'QR-disabled templates cannot issue signed blank instances.');
        }
        if ($pageCount < 1 || $pageCount > 4 || $pageCount !== $version->pageCount()) {
            throw new OmrBlankInstanceException('version_page_count_mismatch', 'Blank-instance pages must match the immutable template-version page count.');
        }

        $contract = OmrBlankPrintContract::fromVersion($version);
        if ($contract->qrMode !== OmrQrMode::Signed || count($contract->compiledPages) !== $pageCount) {
            throw new OmrBlankInstanceException('version_page_count_mismatch', 'Blank-instance pages must match the immutable compiled page list.');
        }
    }

    /**
     * @return array{institution_id: ?int, academic_cycle_id: ?int}
     */
    private function resolveScope(User $actor, ?int $institutionId, ?int $academicCycleId): array
    {
        if (($institutionId === null) !== ($academicCycleId === null)) {
            throw new OmrBlankInstanceException('scope_incomplete', 'Both institution and academic-cycle scope are required.');
        }

        if ($institutionId !== null && $academicCycleId !== null) {
            $cycle = AcademicCycle::query()
                ->whereKey($academicCycleId)
                ->where('institution_id', $institutionId)
                ->first();
            if ($cycle === null) {
                throw new OmrBlankInstanceException('scope_invalid', 'The blank instance scope is not a valid academic cycle.');
            }

            return ['institution_id' => $institutionId, 'academic_cycle_id' => $academicCycleId];
        }

        $profile = $actor->staffProfile;
        if ($profile === null) {
            return ['institution_id' => null, 'academic_cycle_id' => null];
        }

        if ($profile->institution_id === null || trim((string) $profile->active_cycle_code) === '') {
            throw new OmrBlankInstanceException('scope_unavailable', 'The issuer has no complete institution and academic-cycle scope.');
        }

        $cycle = AcademicCycle::query()
            ->where('institution_id', (int) $profile->institution_id)
            ->where('code', trim((string) $profile->active_cycle_code))
            ->first();
        if ($cycle === null) {
            throw new OmrBlankInstanceException('scope_unavailable', 'The issuer academic-cycle scope is not registered.');
        }

        return [
            'institution_id' => (int) $cycle->institution_id,
            'academic_cycle_id' => (int) $cycle->id,
        ];
    }

    public function registerCapture(
        OmrBlankInstance $instance,
        int $pageNo,
        OmrCaptureSheetPage $capturedPage,
        User $actor,
        ?int $examSessionId = null,
    ): OmrBlankInstance {
        return DB::transaction(function () use (
            $instance,
            $pageNo,
            $capturedPage,
            $actor,
            $examSessionId,
        ): OmrBlankInstance {
            $locked = OmrBlankInstance::query()->lockForUpdate()->findOrFail($instance->id);
            $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($locked->template_version_id);
            $this->assertSignedPrintVersion($version, (int) $locked->page_count);
            $registeredCapturePage = OmrCaptureSheetPage::query()
                ->with(['sheet.batch.gradingSession.examSession.academicCycle', 'sheet.admissionAttempt'])
                ->lockForUpdate()
                ->find($capturedPage->id);
            if ($registeredCapturePage === null) {
                throw new OmrBlankInstanceException('capture_page_invalid', 'The capture page must be a persisted page in the capture batch.');
            }
            $this->assertCapturePageMatchesInstance($locked, $pageNo, $registeredCapturePage, $examSessionId);
            $capturedPage = $registeredCapturePage;
            if (in_array($locked->status, [OmrBlankInstance::STATUS_SUPERSEDED, OmrBlankInstance::STATUS_VOID], true)) {
                throw new OmrBlankInstanceException('superseded_instance', 'This blank instance is no longer capturable.');
            }
            $page = $locked->pages()->lockForUpdate()->where('page_no', $pageNo)->first();
            if ($page === null) {
                throw new OmrBlankInstanceException('page_missing_from_set', 'The captured page is not part of the blank set.');
            }
            if ($page->status === OmrBlankInstancePage::STATUS_VOID) {
                throw new OmrBlankInstanceException('lifecycle_invalid', 'This blank-instance page is void.');
            }

            $existingClaim = OmrBlankInstancePageClaim::query()
                ->where('capture_sheet_page_id', $capturedPage->id)
                ->first();
            if ($existingClaim !== null) {
                if ((int) $existingClaim->blank_instance_page_id !== (int) $page->id) {
                    throw new OmrBlankInstanceException('capture_claim_conflict', 'This capture page is already bound to another issued Answer Sheet page.');
                }

                return $locked->fresh('pages');
            }
            if ($locked->isReusableMaster()) {
                OmrBlankInstancePageClaim::query()->create([
                    'blank_instance_page_id' => $page->id,
                    'capture_sheet_page_id' => $capturedPage->id,
                    'reproduction_authorization_id' => null,
                    'claim_kind' => OmrBlankInstancePageClaim::KIND_REUSABLE_COPY,
                    'created_by_user_id' => $actor->id,
                ]);

                return $locked->fresh('pages');
            }

            if ($page->claims()->doesntExist() && $page->status !== OmrBlankInstancePage::STATUS_CAPTURED) {
                OmrBlankInstancePageClaim::query()->create([
                    'blank_instance_page_id' => $page->id,
                    'capture_sheet_page_id' => $capturedPage->id,
                    'reproduction_authorization_id' => null,
                    'claim_kind' => OmrBlankInstancePageClaim::KIND_ORIGINAL,
                    'created_by_user_id' => $actor->id,
                ]);
                $page->forceFill([
                    'status' => OmrBlankInstancePage::STATUS_CAPTURED,
                    'captured_page_id' => $capturedPage->id,
                ])->save();
                $allCaptured = $locked->pages()->where('status', '!=', OmrBlankInstancePage::STATUS_CAPTURED)->doesntExist();
                if ($allCaptured) {
                    $locked->forceFill(['status' => OmrBlankInstance::STATUS_CAPTURED])->save();
                }

                return $locked->fresh('pages');
            }

            $authorization = $examSessionId === null
                ? null
                : OmrPaperReproductionAuthorization::query()
                    ->where('exam_session_id', $examSessionId)
                    ->where('blank_instance_page_id', $page->id)
                    ->where('status', OmrPaperReproductionAuthorization::STATUS_OPEN)
                    ->where('expires_at', '>', now())
                    ->whereColumn('used_claims', '<', 'maximum_claims')
                    ->lockForUpdate()
                    ->first();
            if ($authorization === null) {
                throw new OmrBlankInstanceException('page_already_captured', 'This unique page has already been captured and has no available reproduction authorization.');
            }

            OmrBlankInstancePageClaim::query()->create([
                'blank_instance_page_id' => $page->id,
                'capture_sheet_page_id' => $capturedPage->id,
                'reproduction_authorization_id' => $authorization->id,
                'claim_kind' => OmrBlankInstancePageClaim::KIND_AUTHORIZED_COPY,
                'created_by_user_id' => $actor->id,
            ]);
            $usedClaims = $authorization->used_claims + 1;
            $authorization->forceFill([
                'used_claims' => $usedClaims,
                'status' => $usedClaims >= $authorization->maximum_claims
                    ? OmrPaperReproductionAuthorization::STATUS_RECONCILED
                    : OmrPaperReproductionAuthorization::STATUS_OPEN,
                'reconciled_by_user_id' => $usedClaims >= $authorization->maximum_claims ? $actor->id : null,
                'reconciled_at' => $usedClaims >= $authorization->maximum_claims ? now() : null,
                'lock_version' => $authorization->lock_version + 1,
            ])->save();
            if ($usedClaims >= $authorization->maximum_claims) {
                $this->recordAuditEvent->handle('grading.omr.paper_reproduction_reconciled', [
                    'authorization_id' => $authorization->id,
                    'exam_session_id' => $authorization->exam_session_id,
                    'blank_instance_page_id' => $page->id,
                    'maximum_claims' => $authorization->maximum_claims,
                    'used_claims' => $usedClaims,
                ], (string) $actor->id);
            }

            return $locked->fresh('pages');
        });
    }

    private function assertCapturePageMatchesInstance(
        OmrBlankInstance $instance,
        int $pageNo,
        OmrCaptureSheetPage $capturePage,
        ?int $examSessionId,
    ): void {
        if ((int) $capturePage->page_no !== $pageNo) {
            throw new OmrBlankInstanceException('capture_page_mismatch', 'The capture page number does not match the signed blank-instance page.');
        }

        $sheet = $capturePage->sheet;
        $batch = $sheet?->batch;
        if ($batch === null || (int) $batch->template_version_id !== (int) $instance->template_version_id) {
            throw new OmrBlankInstanceException('template_mismatch', 'The capture batch does not use this blank instance template version.');
        }

        $examSession = $batch->gradingSession?->examSession;
        $cycle = $examSession?->academicCycle;
        $attempt = $sheet?->admissionAttempt;
        $institutionId = $instance->institution_id;
        $academicCycleId = $instance->academic_cycle_id;
        if ($examSession === null
            || $cycle === null
            || $institutionId === null
            || $academicCycleId === null
            || (int) $examSession->academic_cycle_id !== (int) $academicCycleId
            || (int) $cycle->institution_id !== (int) $institutionId
            || ($attempt !== null
                && ((int) $attempt->institution_id !== (int) $institutionId
                    || (int) $attempt->academic_cycle_id !== (int) $academicCycleId))
            || ($examSessionId !== null && (int) $examSession->id !== $examSessionId)) {
            throw new OmrBlankInstanceException('capture_scope_mismatch', 'The capture batch and applicant must match the blank instance institution and academic cycle.');
        }
    }

    public function void(OmrBlankInstance $instance): OmrBlankInstance
    {
        return DB::transaction(function () use ($instance): OmrBlankInstance {
            $locked = OmrBlankInstance::query()->lockForUpdate()->findOrFail($instance->id);
            if ($locked->status !== OmrBlankInstance::STATUS_PRINTED || $locked->claims()->exists()) {
                throw new OmrBlankInstanceException('lifecycle_invalid', 'Only printed sets may be voided.');
            }
            $this->voidOpenReproductionAuthorizations($locked);
            $locked->forceFill(['status' => OmrBlankInstance::STATUS_VOID])->save();
            $locked->pages()->where('status', OmrBlankInstancePage::STATUS_PRINTED)->update(['status' => OmrBlankInstancePage::STATUS_VOID]);

            return $locked->fresh('pages');
        });
    }

    public function supersede(OmrBlankInstance $instance, OmrBlankInstance $replacement): OmrBlankInstance
    {
        return DB::transaction(function () use ($instance, $replacement): OmrBlankInstance {
            $locked = OmrBlankInstance::query()->lockForUpdate()->findOrFail($instance->id);
            if ($locked->status !== OmrBlankInstance::STATUS_PRINTED || $locked->claims()->exists()) {
                throw new OmrBlankInstanceException('lifecycle_invalid', 'Only printed sets may be superseded.');
            }
            $this->voidOpenReproductionAuthorizations($locked);
            $locked->forceFill([
                'status' => OmrBlankInstance::STATUS_SUPERSEDED,
                'superseded_by_instance_id' => $replacement->id,
            ])->save();

            return $locked->fresh('pages');
        });
    }

    private function voidOpenReproductionAuthorizations(OmrBlankInstance $instance): void
    {
        OmrPaperReproductionAuthorization::query()
            ->whereHas(
                'blankInstancePage',
                fn ($query) => $query->where('instance_id', $instance->id),
            )
            ->where('status', OmrPaperReproductionAuthorization::STATUS_OPEN)
            ->update([
                'status' => OmrPaperReproductionAuthorization::STATUS_VOID,
                'updated_at' => now(),
            ]);
    }

    public function resolveDisplayCode(string $displayCode): OmrBlankInstance
    {
        return OmrBlankInstance::query()->where('display_code', strtoupper(trim($displayCode)))->firstOrFail();
    }

    public function newInstanceCode(): string
    {
        return $this->uniqueInstanceCode();
    }

    public function displayCodeFor(string $instanceCode): string
    {
        return $this->uniqueDisplayCode($instanceCode);
    }

    private function uniqueInstanceCode(): string
    {
        do {
            $code = 'NI-'.$this->crockford(random_bytes(16));
        } while (OmrBlankInstance::query()->where('instance_code', $code)->exists());

        return $code;
    }

    private function uniqueDisplayCode(string $instanceCode): string
    {
        $base = substr(str_replace('NI-', '', $instanceCode), 0, 10);
        $code = 'NI-'.$base.$this->checkDigit($base);
        if (OmrBlankInstance::query()->where('display_code', $code)->exists()) {
            return $this->uniqueDisplayCode($this->uniqueInstanceCode());
        }

        return $code;
    }

    private function crockford(string $bytes): string
    {
        $buffer = 0;
        $bits = 2;
        $output = '';
        foreach (unpack('C*', $bytes) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $output .= self::CROCKFORD[($buffer >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $output .= self::CROCKFORD[($buffer << (5 - $bits)) & 31];
        }

        return substr($output, 0, 26);
    }

    private function checkDigit(string $base): string
    {
        $sum = 0;
        foreach (str_split($base) as $character) {
            $sum += strpos(self::CROCKFORD, $character);
        }

        return self::CROCKFORD[$sum % strlen(self::CROCKFORD)];
    }
}

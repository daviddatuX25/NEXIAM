<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBlankInstancePage;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function array_values;
use function hash;
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

    public function __construct(private readonly OmrQrPayload $qr)
    {
    }

    public function createPrintedSet(
        OmrAnswerSheetTemplateVersion $version,
        User $actor,
        int $pageCount,
        array $pageArtifactHashes,
        ?int $printJobId = null,
        ?string $keyId = null,
        ?string $instanceCode = null,
        ?string $displayCode = null,
    ): OmrBlankInstance {
        if ($version->status !== OmrAnswerSheetTemplateVersion::STATUS_ACTIVE) {
            throw new OmrBlankInstanceException('template_not_active', 'Blank instances can only be printed from an active template.');
        }
        if ($pageCount < 1 || count($pageArtifactHashes) !== $pageCount) {
            throw new OmrBlankInstanceException('page_set_incomplete', 'A complete page artifact is required for every page.');
        }
        foreach ($pageArtifactHashes as $hashValue) {
            if (! is_string($hashValue) || preg_match('/^[a-f0-9]{64}$/', $hashValue) !== 1) {
                throw new OmrBlankInstanceException('print_artifact_invalid', 'Every page artifact must have a SHA-256 hash.');
            }
        }
        $keyId ??= (string) config('omr.qr-keyring.current', 'dev-v1');

        return DB::transaction(function () use ($version, $actor, $pageCount, $pageArtifactHashes, $printJobId, $keyId, $instanceCode, $displayCode): OmrBlankInstance {
            $lockedVersion = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail($version->id);
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
                'instance_code' => $instanceCode,
                'display_code' => $displayCode,
                'page_set_no' => $pageSetNo,
                'page_count' => $pageCount,
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

    public function registerCapture(OmrBlankInstance $instance, int $pageNo, OmrCaptureSheetPage $capturedPage): OmrBlankInstance
    {
        return DB::transaction(function () use ($instance, $pageNo, $capturedPage): OmrBlankInstance {
            $locked = OmrBlankInstance::query()->lockForUpdate()->findOrFail($instance->id);
            if (in_array($locked->status, [OmrBlankInstance::STATUS_SUPERSEDED, OmrBlankInstance::STATUS_VOID], true)) {
                throw new OmrBlankInstanceException('superseded_instance', 'This blank instance is no longer capturable.');
            }
            $page = $locked->pages()->lockForUpdate()->where('page_no', $pageNo)->first();
            if ($page === null) {
                throw new OmrBlankInstanceException('page_missing_from_set', 'The captured page is not part of the blank set.');
            }
            if ($page->status === OmrBlankInstancePage::STATUS_CAPTURED) {
                throw new OmrBlankInstanceException('page_already_captured', 'This page has already been captured.');
            }
            $page->forceFill([
                'status' => OmrBlankInstancePage::STATUS_CAPTURED,
                'captured_page_id' => $capturedPage->id,
            ])->save();
            $allCaptured = $locked->pages()->where('status', '!=', OmrBlankInstancePage::STATUS_CAPTURED)->doesntExist();
            if ($allCaptured) {
                $locked->forceFill(['status' => OmrBlankInstance::STATUS_CAPTURED])->save();
            }

            return $locked->fresh('pages');
        });
    }

    public function void(OmrBlankInstance $instance): OmrBlankInstance
    {
        return DB::transaction(function () use ($instance): OmrBlankInstance {
            $locked = OmrBlankInstance::query()->lockForUpdate()->findOrFail($instance->id);
            if ($locked->status !== OmrBlankInstance::STATUS_PRINTED) {
                throw new OmrBlankInstanceException('lifecycle_invalid', 'Only printed sets may be voided.');
            }
            $locked->forceFill(['status' => OmrBlankInstance::STATUS_VOID])->save();
            $locked->pages()->where('status', OmrBlankInstancePage::STATUS_PRINTED)->update(['status' => OmrBlankInstancePage::STATUS_VOID]);

            return $locked->fresh('pages');
        });
    }

    public function supersede(OmrBlankInstance $instance, OmrBlankInstance $replacement): OmrBlankInstance
    {
        return DB::transaction(function () use ($instance, $replacement): OmrBlankInstance {
            $locked = OmrBlankInstance::query()->lockForUpdate()->findOrFail($instance->id);
            if ($locked->status !== OmrBlankInstance::STATUS_PRINTED) {
                throw new OmrBlankInstanceException('lifecycle_invalid', 'Only printed sets may be superseded.');
            }
            $locked->forceFill([
                'status' => OmrBlankInstance::STATUS_SUPERSEDED,
                'superseded_by_instance_id' => $replacement->id,
            ])->save();

            return $locked->fresh('pages');
        });
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

<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrBlankInstanceException;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrQrPayload;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\User;
use RuntimeException;

final class RegisterOmrBlankInstanceCapture
{
    public function __construct(
        private readonly OmrQrPayload $qr,
        private readonly OmrBlankInstanceService $instances,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(User $actor, ?string $payload, OmrAnswerSheetTemplateVersion $templateVersion, int $pageNo, OmrCaptureSheetPage $capturePage): OmrBlankInstance
    {
        if ($payload === null || trim($payload) === '') {
            throw new OmrBlankInstanceException('qr_undecodable', 'QR payload is absent or unreadable; use audited manual recovery.');
        }
        $verified = $this->qr->verify($payload);
        $instance = OmrBlankInstance::query()->where('instance_code', $verified['instance_code'])->first();
        if ($instance === null) {
            throw new OmrBlankInstanceException('tamper_detected', 'Signed instance code is not registered.');
        }
        if ((int) $instance->template_version_id !== (int) $templateVersion->id) {
            throw new OmrBlankInstanceException('template_mismatch', 'Blank instance belongs to another template version.');
        }
        if ((int) $verified['page_no'] !== $pageNo || (int) $verified['page_count'] !== (int) $instance->page_count) {
            throw new OmrBlankInstanceException('page_missing_from_set', 'Signed page metadata does not match the instance set.');
        }
        $saved = $this->instances->registerCapture($instance, $pageNo, $capturePage);
        $this->recordAuditEvent->handle('omr.blank_instance_capture_registered', [
            'instance_id' => $saved->id,
            'page_no' => $pageNo,
            'template_version_id' => $templateVersion->id,
            'qr_payload_hash' => $verified['payload_hash'],
        ], (string) $actor->id);

        return $saved;
    }
}

<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateOmrAuthoringDraft
{
    public function __construct(
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @param array<string, mixed> $authoring */
    public function handle(
        User $actor,
        string $code,
        string $name,
        string $sourceKind,
        OmrRegistrationMode $registrationMode,
        OmrQrMode $qrMode,
        int $requestedPageCount,
        array $authoring = [],
    ): OmrTemplateAuthoringDraft {
        return DB::transaction(function () use (
            $actor,
            $code,
            $name,
            $sourceKind,
            $registrationMode,
            $qrMode,
            $requestedPageCount,
            $authoring,
        ): OmrTemplateAuthoringDraft {
            $draft = $this->drafts->create(
                $actor,
                $code,
                $name,
                $sourceKind,
                $authoring,
                $registrationMode,
                $qrMode,
                $requestedPageCount,
            );

            $this->recordAuditEvent->handle('omr.canonical_draft_created', [
                'institution_id' => $draft->institution_id,
                'draft_id' => $draft->id,
                'code' => $draft->code,
                'source_kind' => $draft->source_kind,
                'registration_mode' => $registrationMode->value,
                'qr_mode' => $qrMode->value,
                'requested_page_count' => $requestedPageCount,
            ], (string) $actor->id);

            return $draft;
        });
    }
}

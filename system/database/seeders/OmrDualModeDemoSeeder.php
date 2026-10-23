<?php

namespace Database\Seeders;

use App\Assessment\Actions\Omr\Canonical\CreateOmrAuthoringDraft;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Opt-in, reproducible draft gallery for the dual-mode OMR lifecycle.
 *
 * Run after foundation:reset and its normal OMR seeders:
 *   php artisan db:seed --class=Database\\Seeders\\OmrDualModeDemoSeeder
 *
 * This seed deliberately creates drafts only. It does not manufacture
 * verification, recognition, issuance, or physical-qualification evidence.
 */
final class OmrDualModeDemoSeeder extends Seeder
{
    private const DRAFTS = [
        [
            'code' => 'OMR-DUAL-DOC-1P',
            'name' => 'Dual-mode demo — uploaded supplier sheet, document-bound, one page',
            'source_kind' => OmrTemplateAuthoringDraft::SOURCE_PDF,
            'registration_mode' => OmrRegistrationMode::DocumentBound,
            'qr_mode' => OmrQrMode::Disabled,
            'page_count' => 1,
        ],
        [
            'code' => 'OMR-DUAL-DOC-2P',
            'name' => 'Dual-mode demo — uploaded supplier sheet, document-bound, two pages',
            'source_kind' => OmrTemplateAuthoringDraft::SOURCE_PDF,
            'registration_mode' => OmrRegistrationMode::DocumentBound,
            'qr_mode' => OmrQrMode::Disabled,
            'page_count' => 2,
        ],
        [
            'code' => 'OMR-DUAL-FID-1P',
            'name' => 'Dual-mode demo — fiducial-anchored, signed QR, one page',
            'source_kind' => OmrTemplateAuthoringDraft::SOURCE_HTML,
            'registration_mode' => OmrRegistrationMode::FiducialAnchored,
            'qr_mode' => OmrQrMode::Signed,
            'page_count' => 1,
        ],
        [
            'code' => 'OMR-DUAL-FID-2P',
            'name' => 'Dual-mode demo — fiducial-anchored, signed QR, two pages',
            'source_kind' => OmrTemplateAuthoringDraft::SOURCE_HTML,
            'registration_mode' => OmrRegistrationMode::FiducialAnchored,
            'qr_mode' => OmrQrMode::Signed,
            'page_count' => 2,
        ],
    ];

    public function run(): void
    {
        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $drafts = [];

        foreach (self::DRAFTS as $definition) {
            $draft = $this->ensureDraft($actor, $definition);
            $drafts[] = $draft;
            $this->command?->info(sprintf(
                'OMR draft #%d %s — %s / %s / %d page(s): /setup/exam-materials/answer-sheets/%d/edit',
                $draft->id,
                $draft->code,
                $draft->registration_mode->value,
                $draft->qr_mode->value,
                $draft->requested_page_count,
                $draft->id,
            ));
        }

        $this->reportExistingSignedQrVersions();
        $this->reportExistingReviewFixtures($actor);
        $this->reportUnavailableStates($drafts);
        $this->command?->info('OMR staff login: guidance.counselor@nexiam.test / password');
    }

    /**
     * @param  array{code: string, name: string, registration_mode: OmrRegistrationMode, qr_mode: OmrQrMode, page_count: int}  $definition
     */
    private function ensureDraft(User $actor, array $definition): OmrTemplateAuthoringDraft
    {
        $existing = OmrTemplateAuthoringDraft::query()
            ->where('code', $definition['code'])
            ->first();

        if ($existing !== null) {
            if ((int) $existing->owner_user_id !== (int) $actor->id
                || $existing->registration_mode !== $definition['registration_mode']
                || $existing->qr_mode !== $definition['qr_mode']
                || (int) $existing->requested_page_count !== $definition['page_count']) {
                throw new RuntimeException('omr_dual_mode_demo_draft_context_mismatch:'.$definition['code']);
            }

            return $existing;
        }

        $pages = [];
        for ($pageNo = 1; $pageNo <= $definition['page_count']; $pageNo++) {
            $pages[] = ['page_no' => $pageNo, 'confirmed' => false];
        }

        return app(CreateOmrAuthoringDraft::class)->handle(
            actor: $actor,
            code: $definition['code'],
            name: $definition['name'],
            sourceKind: $definition['source_kind'] ?? OmrTemplateAuthoringDraft::SOURCE_HTML,
            registrationMode: $definition['registration_mode'],
            qrMode: $definition['qr_mode'],
            requestedPageCount: $definition['page_count'],
            authoring: [
                'pages' => $pages,
                'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            ],
        );
    }

    private function reportExistingSignedQrVersions(): void
    {
        foreach ([1, 2] as $pageCount) {
            $version = OmrAnswerSheetTemplateVersion::query()
                ->with('template:id,code,name')
                ->where('registration_mode', OmrRegistrationMode::FiducialAnchored->value)
                ->where('qr_mode', OmrQrMode::Signed->value)
                ->where('page_count', $pageCount)
                ->orderBy('template_id')
                ->orderBy('version')
                ->first();

            if ($version === null) {
                $this->command?->warn(sprintf(
                    'No existing fiducial/signed-QR %d-page immutable version was found; run the normal OMR demo seed first.',
                    $pageCount,
                ));

                continue;
            }

            $this->command?->info(sprintf(
                'Existing fiducial/signed-QR version #%d %s v%d (%d page(s), status=%s); version list: /setup/exam-materials/answer-sheets',
                $version->id,
                (string) $version->template?->code,
                $version->version,
                $version->page_count,
                $version->status,
            ));
        }
    }

    private function reportExistingReviewFixtures(User $actor): void
    {
        $reviewRun = OmrDiagnosticRun::query()
            ->where('created_by_user_id', $actor->id)
            ->where('status', OmrDiagnosticRun::STATUS_NEEDS_REVIEW)
            ->where('phase', OmrDiagnosticRun::PHASE_REVIEW)
            ->orderBy('public_id')
            ->first();

        if ($reviewRun !== null) {
            $this->command?->info(sprintf(
                'Existing diagnostic review demo fixture %s (%s/%s; seeded evidence, not physical qualification): /setup/exam-materials/omr-test/runs/%s',
                $reviewRun->public_id,
                $reviewRun->status,
                $reviewRun->phase,
                $reviewRun->public_id,
            ));
        } else {
            $this->command?->warn('No existing diagnostic review run found; refresh OmrDiagnosticDemoSeeder for the seeded review surface.');
        }

        $reviewSheet = OmrCaptureSheet::query()
            ->where('status', OmrCaptureSheet::STATUS_NEEDS_REVIEW)
            ->whereHas('batch.gradingSession')
            ->with('batch.gradingSession')
            ->orderBy('id')
            ->first();

        if ($reviewSheet !== null) {
            $gradingSession = $reviewSheet->batch?->gradingSession;
            $this->command?->info(sprintf(
                'Existing production review demo fixture #%d in batch #%d (seeded synthetic evidence; no new claim or score); workbench: /scoring/sessions/%d/omr/batches/%d/workbench',
                $reviewSheet->id,
                $reviewSheet->batch_id,
                $gradingSession?->exam_session_id,
                $reviewSheet->batch_id,
            ));
        }
    }

    /** @param list<OmrTemplateAuthoringDraft> $drafts */
    private function reportUnavailableStates(array $drafts): void
    {
        $documentBoundDrafts = array_filter(
            $drafts,
            static fn (OmrTemplateAuthoringDraft $draft): bool => $draft->registration_mode === OmrRegistrationMode::DocumentBound,
        );
        if ($documentBoundDrafts !== []) {
            $this->command?->warn(
                'Not seeded: document-bound immutable versions, pending Page 2, pairing_missing, and pairing_conflict. '.
                'They require a compiled QR-disabled two-page version backed by current per-page digital-verification evidence. '.
                'pairing_missing/conflict additionally require page evidence analyzed against that version with, respectively, an unreadable identity or two different recognized Applicant Numbers; no such page-image/recognition fixture ships in the reset seed. '.
                'Production capture also requires an explicitly printed issuance, and physical activation remains blocked without genuine printer/device qualification.',
            );
        }
    }
}

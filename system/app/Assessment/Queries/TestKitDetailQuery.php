<?php

namespace App\Assessment\Queries;

use App\Assessment\Support\TestKitCurrentAcceptanceStatus;
use App\Assessment\Support\TestKitEligibility;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;

use function sprintf;

final class TestKitDetailQuery
{
    public function __construct(
        private readonly TestKitEligibility $eligibility,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitCompositionQuery $compositionQuery,
        private readonly TestKitCurrentAcceptanceStatus $acceptanceStatus,
    ) {}

    /** @return array<string, mixed> */
    public function query(User $actor, int $testKitId, ?int $versionId = null): array
    {
        $family = TestKit::query()
            ->where('institution_id', (int) Institution::current()->id)
            ->findOrFail($testKitId);
        $preparation = $family->openPreparation()->first();
        $hasOpenPreparation = $preparation !== null;
        if ($versionId === null && $preparation !== null) {
            return $this->preparation($actor, $family, $preparation);
        }

        $versionsQuery = TestKitVersion::query()
            ->where('test_kit_id', $family->id)
            ->when($versionId !== null, fn ($query) => $query->whereKey($versionId))
            ->with('acceptanceReceipts')
            ->orderByDesc('version_number');
        $selectedVersion = $versionsQuery->first();
        if ($selectedVersion === null) {
            abort_if($versionId !== null, 404);

            return $this->withoutPreparation($family);
        }
        $composition = $this->compositionQuery->forVersion($actor, $family, $selectedVersion);
        $latestFamilyVersionNumber = (int) (TestKitVersion::query()
            ->where('test_kit_id', $family->id)
            ->max('version_number') ?? 0);
        $instrumentVersion = TestInstrumentVersion::query()
            ->with(['subtests.items', 'metrics.inputs'])
            ->findOrFail($selectedVersion->test_instrument_version_id);
        $templateVersion = $selectedVersion->template_version_id === null
            ? null
            : OmrAnswerSheetTemplateVersion::query()
                ->with(['template', 'pages', 'areas.questions'])
                ->find((int) $selectedVersion->template_version_id);
        $bookletVersion = $selectedVersion->booklet_version_id === null
            ? null
            : OmrQuestionBookletVersion::query()
                ->with(['booklet', 'sets.items'])
                ->find((int) $selectedVersion->booklet_version_id);
        $resources = $composition['applicable_resources']
            ?? ($composition['state'] === 'ready' ? [
                'answer_sheet' => true,
                'booklet_form' => true,
                'key' => true,
                'physical_procedure' => true,
            ] : null);
        $omrApplicable = $composition['omr_applicable'] ?? ($composition['state'] === 'ready' ? true : null);
        $answerSheetRequired = is_array($resources) ? ($resources['answer_sheet'] ?? null) : null;
        $bookletRequired = is_array($resources)
            ? (($resources['booklet_form'] ?? false) || ($resources['key'] ?? false))
            : null;
        $receipts = $selectedVersion->acceptanceReceipts
            ->sortByDesc('created_at')
            ->take(5)
            ->map(fn (TestKitAcceptanceReceipt $receipt): array => [
                'id' => (int) $receipt->id,
                'result' => (string) $receipt->result,
                'created_at' => $receipt->created_at?->toISOString(),
            ])->values()->all();
        $selectedReceipt = $selectedVersion->acceptanceReceipts
            ->firstWhere('id', $selectedVersion->selected_acceptance_receipt_id);
        $historicalReceipt = $selectedReceipt
            ?? $selectedVersion->acceptanceReceipts->sortByDesc('id')->first();
        $acceptanceStatus = $this->acceptanceStatus->forReceipt($selectedVersion, $selectedReceipt);
        $hasExternalFinalOnlyProfile = $instrumentVersion->isFinalOnly();
        $hasPassingReceipt = $acceptanceStatus['current'];
        $profileReady = $instrumentVersion->status === 'approved';
        $answerSheetReady = $answerSheetRequired === false || ($templateVersion !== null
            && in_array($templateVersion->status, ['compiled', 'active'], true));
        $bookletSetAReady = $bookletVersion?->sets->contains(
            fn ($set): bool => $set->set_code === 'A' && $set->isFinalized(),
        ) ?? false;
        $bookletReady = $bookletRequired === false || ($bookletVersion !== null
            && $bookletVersion->status === OmrQuestionBookletVersion::STATUS_FINALIZED
            && (! ($resources['key'] ?? false) || $bookletSetAReady));
        $trialReady = $omrApplicable === false || ($omrApplicable === true && $hasPassingReceipt);
        $readinessChecks = ['test_profile' => $profileReady];
        if ($answerSheetRequired === true) {
            $readinessChecks['answer_sheet'] = $answerSheetReady;
        }
        if ($bookletRequired === true) {
            $readinessChecks['booklet'] = $bookletReady;
        }
        if ($omrApplicable === true) {
            $readinessChecks['trial'] = $trialReady;
        }
        $readyCount = count(array_filter($readinessChecks));
        $readinessTotal = count($readinessChecks);
        $trialStatus = match ($omrApplicable) {
            false => 'not_applicable',
            true => $hasPassingReceipt ? 'verified' : ($historicalReceipt?->result === 'passed'
                ? 'historical_pass'
                : ($historicalReceipt?->result === 'failed' ? 'failed' : 'incomplete')),
            default => 'unresolved',
        };
        $testProfileUrl = route('setup.test-instruments.show', ['instrument' => $instrumentVersion->test_instrument_id], absolute: false);
        $institutionId = (int) $family->institution_id;
        $canEditAnswerSheets = $this->authorizer->decideFresh(
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
            ['office' => 'guidance', 'institution_id' => $institutionId],
        )->allowed;
        $canManageBooklets = $this->authorizer->decideFresh(
            $actor,
            CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
            ['office' => 'guidance', 'institution_id' => $institutionId],
        )->allowed;
        $answerSheetPreviewUrl = $templateVersion !== null && $canEditAnswerSheets
            ? route('setup.omr.templates.preview', [
                'version' => $templateVersion->id,
                'test_kit_id' => $family->id,
                'test_kit_version_id' => $selectedVersion->id,
            ], absolute: false)
            : null;
        $bookletWorkspaceUrl = $bookletVersion !== null && $canManageBooklets
            ? route('setup.exam-materials.booklets.show', [
                'booklet' => $bookletVersion->booklet_id,
                'booklet_version_id' => $bookletVersion->id,
                'test_kit_id' => $family->id,
                'test_kit_version_id' => $selectedVersion->id,
            ], absolute: false)
            : null;
        $trialUrl = ($omrApplicable === true || ($composition['map_applicable'] ?? false) === true) && $canEditAnswerSheets
            ? route('setup.exam-materials.omr-test', ['test_kit_version_id' => $selectedVersion->id], absolute: false)
            : null;
        $eligibility = $this->eligibility->evaluate(
            $selectedVersion,
            null,
            TestKitEligibility::PURPOSE_ACTIVATION,
            $selectedReceipt,
            operation: $instrumentVersion->isFinalOnly() ? 'external_final' : 'manual',
        );
        $eligibilityBlockers = $eligibility['blockers'];
        if ($selectedVersion->status !== TestKitVersion::STATUS_CANDIDATE) {
            $eligibilityBlockers[] = [
                'code' => 'test_kit_status',
                'message' => 'Only a Candidate Test Kit version can be activated.',
            ];
        }
        $operationalHolds = array_values(array_filter(
            $eligibilityBlockers,
            static fn (array $blocker): bool => in_array($blocker['code'], [
                'source_evidence_unavailable',
                'physical_qualification_required',
                'institutional_provenance_required',
                'version_revoked',
            ], true),
        ));
        $preparation = TestKitPreparation::query()
            ->where('test_kit_id', $family->id)
            ->where('assembled_test_kit_version_id', $selectedVersion->id)
            ->first();
        $kitContext = [
            'test_kit_id' => (int) $family->id,
            'preparation_id' => $preparation === null ? null : (int) $preparation->id,
            'test_kit_version_id' => (int) $selectedVersion->id,
            'booklet_set_id' => $selectedVersion->booklet_set_id === null ? null : (int) $selectedVersion->booklet_set_id,
            'booklet_set_code' => $selectedVersion->booklet_set_code,
            'qr_mode' => $templateVersion?->qr_mode instanceof \BackedEnum
                ? $templateVersion->qr_mode->value
                : ($templateVersion?->qr_mode === null ? null : (string) $templateVersion->qr_mode),
            'registration_mode' => $templateVersion?->registration_mode instanceof \BackedEnum
                ? $templateVersion->registration_mode->value
                : ($templateVersion?->registration_mode === null ? null : (string) $templateVersion->registration_mode),
            'back_url' => route('setup.exam-materials.test-kits.show', [
                'testKit' => $family->id,
                'version' => $selectedVersion->id,
            ], absolute: false),
        ];
        $testSetupUrl = route('setup.exam-materials.test-kits.test-setup', [
            'testKit' => $family->id,
            'test_kit_id' => $family->id,
            'test_kit_version_id' => $selectedVersion->id,
        ], absolute: false);
        $versionHistory = $this->versionHistory($family);

        $subtestNames = $instrumentVersion->subtests
            ->pluck('name')
            ->filter(static fn (?string $name): bool => $name !== null && trim($name) !== '')
            ->values()
            ->all();

        $testProfile = [
            'id' => (int) $instrumentVersion->test_instrument_id,
            'version_id' => (int) $instrumentVersion->id,
            'version_number' => (int) $instrumentVersion->version_number,
            'display_name' => (string) $instrumentVersion->display_name,
            'form_code' => (string) $instrumentVersion->form_code,
            'subtests_count' => $instrumentVersion->subtests->count(),
            'subtests_summary' => implode(', ', $subtestNames),
            'status' => (string) $instrumentVersion->status,
            'is_final_only' => $hasExternalFinalOnlyProfile,
            'required_final_metric_count' => $hasExternalFinalOnlyProfile
                ? $instrumentVersion->metrics->where('is_required', true)->count()
                : null,
            'url' => $testProfileUrl,
        ];
        $normsStatus = $hasExternalFinalOnlyProfile
            ? 'not_applicable'
            : (string) ($preparation?->normPackageVersion?->status ?? 'unselected');
        $norms = [
            'id' => null,
            'version_id' => null,
            'title' => null,
            'tables_count' => 0,
            'status' => $normsStatus,
            'url' => null,
        ];
        $answerSheet = $templateVersion === null ? null : [
            'id' => (int) $templateVersion->template_id,
            'version_id' => (int) $templateVersion->id,
            'version_number' => (int) $templateVersion->version,
            'title' => (string) ($templateVersion->template->name ?? $templateVersion->template->title ?? 'Answer Sheet'),
            'total_pages' => (int) $templateVersion->page_count,
            'physical_item_count' => $templateVersion->areas->flatMap(fn ($area) => $area->questions)->count(),
            'status' => (string) $templateVersion->status,
            'preview_url' => $answerSheetPreviewUrl,
        ];
        $booklet = $bookletVersion === null ? null : [
            'id' => (int) $bookletVersion->booklet_id,
            'version_id' => (int) $bookletVersion->id,
            'version_number' => (int) $bookletVersion->version,
            'title' => (string) ($bookletVersion->booklet->name ?? $bookletVersion->booklet->title ?? 'Booklet'),
            'total_keys' => (int) ($bookletVersion->sets->firstWhere('set_code', 'A')?->items->count() ?? 0),
            'set_code' => $bookletVersion->sets->firstWhere('set_code', 'A')?->set_code,
            'status' => (string) $bookletVersion->status,
            'workspace_url' => $bookletWorkspaceUrl,
        ];
        $trial = [
            'historical_result' => $historicalReceipt?->result,
            'current_verification' => $acceptanceStatus['current'],
            'verification_blockers' => $acceptanceStatus['blockers'],
            'verification_status' => $trialStatus,
            'receipt_id' => $historicalReceipt === null ? null : (int) $historicalReceipt->id,
            'trial_url' => $trialUrl,

        ];

        return [
            'test_kit' => [
                'id' => (int) $family->id,
                'code' => (string) $family->code,
                'name' => (string) $family->name,
                'is_synthetic' => (bool) $family->is_synthetic,
                'latest_version_number' => $latestFamilyVersionNumber,
            ],
            'test_kit_version' => [
                'id' => (int) $selectedVersion->id,
                'version_number' => (int) $selectedVersion->version_number,
                'predecessor_version_id' => $selectedVersion->predecessor_version_id === null ? null : (int) $selectedVersion->predecessor_version_id,
                'status' => (string) $selectedVersion->status,
                'use_status' => $selectedVersion->revoked_at !== null
                    ? 'revoked'
                    : match ($selectedVersion->status) {
                        TestKitVersion::STATUS_CANDIDATE => 'draft',
                        TestKitVersion::STATUS_ACTIVE => 'active',
                        default => 'history',
                    },
                'scoring_method' => $hasExternalFinalOnlyProfile ? 'final_scores_only' : 'raw_scoring',
                'revision' => (int) $selectedVersion->revision,
                'materials' => [
                    'template_version_id' => $selectedVersion->template_version_id === null ? null : (int) $selectedVersion->template_version_id,
                    'booklet_version_id' => $selectedVersion->booklet_version_id === null ? null : (int) $selectedVersion->booklet_version_id,
                    'booklet_set_id' => $selectedVersion->booklet_set_id === null ? null : (int) $selectedVersion->booklet_set_id,
                    'test_instrument_version_id' => (int) $selectedVersion->test_instrument_version_id,
                ],
                'selected_acceptance_receipt_id' => $selectedVersion->selected_acceptance_receipt_id === null
                    ? null
                    : (int) $selectedVersion->selected_acceptance_receipt_id,
            ],
            'composition' => $composition,
            'test_profile' => $testProfile,
            'answer_sheet' => $answerSheet,
            'booklet' => $booklet,
            'norms' => $norms,
            'trial' => $trial,
            'recent_receipts' => $receipts,
            'readiness' => [
                'configuration_status' => $resources === null || $omrApplicable === null ? 'unresolved' : 'resolved',
                'configuration_ready' => $resources !== null && $omrApplicable !== null && $readyCount === $readinessTotal,
                'omr_readiness' => $hasExternalFinalOnlyProfile || $omrApplicable === false
                    ? 'not_applicable'
                    : ($eligibility['eligible'] ? 'ready' : 'held'),
                'operational_holds' => array_column($operationalHolds, 'code'),
                'is_ready_for_activation' => $selectedVersion->status === 'candidate' && $eligibility['eligible'],
                'blockers' => array_column($eligibilityBlockers, 'code'),
                'blocker_details' => $eligibilityBlockers,
                'score' => $readyCount,
                'total' => $readinessTotal,
            ],
            'actions' => [
                'index' => route('setup.exam-materials.test-kits.index'),
                'test_setup' => $testSetupUrl,
                'answer_sheet' => ! $hasExternalFinalOnlyProfile && $canEditAnswerSheets
                    ? route('setup.exam-materials.answer-sheets.index', [
                        'test_kit_id' => $family->id,
                        'test_kit_version_id' => $selectedVersion->id,
                    ], absolute: false)
                    : null,
                'booklet' => ! $hasExternalFinalOnlyProfile && $canManageBooklets
                    ? route('setup.exam-materials.booklets.index', [
                        'test_kit_id' => $family->id,
                        'test_kit_version_id' => $selectedVersion->id,
                    ], absolute: false)
                    : null,
                'history' => route('setup.exam-materials.test-kits.show', ['testKit' => $family->id], absolute: false),
                'start_preparation' => (int) $selectedVersion->version_number === $latestFamilyVersionNumber
                    ? route('setup.exam-materials.test-kits.preparation.start', ['testKit' => $family->id])
                    : null,
                'select_receipt' => route('setup.exam-materials.test-kits.select-receipt', ['testKit' => $family->id, 'version' => $selectedVersion->id]),
                'discard_preparation' => $hasOpenPreparation && $preparation?->state === TestKitPreparation::STATE_PREPARING
                    ? route('setup.exam-materials.test-kits.preparation.discard', ['testKit' => $family->id])
                    : null,
                'activate' => route('setup.exam-materials.test-kits.activate', ['testKit' => $family->id, 'version' => $selectedVersion->id]),
                'retire' => route('setup.exam-materials.test-kits.retire', ['testKit' => $family->id, 'version' => $selectedVersion->id]),
                'revoke' => route('setup.exam-materials.test-kits.revoke', ['testKit' => $family->id, 'version' => $selectedVersion->id]),
            ],
            'version_history' => $versionHistory,
            'kit_context' => $kitContext,
        ];
    }

    /** @return array<string, mixed> */
    private function preparation(User $actor, TestKit $family, TestKitPreparation $preparation): array
    {
        $institutionId = (int) $family->institution_id;
        $canEditAnswerSheets = $this->authorizer->decideFresh(
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
            ['office' => 'guidance', 'institution_id' => $institutionId],
        )->allowed;
        $canManageBooklets = $this->authorizer->decideFresh(
            $actor,
            CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
            ['office' => 'guidance', 'institution_id' => $institutionId],
        )->allowed;
        $profileVersions = TestInstrumentVersion::query()
            ->with('instrument')
            ->where('status', TestInstrumentVersion::STATUS_APPROVED)
            ->whereHas('instrument', fn ($query) => $query
                ->where('institution_id', $institutionId)
                ->where('is_synthetic', $family->is_synthetic))
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $normVersions = AssessmentNormPackageVersion::query()
            ->with('package')
            ->whereIn('status', [AssessmentNormPackageVersion::STATUS_DRAFT, AssessmentNormPackageVersion::STATUS_APPROVED])
            ->whereHas('package', fn ($query) => $query
                ->where('institution_id', $institutionId)
                ->where('is_synthetic', $family->is_synthetic))
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $drafts = $canEditAnswerSheets
            ? OmrTemplateAuthoringDraft::query()
                ->where('institution_id', $institutionId)
                ->where('is_synthetic', $family->is_synthetic)
                ->orderByDesc('updated_at')
                ->limit(100)
                ->get()
            : collect();
        $sheetVersions = $canEditAnswerSheets
            ? OmrAnswerSheetTemplateVersion::query()
                ->with('template')
                ->whereIn('status', [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE])
                ->whereHas('template', fn ($query) => $query
                    ->where('institution_id', $institutionId)
                    ->where('is_synthetic', $family->is_synthetic))
                ->orderByDesc('id')
                ->limit(100)
                ->get()
            : collect();
        $draftIdsByCompiledVersionId = $drafts
            ->filter(fn ($draft): bool => $draft->compiled_version_id !== null)
            ->mapWithKeys(fn ($draft): array => [(int) $draft->compiled_version_id => (int) $draft->id]);
        $bookletVersions = $canManageBooklets
            ? OmrQuestionBookletVersion::query()
                ->with(['booklet', 'sets'])
                ->whereNull('archived_at')
                ->where('status', OmrQuestionBookletVersion::STATUS_FINALIZED)
                ->whereHas('booklet', fn ($query) => $query
                    ->where('institution_id', $institutionId)
                    ->where('is_synthetic', $family->is_synthetic))
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->filter(fn ($version): bool => $version->sets->contains(
                    fn ($set): bool => $set->set_code === 'A' && $set->isFinalized(),
                ))
                ->values()
            : collect();

        $selectedInstrument = $preparation->testInstrumentVersion;
        $selectedNorm = $preparation->normPackageVersion;
        $selectedDraft = $preparation->answerSheetDraft;
        $selectedSheet = $preparation->templateVersion;
        $selectedBooklet = $preparation->bookletVersion;
        $selectedSet = $preparation->bookletSet;
        $composition = $this->compositionQuery->forPreparation($actor, $family, $preparation);
        $requirements = $composition['requirements'] ?? null;
        $resources = $composition['applicable_resources'] ?? null;
        $omrApplicable = $composition['omr_applicable'] ?? null;
        $mapApplicable = $composition['map_applicable'] ?? null;
        $blockers = [];
        if ($selectedInstrument === null || $selectedInstrument->status !== TestInstrumentVersion::STATUS_APPROVED) {
            $blockers[] = ['code' => 'test_profile', 'message' => 'Select an approved Test Profile version.'];
        }
        if (! is_array($requirements) || ! is_array($resources) || ! is_bool($omrApplicable)) {
            $blockers[] = [
                'code' => 'input_applicability_unresolved',
                'message' => 'Approved metric input and material requirements must be resolved before assembling this Kit.',
            ];
        } else {
            $hasAnswerSheet = $selectedSheet !== null
                && in_array($selectedSheet->status, [
                    OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
                    OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
                ], true);
            if (($resources['answer_sheet'] ?? false) && ! $hasAnswerSheet) {
                $blockers[] = ['code' => 'answer_sheet', 'message' => 'Select a ready Answer Sheet version.'];
            }
            if (($resources['booklet_form'] ?? false)
                && ($selectedBooklet === null || $selectedBooklet->status !== OmrQuestionBookletVersion::STATUS_FINALIZED)) {
                $blockers[] = ['code' => 'booklet_form', 'message' => 'Select a finalized Booklet form required by this Test Setup.'];
            }
            if (($resources['key'] ?? false)
                && ($selectedBooklet === null || $selectedSet === null
                    || $selectedSet->set_code !== 'A' || ! $selectedSet->isFinalized())) {
                $blockers[] = ['code' => 'booklet_key', 'message' => 'Select the finalized Set A key required by this Test Setup.'];
            }
        }
        if ($selectedDraft !== null && $selectedSheet !== null
            && (int) $selectedDraft->compiled_version_id !== (int) $selectedSheet->id) {
            $blockers[] = ['code' => 'incompatible_sheet_draft', 'message' => 'The Answer Sheet draft does not match the selected compiled version.'];
        }
        if ($selectedBooklet !== null && $selectedSet !== null
            && (int) $selectedSet->booklet_version_id !== (int) $selectedBooklet->id) {
            $blockers[] = ['code' => 'incompatible_booklet_set', 'message' => 'Set A does not belong to the selected Booklet version.'];
        }
        if ($mapApplicable === true && ($composition['map_complete'] ?? false) !== true) {
            $blockers[] = [
                'code' => 'composition_incomplete',
                'message' => 'Save a complete exact item map for the required OMR or selected optional attachment before assembling the Kit.',
            ];
        }
        foreach ($composition['issues'] ?? [] as $issue) {
            if (is_array($issue) && isset($issue['code'], $issue['message'])
                && ! in_array($issue['code'], array_column($blockers, 'code'), true)) {
                $blockers[] = $issue;
            }
        }

        $assemblyChecks = ['test_profile' => $selectedInstrument?->status === TestInstrumentVersion::STATUS_APPROVED];
        if (($resources['answer_sheet'] ?? false) === true) {
            $assemblyChecks['answer_sheet'] = $selectedSheet !== null && in_array(
                $selectedSheet->status,
                [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE],
                true,
            );
        }
        if (($resources['booklet_form'] ?? false) === true) {
            $assemblyChecks['booklet_form'] = $selectedBooklet?->status === OmrQuestionBookletVersion::STATUS_FINALIZED;
        }
        if (($resources['key'] ?? false) === true) {
            $assemblyChecks['booklet_key'] = $selectedSet !== null && $selectedSet->set_code === 'A' && $selectedSet->isFinalized();
        }
        if ($mapApplicable === true) {
            $assemblyChecks['item_map'] = ($composition['map_complete'] ?? false) === true;
        }
        $assemblyReadinessScore = count(array_filter($assemblyChecks));
        $assemblyReadinessTotal = count($assemblyChecks);

        $selection = [
            'test_instrument_version_id' => $preparation->test_instrument_version_id,
            'norm_package_version_id' => $preparation->norm_package_version_id,
            'answer_sheet_draft_id' => $preparation->answer_sheet_draft_id,
            'template_version_id' => $preparation->template_version_id,
            'booklet_version_id' => $preparation->booklet_version_id,
            'booklet_set_id' => $preparation->booklet_set_id,
        ];
        $context = [
            'test_kit_id' => (int) $family->id,
            'preparation_id' => (int) $preparation->id,
            'test_kit_version_id' => null,
            'booklet_set_id' => $selectedSet === null ? null : (int) $selectedSet->id,
            'booklet_set_code' => $selectedSet === null ? null : (string) $selectedSet->set_code,
            'qr_mode' => $selectedSheet === null ? null : ($selectedSheet->qr_mode instanceof \BackedEnum ? $selectedSheet->qr_mode->value : (string) $selectedSheet->qr_mode),
            'registration_mode' => $selectedSheet === null ? null : ($selectedSheet->registration_mode instanceof \BackedEnum ? $selectedSheet->registration_mode->value : (string) $selectedSheet->registration_mode),
            'back_url' => route('setup.exam-materials.test-kits.show', ['testKit' => $family->id], absolute: false),
        ];
        $testSetupUrl = route('setup.exam-materials.test-kits.test-setup', [
            'testKit' => $family->id,
            'test_kit_id' => $family->id,
            'preparation_id' => $preparation->id,
        ], absolute: false);
        $answerSheetUrl = route('setup.exam-materials.answer-sheets.index', [
            'test_kit_id' => $family->id,
            'preparation_id' => $preparation->id,
            'answer_sheet_draft_id' => $preparation->answer_sheet_draft_id,
            'template_version_id' => $preparation->template_version_id,
        ], absolute: false);
        $bookletUrl = $selectedBooklet === null
            ? route('setup.exam-materials.booklets.index', [
                'test_kit_id' => $family->id,
                'preparation_id' => $preparation->id,
            ], absolute: false)
            : route('setup.exam-materials.booklets.show', [
                'booklet' => $selectedBooklet->booklet_id,
                'test_kit_id' => $family->id,
                'preparation_id' => $preparation->id,
                'booklet_version_id' => $selectedBooklet->id,
                'booklet_set_id' => $preparation->booklet_set_id,
            ], absolute: false);
        $actions = [
            'index' => route('setup.exam-materials.test-kits.index'),
            'test_setup' => $testSetupUrl,
            'save_preparation' => route('setup.exam-materials.test-kits.preparation.update', ['testKit' => $family->id]),
            'save_composition' => route('setup.exam-materials.test-kits.preparation.composition.update', ['testKit' => $family->id]),
            'assemble' => route('setup.exam-materials.test-kits.preparation.assemble', ['testKit' => $family->id]),
            'discard_preparation' => route('setup.exam-materials.test-kits.preparation.discard', ['testKit' => $family->id]),
            'answer_sheet' => ! ($selectedInstrument?->isFinalOnly() ?? false) && $canEditAnswerSheets ? $answerSheetUrl : null,
            'booklet' => ! ($selectedInstrument?->isFinalOnly() ?? false) && $canManageBooklets ? $bookletUrl : null,
        ];

        return [
            'test_kit' => [
                'id' => (int) $family->id,
                'code' => (string) $family->code,
                'name' => (string) $family->name,
                'is_synthetic' => (bool) $family->is_synthetic,
                'latest_version_number' => (int) (TestKitVersion::query()
                    ->where('test_kit_id', $family->id)
                    ->max('version_number') ?? 0),
            ],
            'preparation' => [
                'id' => (int) $preparation->id,
                'revision' => (int) $preparation->revision,
                'state' => (string) $preparation->state,
                'selections' => $selection,
                'selected' => [
                    'test_profile' => $selectedInstrument === null ? null : [
                        'id' => (int) $selectedInstrument->id,
                        'name' => (string) $selectedInstrument->display_name,
                        'version_number' => (int) $selectedInstrument->version_number,
                        'is_final_only' => $selectedInstrument->isFinalOnly(),
                        'status' => (string) $selectedInstrument->status,
                    ],
                    'norm' => $selectedNorm === null ? null : [
                        'id' => (int) $selectedNorm->id,
                        'name' => (string) $selectedNorm->display_name,
                        'version_number' => (int) $selectedNorm->version_number,
                        'status' => (string) $selectedNorm->status,
                    ],
                    'answer_sheet_draft' => $selectedDraft === null ? null : [
                        'id' => (int) $selectedDraft->id,
                        'name' => (string) $selectedDraft->name,
                        'stage' => (string) $selectedDraft->stage,
                    ],
                    'answer_sheet' => $selectedSheet === null ? null : [
                        'id' => (int) $selectedSheet->id,
                        'name' => (string) ($selectedSheet->template?->name ?? 'Answer Sheet'),
                        'version_number' => (int) $selectedSheet->version,
                        'status' => (string) $selectedSheet->status,
                    ],
                    'booklet' => $selectedBooklet === null ? null : [
                        'id' => (int) $selectedBooklet->id,
                        'name' => (string) $selectedBooklet->booklet->name,
                        'version_number' => (int) $selectedBooklet->version,
                        'status' => (string) $selectedBooklet->status,
                        'set_code' => $selectedSet?->set_code,
                    ],
                ],
                'assembly_blockers' => $blockers,
            ],
            'composition' => $composition,
            'test_kit_version' => null,
            'test_profile' => null,
            'answer_sheet' => null,
            'booklet' => null,
            'norms' => null,
            'trial' => [
                'historical_result' => null,
                'current_verification' => false,
                'verification_blockers' => [],
                'verification_status' => $omrApplicable === false ? 'not_applicable' : ($omrApplicable === true ? 'not_verified' : 'unresolved'),
                'receipt_id' => null,
                'trial_url' => null,
            ],
            'recent_receipts' => [],
            'readiness' => [
                'configuration_status' => is_array($requirements) ? 'resolved' : 'unresolved',
                'configuration_ready' => is_array($requirements) && $blockers === [],
                'operational_holds' => [],
                'is_ready_for_activation' => false,
                'blockers' => array_column($blockers, 'code'),
                'blocker_details' => $blockers,
                'score' => $assemblyReadinessScore,
                'total' => $assemblyReadinessTotal,
            ],

            'component_choices' => [
                'test_instruments' => $profileVersions->map(fn ($version): array => [
                    'id' => (int) $version->id,
                    'label' => sprintf('%s · v%d', $version->display_name, $version->version_number),
                    'status' => (string) $version->status,
                ])->all(),
                'norm_versions' => ($selectedInstrument?->isFinalOnly() ?? false) ? [] : $normVersions->map(fn ($version): array => [
                    'id' => (int) $version->id,
                    'label' => sprintf('%s · v%d', $version->display_name, $version->version_number),
                    'status' => (string) $version->status,
                ])->all(),
                'answer_sheet_drafts' => $drafts->map(fn ($draft): array => [
                    'id' => (int) $draft->id,
                    'label' => (string) $draft->name,
                    'stage' => (string) $draft->stage,
                ])->all(),
                'answer_sheet_versions' => $sheetVersions->map(fn ($version): array => [
                    'id' => (int) $version->id,
                    'draft_id' => $draftIdsByCompiledVersionId->get((int) $version->id),
                    'label' => sprintf('%s · v%d', $version->template?->name ?? 'Answer Sheet', $version->version),
                    'status' => (string) $version->status,
                ])->all(),
                'booklet_versions' => $bookletVersions->map(fn ($version): array => [
                    'id' => (int) $version->id,
                    'set_id' => (int) $version->sets->firstWhere('set_code', 'A')->id,
                    'label' => sprintf('%s · v%d · Set A', $version->booklet->name, $version->version),
                    'status' => (string) $version->status,
                ])->all(),
            ],
            'version_history' => $this->versionHistory($family),
            'kit_context' => $context,
            'actions' => $actions,
        ];
    }

    /** @return array<string, mixed> */
    private function withoutPreparation(TestKit $family): array
    {
        return [
            'test_kit' => [
                'id' => (int) $family->id,
                'code' => (string) $family->code,
                'name' => (string) $family->name,
                'is_synthetic' => (bool) $family->is_synthetic,
                'latest_version_number' => 0,
            ],
            'test_kit_version' => null,
            'test_profile' => null,
            'answer_sheet' => null,
            'booklet' => null,
            'preparation' => null,
            'composition' => null,
            'component_choices' => [],
            'trial' => ['historical_result' => null, 'current_verification' => false, 'verification_blockers' => [], 'receipt_id' => null, 'trial_url' => null],
            'recent_receipts' => [],
            'readiness' => ['is_ready_for_activation' => false, 'blockers' => [], 'blocker_details' => [], 'score' => 0],
            'version_history' => [],
            'kit_context' => [
                'test_kit_id' => (int) $family->id,
                'preparation_id' => null,
                'test_kit_version_id' => null,
                'back_url' => route('setup.exam-materials.test-kits.show', ['testKit' => $family->id], absolute: false),
            ],
            'actions' => [
                'index' => route('setup.exam-materials.test-kits.index'),
                'history' => route('setup.exam-materials.test-kits.show', ['testKit' => $family->id], absolute: false),
                'start_preparation' => route('setup.exam-materials.test-kits.preparation.start', ['testKit' => $family->id]),
            ],
        ];
    }

    /** @return list<array{id: int, version_number: int, status: string, url: string}> */
    private function versionHistory(TestKit $family): array
    {
        return TestKitVersion::query()
            ->where('test_kit_id', $family->id)
            ->orderByDesc('version_number')
            ->get(['id', 'version_number', 'status'])
            ->map(fn (TestKitVersion $version): array => [
                'id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
                'status' => (string) $version->status,
                'url' => route('setup.exam-materials.test-kits.show', [
                    'testKit' => $family->id,
                    'version' => $version->id,
                ], absolute: false),
            ])->all();
    }
}

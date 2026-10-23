<?php

namespace App\Assessment\Omr\Queries;

use App\Assessment\Omr\Canonical\CanonicalOmrFixture;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceStatus;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\TestKit;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function sprintf;

final class ExamMaterialsQuery
{
    public function __construct(
        private readonly BookletWorkspaceQuery $workspace,
        private readonly OmrPhysicalAssuranceStatus $physicalAssurance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(
        ?User $actor = null,
        bool $canViewQuestionContent = false,
        ?int $selectedBookletVersionId = null,
        bool $readOnly = false,
        bool $kitContextPresent = false,
        ?bool $kitIsSynthetic = null,
        ?int $selectedInstrumentVersionId = null,
    ): array {
        $institutionId = (int) Institution::current()->id;
        $selectedBookletVersion = $selectedBookletVersionId === null
            ? null
            : OmrQuestionBookletVersion::query()
                ->whereHas('booklet', fn ($query) => $query->where('institution_id', $institutionId))
                ->findOrFail($selectedBookletVersionId);
        $draft = OmrTemplateAuthoringDraft::query()
            ->where('code', CanonicalOmrFixture::STABLE_CODE)
            ->with(['template.versions' => fn ($query) => $query->orderByDesc('version')])
            ->first();

        $answerSheet = null;
        if ($draft !== null) {
            $versions = $draft->template?->versions ?? collect();
            $qualificationByVersionId = $versions->mapWithKeys(function (OmrAnswerSheetTemplateVersion $version): array {
                $qualification = $this->physicalAssurance->get(null, $version);

                return [(int) $version->id => (bool) ($qualification['qualified'] ?? false)];
            });
            $physicallyQualified = $qualificationByVersionId->contains(
                static fn (bool $qualified): bool => $qualified,
            );
            $answerSheet = [
                'active_count' => OmrAnswerSheetTemplateVersion::query()
                    ->whereHas('template', fn ($query) => $query->where('institution_id', $institutionId))
                    ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
                    ->count(),
                'compiled_count' => OmrAnswerSheetTemplateVersion::query()
                    ->whereHas('template', fn ($query) => $query->where('institution_id', $institutionId))
                    ->where('status', OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
                    ->count(),
                'id' => (int) $draft->id,
                'code' => (string) $draft->code,
                'name' => (string) $draft->name,
                'display_name' => 'NEXIAM answer sheet',
                'stage' => (string) $draft->stage,
                'status_label' => $this->draftStatusLabel((string) $draft->stage),
                'physical_status' => $physicallyQualified ? 'Physically qualified' : 'Physical qualification pending',
                'open_url' => route('setup.exam-materials.answer-sheet'),
                'updated_at' => $draft->updated_at?->toIso8601String(),
                'compiled_versions' => $versions->map(fn ($version): array => [
                    'id' => (int) $version->id,
                    'version' => (int) $version->version,
                    'status' => (string) $version->status,
                    'physical_status' => $qualificationByVersionId->get((int) $version->id) ? 'physical_qualified' : 'not_qualified',
                    'native_bundle_hash' => $version->native_bundle_hash,
                ])->values()->all(),
            ];
        }

        $booklets = OmrQuestionBooklet::query()
            ->where('institution_id', $institutionId)
            ->when($selectedBookletVersion !== null, fn ($query) => $query->whereKey($selectedBookletVersion->booklet_id))
            ->with([
                'versions' => function ($query) use ($selectedBookletVersion): void {
                    if ($selectedBookletVersion === null) {
                        $query->whereNull('archived_at');
                    }

                    $query->orderByDesc('version');
                },
                'versions.templateVersion.template',
                'versions.sets' => fn ($query) => $query->orderBy('set_code'),
            ])
            ->orderBy('code')
            ->get();

        $bookletProjection = $booklets
            ->map(function (OmrQuestionBooklet $booklet) use (
                $actor,
                $canViewQuestionContent,
                $selectedBookletVersionId,
                $readOnly,
                $kitIsSynthetic,
                $kitContextPresent,
                $selectedInstrumentVersionId,
            ): array {
                $workspace = $actor === null
                    ? null
                    : $this->workspace->forBooklet(
                        $booklet,
                        $actor,
                        $canViewQuestionContent,
                        $selectedBookletVersionId,
                        $readOnly,
                        $kitContextPresent,
                        $kitIsSynthetic,
                        $selectedInstrumentVersionId,
                    );

                return [
                    'id' => (int) $booklet->id,
                    'code' => (string) $booklet->code,
                    'name' => (string) $booklet->name,
                    'url' => route('setup.exam-materials.booklets.show', $booklet),
                    'workspace' => $workspace,
                    'versions' => $booklet->versions
                        ->when(
                            $selectedBookletVersionId !== null,
                            fn ($versions) => $versions->where('id', $selectedBookletVersionId),
                        )
                        ->map(fn ($version): array => [
                            'id' => (int) $version->id,
                            'version' => (int) $version->version,
                            'status' => $version->isFinalized() ? 'finalized' : (string) $version->status,
                            'authoring_mode' => $version->authoring_mode,
                            'template_version_id' => $version->template_version_id === null ? null : (int) $version->template_version_id,
                            'items_count' => (int) $version->items()->count(),
                            'answer_key_hash' => $version->answer_key_hash,
                            'content_hash' => $version->content_hash,
                            'sets' => $version->sets
                                ->filter(fn ($set): bool => $set->isFinalized())
                                ->map(fn ($set): array => [
                                    'id' => (int) $set->id,
                                    'code' => (string) $set->set_code,
                                ])
                                ->values()
                                ->all(),
                        ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        $answerSheets = OmrAnswerSheetTemplateVersion::query()
            ->with('template')
            ->whereHas('template', function ($query) use ($institutionId, $kitContextPresent, $kitIsSynthetic): void {
                $query->where('institution_id', $institutionId);
                if ($kitContextPresent && $kitIsSynthetic !== null) {
                    $query->where('is_synthetic', $kitIsSynthetic);
                }
            })
            ->when(
                $kitContextPresent && $selectedInstrumentVersionId !== null,
                fn ($query) => $query->where('test_instrument_version_id', $selectedInstrumentVersionId),
            )
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
            ->orderByDesc('id')
            ->get()
            ->map(static fn (OmrAnswerSheetTemplateVersion $version): array => [
                'id' => (int) $version->id,
                'name' => (string) ($version->template?->name ?? 'Answer Sheet'),
                'version' => (int) $version->version,
                'label' => sprintf('%s v%d', $version->template?->name ?? 'Answer Sheet', (int) $version->version),
                'status' => 'compiled',
                'qr_mode' => $version->qr_mode?->value,
                'registration_mode' => $version->registration_mode?->value,
                'page_count' => (int) $version->page_count,
            ])
            ->values()
            ->all();

        $compiledAnswerSheetIds = collect($answerSheets)->pluck('id')->map(static fn ($id): int => (int) $id);
        $finalizedCompatibleBooklets = collect($bookletProjection)
            ->flatMap(static fn (array $booklet) => $booklet['versions'])
            ->filter(
                static fn (array $version): bool => $version['status'] === 'finalized'
                    && in_array('A', array_column($version['sets'], 'code'), true)
                    && $compiledAnswerSheetIds->contains((int) $version['template_version_id']),
            )
            ->count();
        $omrTestStatus = match (true) {
            $compiledAnswerSheetIds->isEmpty() => 'needs_answer_sheet',
            $finalizedCompatibleBooklets === 0 => 'needs_booklet',
            default => 'ready',
        };
        $testKits = TestKit::query()
            ->where('institution_id', $institutionId)
            ->with(['versions' => fn ($query) => $query->orderByDesc('version_number')->limit(1)])
            ->orderBy('code')
            ->limit(100)
            ->get()
            ->map(static fn (TestKit $kit): array => [
                'id' => (int) $kit->id,
                'code' => (string) $kit->code,
                'name' => (string) $kit->name,
                'is_synthetic' => (bool) $kit->is_synthetic,
                'latest_version' => $kit->versions->first() === null ? null : [
                    'id' => (int) $kit->versions->first()->id,
                    'version_number' => (int) $kit->versions->first()->version_number,
                    'status' => (string) $kit->versions->first()->status,
                    'revision' => (int) $kit->versions->first()->revision,
                ],
            ])
            ->values()
            ->all();
        $candidateMaterials = DB::table('omr_answer_sheet_template_versions as sheet')
            ->join('omr_answer_sheet_templates as sheet_family', 'sheet_family.id', '=', 'sheet.template_id')
            ->join('test_instrument_versions as profile', 'profile.id', '=', 'sheet.test_instrument_version_id')
            ->join('test_instruments as profile_family', 'profile_family.id', '=', 'profile.test_instrument_id')
            ->join('omr_question_booklet_versions as booklet', function ($join): void {
                $join->on('booklet.template_version_id', '=', 'sheet.id')
                    ->on('booklet.test_instrument_version_id', '=', 'profile.id');
            })
            ->join('omr_question_booklets as booklet_family', 'booklet_family.id', '=', 'booklet.booklet_id')
            ->join('omr_booklet_sets as set_a', 'set_a.booklet_version_id', '=', 'booklet.id')
            ->where('sheet_family.institution_id', $institutionId)
            ->where('profile_family.institution_id', $institutionId)
            ->where('booklet_family.institution_id', $institutionId)
            ->whereColumn('sheet_family.is_synthetic', 'profile_family.is_synthetic')
            ->whereColumn('profile_family.is_synthetic', 'booklet_family.is_synthetic')
            ->where('sheet.status', OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
            ->where('profile.status', 'approved')
            ->where('booklet.status', 'finalized')
            ->whereNotNull('booklet.finalized_at')
            ->where('set_a.set_code', 'A')
            ->where('set_a.status', 'finalized')
            ->whereNotNull('set_a.finalized_at')
            ->orderBy('sheet_family.code')
            ->orderByDesc('sheet.version')
            ->orderBy('booklet_family.code')
            ->orderByDesc('booklet.version')
            ->get([
                'sheet.id as template_version_id',
                'sheet.version as template_version_number',
                'sheet_family.code as template_code',
                'sheet_family.name as template_name',
                'booklet.id as booklet_version_id',
                'booklet.version as booklet_version_number',
                'booklet_family.code as booklet_code',
                'booklet_family.name as booklet_name',
                'set_a.id as booklet_set_id',
                'profile.id as test_instrument_version_id',
                'profile_family.code as profile_code',
                'profile.display_name as profile_name',
                'sheet_family.is_synthetic',
            ])
            ->map(static fn (object $row): array => [
                'template_version_id' => (int) $row->template_version_id,
                'template_label' => sprintf('%s · %s v%d', $row->template_code, $row->template_name, $row->template_version_number),
                'booklet_version_id' => (int) $row->booklet_version_id,
                'booklet_label' => sprintf('%s · %s v%d', $row->booklet_code, $row->booklet_name, $row->booklet_version_number),
                'booklet_set_id' => (int) $row->booklet_set_id,
                'test_instrument_version_id' => (int) $row->test_instrument_version_id,
                'profile_label' => sprintf('%s · %s', $row->profile_code, $row->profile_name),
                'is_synthetic' => (bool) $row->is_synthetic,
            ])
            ->values()
            ->all();
        $profileReadinessRows = DB::table('test_instrument_versions as profile')
            ->join('test_instruments as profile_family', 'profile_family.id', '=', 'profile.test_instrument_id')
            ->where('profile_family.institution_id', $institutionId)
            ->orderBy('profile_family.code')
            ->orderByDesc('profile.version_number')
            ->get([
                'profile.version_number',
                'profile.status',
                'profile_family.id as instrument_id',
                'profile_family.code',
                'profile.display_name',
            ]);
        $bookletReadinessRows = DB::table('omr_question_booklet_versions as booklet')
            ->join('omr_question_booklets as booklet_family', 'booklet_family.id', '=', 'booklet.booklet_id')
            ->leftJoin('omr_booklet_sets as set_a', function ($join): void {
                $join->on('set_a.booklet_version_id', '=', 'booklet.id')
                    ->where('set_a.set_code', '=', 'A');
            })
            ->where('booklet_family.institution_id', $institutionId)
            ->whereNull('booklet.archived_at')
            ->orderBy('booklet_family.code')
            ->orderByDesc('booklet.version')
            ->get([
                'booklet.version',
                'booklet.status',
                'booklet.finalized_at',
                'booklet_family.id as booklet_id',
                'booklet_family.code',
                'booklet_family.name',
                'set_a.id as set_id',
                'set_a.status as set_status',
                'set_a.finalized_at as set_finalized_at',
            ]);
        $approvedProfileRows = $profileReadinessRows->where('status', 'approved');
        $readyBookletRows = $bookletReadinessRows->filter(
            static fn (object $row): bool => $row->status === 'finalized'
                && $row->finalized_at !== null
                && $row->set_id !== null
                && $row->set_status === 'finalized'
                && $row->set_finalized_at !== null,
        );
        $readinessChecklist = [
            [
                'key' => 'answer_sheet',
                'label' => 'Compiled Answer Sheet',
                'status' => $answerSheets !== [] ? 'ready' : ($draft !== null ? 'incomplete' : 'missing'),
                'detail' => $answerSheets !== []
                    ? count($answerSheets).' compiled answer sheet version(s) in this institution.'
                    : ($draft !== null
                        ? 'The answer-sheet draft exists but has no compiled version yet ('.$this->draftStatusLabel((string) $draft->stage).').'
                        : 'No compiled answer-sheet version exists for this institution.'),
                'url' => route('setup.exam-materials.answer-sheets.index'),
                'materials' => $answerSheets !== []
                    ? array_slice(array_map(static fn (array $sheet): array => [
                        'label' => $sheet['label'],
                        'url' => route('setup.exam-materials.answer-sheets.index'),
                    ], $answerSheets), 0, 10)
                    : ($draft !== null ? [[
                        'label' => (string) $draft->name.' · '.$this->draftStatusLabel((string) $draft->stage),
                        'url' => route('setup.exam-materials.answer-sheets.edit', $draft),
                    ]] : []),
            ],
            [
                'key' => 'profile',
                'label' => 'Approved Test Instrument Profile',
                'status' => $approvedProfileRows->isNotEmpty() ? 'ready' : ($profileReadinessRows->isNotEmpty() ? 'incomplete' : 'missing'),
                'detail' => $approvedProfileRows->isNotEmpty()
                    ? $approvedProfileRows->count().' approved profile version(s) in this institution.'
                    : ($profileReadinessRows->isNotEmpty()
                        ? 'Profiles exist, but none has approved status.'
                        : 'No Test Instrument Profile exists for this institution.'),
                'url' => route('setup.test-instruments.index'),
                'materials' => $profileReadinessRows->take(10)->map(static fn (object $profile): array => [
                    'label' => sprintf('%s · %s v%d · %s', $profile->code, $profile->display_name, $profile->version_number, ucfirst((string) $profile->status)),
                    'url' => route('setup.test-instruments.show', (int) $profile->instrument_id),
                ])->values()->all(),
            ],
            [
                'key' => 'booklet',
                'label' => 'Finalized Question Booklet with Set A',
                'status' => $readyBookletRows->isNotEmpty() ? 'ready' : ($bookletReadinessRows->isNotEmpty() ? 'incomplete' : 'missing'),
                'detail' => $readyBookletRows->isNotEmpty()
                    ? $readyBookletRows->count().' finalized booklet version(s) with finalized Set A in this institution.'
                    : ($bookletReadinessRows->isNotEmpty()
                        ? 'Booklet versions exist, but none has both a finalized version and a finalized Set A.'
                        : 'No Question Booklet version exists for this institution.'),
                'url' => route('setup.exam-materials.booklets.index'),
                'materials' => $bookletReadinessRows->take(10)->map(static fn (object $booklet): array => [
                    'label' => sprintf('%s · %s v%d · booklet %s · Set A %s', $booklet->code, $booklet->name, $booklet->version, ucfirst((string) $booklet->status), $booklet->set_status === null ? 'missing' : ucfirst((string) $booklet->set_status)),
                    'url' => route('setup.exam-materials.booklets.show', (int) $booklet->booklet_id),
                ])->values()->all(),
            ],
            [
                'key' => 'compatibility',
                'label' => 'Matching institution & provenance compatibility',
                'status' => $candidateMaterials !== [] ? 'ready' : 'incompatible',
                'detail' => $candidateMaterials !== []
                    ? 'At least one exact candidate tuple shares the institution, synthetic/production provenance, sheet version, approved profile, finalized booklet version, and finalized Set A.'
                    : 'No exact tuple matches the current institution and synthetic/production provenance across the compiled sheet, approved profile, finalized booklet version, and finalized Set A.',
                'url' => null,
                'materials' => [],
            ],
        ];

        return [
            'page_state' => $omrTestStatus === 'ready' ? 'ready' : 'needs_materials',
            'test_kits' => $testKits,
            'candidate_materials' => $candidateMaterials,
            'candidate_readiness' => $readinessChecklist,
            'answer_sheet' => $answerSheet,
            'answer_sheets' => $answerSheets,
            'booklets' => $bookletProjection,
            'omr_test' => [
                'status' => $omrTestStatus,
                'is_ready' => $omrTestStatus === 'ready',
                'compiled_answer_sheet_count' => $compiledAnswerSheetIds->count(),
                'finalized_compatible_booklet_count' => $finalizedCompatibleBooklets,
                'open_url' => route('setup.exam-materials.omr-test'),
            ],
            'exam_kits' => $testKits,
        ];
    }

    private function draftStatusLabel(string $stage): string
    {
        return match ($stage) {
            OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED => 'Digitally verified',
            OmrTemplateAuthoringDraft::STAGE_COMPILED => 'Compiled',
            OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE => 'Mapping complete',
            OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED => 'Page prepared',
            OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED => 'Source confirmed',
            default => 'Editable draft',
        };
    }
}

<?php

namespace App\Assessment\Support;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use Illuminate\Http\Request;
use RuntimeException;

final class TestKitNavigationContext
{
    public function __construct(
        private readonly TestInstrumentAccess $access,
        private readonly KitCompositionContext $compositionContext,
    ) {}

    /**
     * @return array{test_kit_id:int,preparation_id:?int,test_kit_version_id:?int,test_instrument_version_id:?int,booklet_version_id:?int,authoring_booklet_version_id:?int,answer_sheet_draft_id:?int,template_version_id:?int,back_url:string,code:string,is_synthetic:bool,read_only:bool}|null
     */
    public function resolve(User $actor, Request $request): ?array
    {
        $rawVersionId = $request->query('test_kit_version_id');
        $rawKitId = $request->query('test_kit_id');
        $rawPreparationId = $request->query('preparation_id');
        $rawAuthoringBookletVersionId = $request->query('authoring_booklet_version_id');
        if ($rawVersionId === null && $rawKitId === null && $rawPreparationId === null) {
            return null;
        }

        $versionId = $this->positiveInteger($rawVersionId);
        $kitId = $this->positiveInteger($rawKitId);
        $preparationId = $this->positiveInteger($rawPreparationId);
        $authoringBookletVersionId = $this->positiveInteger($rawAuthoringBookletVersionId);
        $routeBookletId = $this->positiveInteger($request->route('booklet'));
        if (($rawVersionId !== null && $versionId === null)
            || ($rawKitId !== null && $kitId === null)
            || ($rawPreparationId !== null && $preparationId === null)
            || ($rawAuthoringBookletVersionId !== null && $authoringBookletVersionId === null)
            || ($request->route('booklet') !== null && $routeBookletId === null)) {
            return null;
        }

        $institutionId = $this->institutionId($actor);
        if ($institutionId === null) {
            return null;
        }

        $instrumentId = $this->positiveInteger($request->route('instrument'));

        if ($versionId !== null) {
            $version = TestKitVersion::query()
                ->with('testKit')
                ->where('institution_id', $institutionId)
                ->find($versionId);

            if ($version === null
                || $preparationId !== null
                || $authoringBookletVersionId !== null
                || $version->testKit === null
                || (int) $version->testKit->institution_id !== $institutionId
                || ($kitId !== null && $kitId !== (int) $version->test_kit_id)
                || ($instrumentId !== null && $instrumentId !== (int) $version->test_instrument_id)
                || ! $this->instrumentVersionMatchesKit(
                    (int) $version->test_instrument_version_id,
                    $version->testKit,
                )
                || ($version->booklet_version_id === null
                    ? ($routeBookletId !== null || ! $this->versionDeclaresNoPaper($version))
                    : ! $this->bookletVersionMatchesKit(
                        (int) $version->booklet_version_id,
                        $version->testKit,
                        $institutionId,
                        $routeBookletId,
                    ))) {
                return null;
            }

            return $this->context(
                $version->testKit,
                null,
                $version,
                true,
                null,
                $version->template_version_id === null ? null : (int) $version->template_version_id,
            );
        }

        if ($preparationId === null) {
            return null;
        }

        $preparation = TestKitPreparation::query()->find($preparationId);
        if ($preparation === null || ! in_array($preparation->state, ['preparing', 'assembled'], true)) {
            return null;
        }

        $kitId ??= (int) $preparation->test_kit_id;
        if ((int) $preparation->test_kit_id !== $kitId) {
            return null;
        }

        $kit = TestKit::query()
            ->where('institution_id', $institutionId)
            ->find($kitId);
        if ($kit === null) {
            return null;
        }
        if ($preparation->test_instrument_version_id !== null
            && ! $this->instrumentVersionMatchesKit((int) $preparation->test_instrument_version_id, $kit)) {
            return null;
        }

        if ($preparation->state === 'assembled') {
            $assembledVersion = TestKitVersion::query()
                ->where('test_kit_id', $kitId)
                ->where('institution_id', $institutionId)
                ->find((int) $preparation->assembled_test_kit_version_id);
            if ($assembledVersion === null
                || $authoringBookletVersionId !== null
                || ! $this->instrumentVersionMatchesKit(
                    (int) $assembledVersion->test_instrument_version_id,
                    $kit,
                )
                || ($assembledVersion->booklet_version_id === null
                    ? ($routeBookletId !== null || ! $this->versionDeclaresNoPaper($assembledVersion))
                    : ! $this->bookletVersionMatchesKit(
                        (int) $assembledVersion->booklet_version_id,
                        $kit,
                        $institutionId,
                        $routeBookletId,
                    ))) {
                return null;
            }

            return $this->context(
                $kit,
                null,
                $assembledVersion,
                true,
                null,
                $assembledVersion->template_version_id === null ? null : (int) $assembledVersion->template_version_id,
            );
        }

        $answerSheetDraftId = $preparation->answer_sheet_draft_id === null
            ? null
            : (int) $preparation->answer_sheet_draft_id;
        if ($answerSheetDraftId !== null
            && OmrTemplateAuthoringDraft::query()
                ->where('institution_id', $institutionId)
                ->where('is_synthetic', (bool) $kit->is_synthetic)
                ->whereKey($answerSheetDraftId)
                ->doesntExist()) {
            return null;
        }

        $templateVersionId = $preparation->template_version_id === null
            ? null
            : (int) $preparation->template_version_id;
        if ($templateVersionId !== null
            && OmrAnswerSheetTemplateVersion::query()
                ->whereKey($templateVersionId)
                ->whereHas('template', fn ($query) => $query
                    ->where('institution_id', $institutionId)
                    ->where('is_synthetic', (bool) $kit->is_synthetic))
                ->doesntExist()) {
            return null;
        }

        $targetBookletVersionId = $authoringBookletVersionId
            ?? ($preparation->booklet_version_id === null ? null : (int) $preparation->booklet_version_id);
        if ($routeBookletId !== null && $targetBookletVersionId === null) {
            return null;
        }
        if ($targetBookletVersionId !== null
            && ! $this->bookletVersionMatchesKit(
                $targetBookletVersionId,
                $kit,
                $institutionId,
                $routeBookletId,
            )) {
            return null;
        }

        return $this->context(
            $kit,
            $preparation,
            null,
            false,
            $answerSheetDraftId,
            $templateVersionId,
            $authoringBookletVersionId,
        );
    }

    /**
     * @return array{test_kit_id:int,preparation_id:?int,test_kit_version_id:?int,test_instrument_version_id:?int,booklet_version_id:?int,authoring_booklet_version_id:?int,answer_sheet_draft_id:?int,template_version_id:?int,back_url:string,code:string,is_synthetic:bool,read_only:bool}
     */
    private function context(
        TestKit $kit,
        ?TestKitPreparation $preparation,
        ?TestKitVersion $version,
        bool $readOnly,
        ?int $answerSheetDraftId = null,
        ?int $templateVersionId = null,
        ?int $authoringBookletVersionId = null,
    ): array {
        if ($answerSheetDraftId === null && $preparation?->answer_sheet_draft_id !== null) {
            $answerSheetDraftId = (int) $preparation->answer_sheet_draft_id;
        }
        if ($templateVersionId === null) {
            $selectedTemplateVersionId = $version?->template_version_id ?? $preparation?->template_version_id;
            $templateVersionId = $selectedTemplateVersionId === null ? null : (int) $selectedTemplateVersionId;
        }

        $routeParameters = ['testKit' => (int) $kit->getKey()];
        if ($version !== null) {
            $routeParameters['version'] = (int) $version->getKey();
        }

        $bookletVersionId = $version?->booklet_version_id ?? $preparation?->booklet_version_id;
        $testInstrumentVersionId = $version?->test_instrument_version_id
            ?? $preparation?->test_instrument_version_id;

        return [
            'test_kit_id' => (int) $kit->getKey(),
            'preparation_id' => $preparation === null ? null : (int) $preparation->getKey(),
            'test_kit_version_id' => $version === null ? null : (int) $version->getKey(),
            'test_instrument_version_id' => $testInstrumentVersionId === null ? null : (int) $testInstrumentVersionId,
            'booklet_version_id' => $bookletVersionId === null ? null : (int) $bookletVersionId,
            'authoring_booklet_version_id' => $authoringBookletVersionId,
            'answer_sheet_draft_id' => $answerSheetDraftId,
            'template_version_id' => $templateVersionId,
            'back_url' => route('setup.exam-materials.test-kits.show', $routeParameters, absolute: false),
            'code' => (string) $kit->code,
            'is_synthetic' => (bool) $kit->is_synthetic,
            'read_only' => $readOnly,
        ];
    }

    private function versionDeclaresNoPaper(TestKitVersion $version): bool
    {
        if ((int) $version->manifest_schema_version !== TestKitManifestBuilder::MANIFEST_SCHEMA_VERSION) {
            return false;
        }

        try {
            $resolved = $this->compositionContext->resolveVersion($version);
        } catch (RuntimeException) {
            return false;
        }

        $profile = $resolved['profile'] ?? null;
        $requirements = $resolved['requirements'] ?? null;

        return $profile instanceof TestInstrumentVersion
            && $profile->status === TestInstrumentVersion::STATUS_APPROVED
            && $profile->material_requirement === TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER
            && is_array($requirements)
            && ($requirements['required_resources'] ?? null) === []
            && ($resolved['booklet'] ?? null) === null
            && ($resolved['set'] ?? null) === null;
    }

    private function instrumentVersionMatchesKit(int $versionId, TestKit $kit): bool
    {
        return TestInstrumentVersion::query()
            ->whereKey($versionId)
            ->whereHas('instrument', fn ($query) => $query
                ->where('institution_id', (int) $kit->institution_id)
                ->where('is_synthetic', (bool) $kit->is_synthetic))
            ->exists();
    }

    private function bookletVersionMatchesKit(
        int $bookletVersionId,
        TestKit $kit,
        int $institutionId,
        ?int $routeBookletId,
    ): bool {
        return OmrQuestionBookletVersion::query()
            ->whereKey($bookletVersionId)
            ->whereHas('booklet', fn ($query) => $query
                ->where('institution_id', $institutionId)
                ->where('is_synthetic', (bool) $kit->is_synthetic)
                ->when($routeBookletId !== null, fn ($query) => $query->whereKey($routeBookletId)))
            ->exists();
    }

    private function institutionId(User $actor): ?int
    {
        try {
            return $this->access->institutionId($actor);
        } catch (AuthorizationException) {
            return null;
        }
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? null : (int) $validated;
    }
}

<?php

namespace App\Http\Controllers\Setup;

use App\Assessment\Queries\KitPairedAnswerKeyPreviewQuery;
use App\Assessment\Support\KitCompositionContext;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function fclose;
use function feof;
use function fpassthru;
use function fread;
use function hash_equals;
use function hash_final;
use function hash_init;
use function hash_update;
use function in_array;
use function is_numeric;
use function is_resource;
use function preg_match;
use function rewind;
use function str_contains;
use function strlen;
use function strtolower;

final class KitPairedAnswerKeyPreviewController extends Controller
{
    private const ALLOWED_RASTER_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly KitCompositionContext $composition,
        private readonly KitPairedAnswerKeyPreviewQuery $previewQuery,
    ) {}

    public function showPreparation(Request $request, TestKit $testKit): JsonResponse
    {
        $actor = $this->actor($request);
        $this->authorizePreview($actor, $testKit);
        $preparation = $this->preparationForRequest($request, $testKit);
        [, , $preview] = $this->preparationPreview($request, $actor, $testKit, $preparation);

        return $this->jsonResponse($preview);
    }

    public function preparationRaster(Request $request, TestKit $testKit, int $pageNo): StreamedResponse
    {
        $actor = $this->actor($request);
        $this->authorizePreview($actor, $testKit);
        $preparation = $this->preparationForRequest($request, $testKit);
        [, $sheet, $preview] = $this->preparationPreview($request, $actor, $testKit, $preparation, verifyAllRasterAssets: false);

        return $this->streamPage($sheet, $preview, $pageNo);
    }

    public function showVersion(Request $request, TestKit $testKit, TestKitVersion $version): JsonResponse
    {
        $actor = $this->actor($request);
        $this->authorizePreview($actor, $testKit);
        abort_unless((int) $version->test_kit_id === (int) $testKit->id, 404);
        [, , $preview] = $this->versionPreview($request, $actor, $testKit, $version);

        return $this->jsonResponse($preview);
    }

    public function versionRaster(Request $request, TestKit $testKit, TestKitVersion $version, int $pageNo): StreamedResponse
    {
        $actor = $this->actor($request);
        $this->authorizePreview($actor, $testKit);
        abort_unless((int) $version->test_kit_id === (int) $testKit->id, 404);
        [, $sheet, $preview] = $this->versionPreview($request, $actor, $testKit, $version, verifyAllRasterAssets: false);

        return $this->streamPage($sheet, $preview, $pageNo);
    }

    /** @return array{0:array<string,mixed>,1:OmrAnswerSheetTemplateVersion,2:array<string,mixed>} */
    private function preparationPreview(Request $request, User $actor, TestKit $testKit, TestKitPreparation $preparation, bool $verifyAllRasterAssets = true): array
    {
        try {
            $resolved = $this->composition->resolvePreparation($preparation);
        } catch (RuntimeException) {
            abort(409);
        }
        $this->assertPreparationContext($request, $preparation, $resolved);
        $sheet = $this->selectedSheet($resolved, $testKit);
        $keys = $this->validatedKeys($actor, $resolved);
        $context = $this->contextProjection($resolved, $preparation, null);
        $preview = $this->previewQuery->forKit(
            $sheet,
            (array) ($resolved['rows'] ?? []),
            $keys,
            $context,
            static fn (int $pageNo): string => route(
                'setup.exam-materials.test-kits.preparation.composition.preview.raster',
                [
                    'testKit' => $testKit->id,
                    'pageNo' => $pageNo,
                    'preparation_id' => $preparation->id,
                    'preparation_revision' => $context['preparation_revision'],
                    'composition_revision' => $context['composition_revision'],
                    'component_vector_sha256' => $context['component_vector_sha256'],
                ],
                absolute: false,
            ),
            $verifyAllRasterAssets,
            $this->requiredPhysicalKeyUids($resolved),
        );

        return [$resolved, $sheet, $preview];
    }

    /** @return array{0:array<string,mixed>,1:OmrAnswerSheetTemplateVersion,2:array<string,mixed>} */
    private function versionPreview(Request $request, User $actor, TestKit $testKit, TestKitVersion $version, bool $verifyAllRasterAssets = true): array
    {
        try {
            $resolved = $this->composition->resolveVersion($version);
        } catch (RuntimeException) {
            abort(409);
        }
        $this->assertVersionContext($request, $version, $resolved);
        $sheet = $this->selectedSheet($resolved, $testKit);
        $keys = $this->validatedKeys($actor, $resolved);
        $context = $this->contextProjection($resolved, null, $version);
        $preview = $this->previewQuery->forKit(
            $sheet,
            (array) ($resolved['rows'] ?? []),
            $keys,
            $context,
            static fn (int $pageNo): string => route(
                'setup.exam-materials.test-kits.version.composition.preview.raster',
                [
                    'testKit' => $testKit->id,
                    'version' => $version->id,
                    'pageNo' => $pageNo,
                    'composition_revision' => $context['composition_revision'],
                    'composition_sha256' => $context['composition_sha256'],
                    'manifest_sha256' => $context['manifest_sha256'],
                ],
                absolute: false,
            ),
            $verifyAllRasterAssets,
            $this->requiredPhysicalKeyUids($resolved),
        );

        return [$resolved, $sheet, $preview];
    }

    private function preparationForRequest(Request $request, TestKit $testKit): TestKitPreparation
    {
        $preparationId = (int) $request->query('preparation_id', 0);
        abort_if($preparationId < 1, 404);
        $preparation = TestKitPreparation::query()
            ->where('test_kit_id', (int) $testKit->id)
            ->whereKey($preparationId)
            ->firstOrFail();
        $currentPreparation = $testKit->openPreparation()->first();
        abort_unless(
            $currentPreparation !== null
                && (int) $currentPreparation->id === $preparationId
                && $preparation->state === TestKitPreparation::STATE_PREPARING,
            409,
        );

        return $preparation;
    }

    /** @param array<string,mixed> $resolved */
    private function assertPreparationContext(Request $request, TestKitPreparation $preparation, array $resolved): void
    {
        $this->assertQueryContext($request, [
            'preparation_id' => (int) $preparation->id,
            'preparation_revision' => (int) $preparation->revision,
            'composition_revision' => (int) ($resolved['composition_revision'] ?? -1),
            'component_vector_sha256' => (string) ($resolved['component_vector_sha256'] ?? ''),
        ]);
    }

    /** @param array<string,mixed> $resolved */
    private function assertVersionContext(Request $request, TestKitVersion $version, array $resolved): void
    {
        abort_unless((int) $version->test_kit_id > 0, 404);
        $this->assertQueryContext($request, [
            'composition_revision' => (int) ($resolved['composition_revision'] ?? -1),
            'composition_sha256' => (string) ($resolved['composition_sha256'] ?? ''),
            'manifest_sha256' => (string) ($resolved['manifest_sha256'] ?? ''),
        ]);
    }

    /** @param array<string,int|string> $expected */
    private function assertQueryContext(Request $request, array $expected): void
    {
        foreach ($expected as $key => $expectedValue) {
            $actualValue = $request->query($key);
            if (is_int($expectedValue)) {
                abort_unless(is_numeric($actualValue) && (int) $actualValue === $expectedValue, 409);

                continue;
            }
            abort_unless(is_string($actualValue) && $expectedValue !== '' && hash_equals($expectedValue, $actualValue), 409);
        }
    }

    /** @param array<string,mixed> $resolved
     * @return list<string>
     */
    private function requiredPhysicalKeyUids(array $resolved): array
    {
        $uids = [];
        foreach ((array) data_get($resolved, 'item_map.composition_rows', []) as $row) {
            if (($row['disposition'] ?? null) === 'physical'
                && ($row['key_required'] ?? false) === true
                && is_string($row['physical_item_uid'] ?? null)
                && $row['physical_item_uid'] !== '') {
                $uids[] = $row['physical_item_uid'];
            }
        }

        return $uids;
    }

    /** @param array<string,mixed> $resolved
     * @return list<array<string,mixed>>
     */
    private function validatedKeys(User $actor, array $resolved): array
    {
        if (($resolved['map_complete'] ?? false) !== true) {
            return [];
        }

        try {
            return $this->composition->readValidatedKeys($actor, $resolved);
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'stale')
                || str_contains($exception->getMessage(), 'changed after resolution')) {
                abort(409);
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $resolved */
    private function selectedSheet(array $resolved, TestKit $testKit): OmrAnswerSheetTemplateVersion
    {
        $sheetId = (int) ($resolved['template_version_id'] ?? 0);
        abort_if($sheetId < 1, 404);

        return OmrAnswerSheetTemplateVersion::query()
            ->whereKey($sheetId)
            ->whereHas('template', fn ($query) => $query->where('institution_id', (int) $testKit->institution_id))
            ->whereIn('status', [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE])
            ->firstOrFail();
    }

    /** @param array<string,mixed> $resolved
     * @return array<string,mixed>
     */
    private function contextProjection(array $resolved, ?TestKitPreparation $preparation, ?TestKitVersion $version): array
    {
        $compositionRevision = (int) ($resolved['composition_revision'] ?? 0);
        $mapComplete = (bool) ($resolved['map_complete'] ?? false);
        if ($preparation !== null) {
            return [
                'preparation_id' => (int) $preparation->id,
                'preparation_revision' => (int) $preparation->revision,
                'composition_revision' => $compositionRevision,
                'component_vector_sha256' => (string) ($resolved['component_vector_sha256'] ?? ''),
                'map_complete' => $mapComplete,
            ];
        }

        return [
            'test_kit_version_id' => (int) $version?->id,
            'composition_revision' => $compositionRevision,
            'composition_sha256' => (string) ($resolved['composition_sha256'] ?? ''),
            'manifest_sha256' => (string) ($resolved['manifest_sha256'] ?? ''),
            'map_complete' => $mapComplete,
        ];
    }

    /** @param array<string,mixed> $preview */
    private function streamPage(OmrAnswerSheetTemplateVersion $sheet, array $preview, int $pageNo): StreamedResponse
    {
        abort_unless(in_array($preview['state'] ?? null, ['ready', 'incomplete'], true), 404);
        abort_unless($pageNo > 0 && $pageNo <= (int) ($preview['page_count'] ?? 0), 404);
        $page = collect((array) ($preview['pages'] ?? []))->firstWhere('page_no', $pageNo);
        abort_unless(is_array($page) && isset($page['raster_sha256']), 404);
        $assets = $sheet->canonicalAssets()
            ->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)
            ->where('page_no', $pageNo)
            ->get();
        abort_unless($assets->count() === 1, 404);
        /** @var OmrTemplateAsset $asset */
        $asset = $assets->first();
        $mime = strtolower((string) $asset->mime);
        $sha256 = strtolower((string) $asset->sha256);
        abort_unless(in_array($mime, self::ALLOWED_RASTER_MIMES, true)
            && preg_match('/\A[a-f0-9]{64}\z/', $sha256) === 1
            && hash_equals($sha256, strtolower((string) $page['raster_sha256']))
            && (int) $asset->bytes > 0
            && (int) $asset->template_version_id === (int) $sheet->id
            && (int) $asset->page_no === $pageNo, 404);

        try {
            $stream = Storage::disk((string) $asset->disk)->readStream((string) $asset->path);
        } catch (Throwable) {
            abort(404);
        }
        abort_unless(is_resource($stream), 404);
        try {
            $prefix = fread($stream, 16384);
            $actualMime = is_string($prefix) ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($prefix) : false;
            abort_unless(is_string($actualMime) && strtolower($actualMime) === $mime && rewind($stream), 404);
            $hashContext = hash_init('sha256');
            $actualBytes = 0;
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                abort_unless(is_string($chunk) && ($chunk !== '' || feof($stream)), 404);
                if ($chunk !== '') {
                    hash_update($hashContext, $chunk);
                    $actualBytes += strlen($chunk);
                }
            }
            abort_unless($actualBytes === (int) $asset->bytes
                && hash_equals($sha256, hash_final($hashContext))
                && rewind($stream), 404);
        } catch (Throwable) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            abort(404);
        }

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $asset->bytes,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 404);

        return $actor;
    }

    private function authorizePreview(User $actor, TestKit $testKit): void
    {
        abort_unless((int) Institution::current()->id === (int) $testKit->institution_id, 404);
        $scope = ['office' => 'guidance', 'institution_id' => (int) $testKit->institution_id];
        foreach ([
            CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
            CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        ] as $capability) {
            abort_unless($this->authorizer->decideFresh($actor, $capability, $scope)->allowed, 404);
        }
    }

    /** @param array<string,mixed> $preview */
    private function jsonResponse(array $preview): JsonResponse
    {
        return response()->json($preview, 200, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

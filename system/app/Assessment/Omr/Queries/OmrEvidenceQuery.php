<?php

namespace App\Assessment\Omr\Queries;

use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrInvalidDetectionOutput;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Review\VerificationOmrReviewContextResolver;
use App\Assessment\Support\KitCompositionContext;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function array_values;
use function ceil;
use function count;
use function hash;
use function hash_equals;
use function in_array;
use function is_array;
use function is_finite;
use function is_numeric;
use function is_scalar;
use function json_encode;
use function max;
use function min;
use function preg_match;
use function strcasecmp;
use function strtolower;
use function strtoupper;
use function trim;
use function usort;

/**
 * The sole server-side read boundary for immutable OMR evidence.
 *
 * Authorization and method/capture binding happen before the immutable cache
 * key is consulted. Projection queries select only evidence columns and use
 * bounded eager loading for crop metadata; no provider payload or image bytes
 * enter the returned DTO.
 */
final class OmrEvidenceQuery
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly OmrEvidenceCache $cache,
        private readonly OmrReviewGeometryQuery $geometryQuery,
        private readonly VerificationOmrReviewContextResolver $verificationReviewResolver,
        private readonly OmrCaptureResolver $captureResolver,
        private readonly KitCompositionContext $kitCompositionContext,
    ) {}

    /**
     * Accepts the exact evidence query contract. The array form is retained as
     * a small adapter for callers that already validated request input.
     *
     * @param  string|array<string,mixed>  $method
     */
    public function paginate(
        OmrTemplateVerificationRun $run,
        User $actor,
        string|array $method = '',
        ?int $captureId = null,
        string $area = 'all',
        ?string $filter = null,
        int $page = 1,
        int $perPage = OmrEvidencePage::DEFAULT_PER_PAGE,
    ): OmrEvidencePage {
        if (is_array($method)) {
            $parameters = $method;
            $method = (string) ($parameters['method'] ?? '');
            $captureId = array_key_exists('capture_id', $parameters) && $parameters['capture_id'] !== null
                ? (int) $parameters['capture_id']
                : null;
            $area = (string) ($parameters['area'] ?? 'all');
            $filter = array_key_exists('filter', $parameters) && $parameters['filter'] !== null
                ? (string) $parameters['filter']
                : null;
            $page = (int) ($parameters['page'] ?? 1);
            $perPage = (int) ($parameters['per_page'] ?? OmrEvidencePage::DEFAULT_PER_PAGE);
        }
        $this->assertAccessible($run, $actor);
        $method = $this->normalizeMethod((string) $method);
        $area = $this->normalizeArea($area);
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $filter = $this->normalizeRequestedFilter($filter);

        $capture = $this->resolveCapture($run, $method, $captureId);
        $reviewEligibility = $this->verificationReviewResolver->eligibility($capture);

        $effectiveFilter = $filter ?? OmrEvidencePage::FILTER_ALL;
        $scopeDigest = $this->resourceScopeDigest($run, $actor);
        $reviewRevision = $this->verificationReviewRevisionMarker($capture);
        $authoring = is_array($run->draft?->authoring_json) ? $run->draft->authoring_json : [];
        $documentFieldsPayload = $authoring['studio']['document_fields']
            ?? $authoring['mapping']['input']['document_fields']
            ?? $authoring['canonical']['mapping']['document_fields']
            ?? $run->templateVersion?->authoring_manifest['studio']['document_fields']
            ?? $run->templateVersion?->authoring_manifest['mapping']['input']['document_fields']
            ?? $run->templateVersion?->authoring_manifest['canonical']['mapping']['document_fields']
            ?? $run->templateVersion?->authoring_manifest['document_fields']
            ?? $run->templateVersion?->review_manifest_json['document_fields']
            ?? [];
        $key = $this->cache->key([
            'institution_id' => $actor->actorScope()->institutionId,
            'actor_id' => (int) $actor->getAuthIdentifier(),
            'authority' => CapabilityCatalog::GUIDANCE_SCORING_OMR,
            'resource_scope_digest' => $scopeDigest,
            'run_id' => (int) $run->id,
            'method' => $method,
            'capture_id' => (int) $capture->id,
            'evidence_hash' => (string) $capture->evidence_hash,
            'evidence_version' => (string) ($capture->evidence_version ?: 'v1'),
            'asset_projection_digest' => $this->captureAssetProjectionDigest($capture),
            'document_fields_hash' => hash('sha256', json_encode($documentFieldsPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'review_revision' => $reviewRevision,
            'review_eligibility' => $reviewEligibility,
            'parent_version' => (int) ($capture->lock_version ?? 0),
            'area' => $area,
            'filter' => $effectiveFilter,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        $projection = $this->cache->remember($key, function () use ($run, $capture, $method, $area, $effectiveFilter, $page, $perPage, $reviewEligibility): array {
            return $this->buildProjection(
                run: $run,
                capture: $capture,
                method: $method,
                area: $area,
                filter: $effectiveFilter,
                page: $page,
                perPage: $perPage,
                reviewEligibility: $reviewEligibility,
            )->toArray();
        });

        if (! is_array($projection)) {
            // A malformed cache value is never an empty evidence result.
            return $this->buildProjection($run, $capture, $method, $area, $effectiveFilter, $page, $perPage, reviewEligibility: $reviewEligibility);
        }

        return $this->fromArray($projection);
    }

    /** Alias for query callers using the common query-object convention. */
    public function handle(
        OmrTemplateVerificationRun $run,
        User $actor,
        array $parameters,
    ): OmrEvidencePage {
        return $this->paginate($run, $actor, $parameters);
    }

    /**
     * Resolve and authorize one immutable capture before conditional requests.
     * Controllers can use this to derive an ETag without reading item rows.
     */
    public function captureFor(
        OmrTemplateVerificationRun $run,
        User $actor,
        string $method,
        ?int $captureId = null,
    ): OmrTemplateVerificationCapture {
        $this->assertAccessible($run, $actor);

        return $this->resolveCapture($run, $this->normalizeMethod($method), $captureId);
    }

    /**
     * Grading scanner adapter. Scanner proposals predate immutable verification
     * rows, so this method projects their already-persisted proposal items into
     * the same bounded DTO without exposing score/answer-key/provider fields.
     */
    public function paginateGradingSheet(
        OmrCaptureSheet $sheet,
        User $actor,
        string|array $method = 'pre_shaded',
        string $area = 'all',
        ?string $filter = null,
        int $page = 1,
        int $perPage = OmrEvidencePage::DEFAULT_PER_PAGE,
    ): OmrEvidencePage {
        $capturePageNo = null;
        if (is_array($method)) {
            $parameters = $method;
            $method = (string) ($parameters['method'] ?? 'pre_shaded');
            $area = (string) ($parameters['area'] ?? 'all');
            $filter = array_key_exists('filter', $parameters) && $parameters['filter'] !== null
                ? (string) $parameters['filter']
                : null;
            $page = (int) ($parameters['page'] ?? 1);
            $perPage = (int) ($parameters['per_page'] ?? OmrEvidencePage::DEFAULT_PER_PAGE);
            $capturePageNo = array_key_exists('page_no', $parameters) && $parameters['page_no'] !== null
                ? (int) $parameters['page_no']
                : (array_key_exists('capture_page_no', $parameters) && $parameters['capture_page_no'] !== null
                    ? (int) $parameters['capture_page_no']
                    : null);
            $blind = (bool) ($parameters['blind'] ?? false);
        } else {
            $blind = false;
        }
        $this->assertCapability($actor);
        $method = $this->normalizeMethod((string) $method);
        $area = $this->normalizeArea($area);
        $page = $this->normalizePage($page);
        $perPage = $this->normalizePerPage($perPage);
        $filter = $this->normalizeRequestedFilter($filter);
        if ($capturePageNo !== null && $capturePageNo < 1) {
            throw new InvalidArgumentException('Evidence capture page must be positive.');
        }

        $sheet->loadMissing([
            'batch.gradingSession',
            'batch.templateVersion',
            'batch.bookletVersion.questions.aptitudeArea',
            'batch.bookletVersion.questions.templateQuestion.options',
            'pages',
        ]);
        $session = $sheet->batch?->gradingSession;
        if ($session === null) {
            throw (new ModelNotFoundException)->setModel(OmrCaptureSheet::class, [$sheet->id]);
        }

        $geometry = $this->gradingGeometry($sheet);
        $rows = [];
        $areaPositions = [];
        foreach ($sheet->proposalItems() as $index => $proposalItem) {
            if (! is_array($proposalItem)) {
                continue;
            }
            $row = $this->gradingItemRow($sheet, $session, $proposalItem, $index, $geometry, $areaPositions, $blind);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        $rows = $this->sortRows($rows);
        $reviewMap = $this->latestGradingReviews(
            (int) $sheet->getKey(),
            array_values(array_unique(array_map(
                static fn (array $row): string => (string) $row['_item_key'],
                $rows,
            ))),
        );
        $rows = array_map(
            fn (array $row): array => $this->withReview($row, $reviewMap[(string) $row['_item_key']] ?? null, false),
            $rows,
        );

        $pageScopedUnfilteredRows = $this->filterRowsByAreaAndPage($rows, 'all', $capturePageNo);
        $scoped = $this->filterRowsByAreaAndPage($rows, $area, $capturePageNo);
        $total = count($scoped);
        $mismatchTotal = count(array_filter($scoped, fn (array $row): bool => $this->isMismatch($row)));
        $needsReviewTotal = count(array_filter($scoped, fn (array $row): bool => $this->needsReview($row)));
        $reviewedTotal = count(array_filter($scoped, fn (array $row): bool => $this->isReviewed($row)));
        $effectiveFilter = $filter ?? OmrEvidencePage::FILTER_ALL;
        $viewRows = array_values(array_filter(
            $scoped,
            fn (array $row): bool => $this->matchesFilter($row, $effectiveFilter),
        ));
        $filteredTotal = count($viewRows);
        $pageCount = max(1, (int) ceil($filteredTotal / $perPage));
        $page = max(1, min($page, $pageCount));
        $offset = ($page - 1) * $perPage;
        $selectedPage = $capturePageNo ?? (int) ($viewRows[0]['page_no'] ?? $rows[0]['page_no'] ?? 1);
        $assetUrls = $this->gradingAssetUrls($session, $sheet, $selectedPage);
        $evidenceVersion = 'scanner-'.(int) ($sheet->version ?? 0);
        $evidenceHash = hash('sha256', json_encode(
            array_map(fn (array $row): array => $this->hashableItem($row), $rows),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));

        return new OmrEvidencePage(
            method: $method,
            captureId: (int) $sheet->id,
            groupNavigation: $this->arrayGroupNavigation($pageScopedUnfilteredRows, $method, (int) $sheet->id, $perPage, $effectiveFilter),
            totals: [
                'total' => $total,
                'filtered_total' => $filteredTotal,
                'mismatch_total' => $mismatchTotal,
                'needs_review_total' => $needsReviewTotal,
                'reviewed_total' => $reviewedTotal,
                'page_count' => $pageCount,
            ],
            page: $page,
            perPage: $perPage,
            filter: $effectiveFilter,
            items: array_slice($viewRows, $offset, $perPage),
            lazyAssetUrls: $assetUrls,
            area: $area,
            total: $total,
            filteredTotal: $filteredTotal,
            mismatchTotal: $mismatchTotal,
            needsReviewTotal: $needsReviewTotal,
            reviewedTotal: $reviewedTotal,
            pageCount: $pageCount,
            evidenceVersion: $evidenceVersion,
            evidenceHash: $evidenceHash,
            attemptNo: 0,
            sheetMap: $this->gradingSheetMap($sheet, $session, $selectedPage, $this->sheetMap($rows, $selectedPage)),
        );
    }

    private function assertAccessible(OmrTemplateVerificationRun $run, User $actor): void
    {
        $this->assertCapability($actor);
        $run->loadMissing(['draft.owner', 'startedBy.staffProfile']);

        if ($run->draft_id !== null
            && ((int) ($run->draft?->owner_user_id ?? 0) !== (int) $actor->getAuthIdentifier())) {
            throw new AuthorizationException('Evidence resource scope is not available.');
        }

        // Compiled runs are institution-scoped by the actor that started the
        // run when both staff profiles carry an institution binding.
        $actorInstitution = $actor->actorScope()->institutionId;
        $ownerInstitution = $run->startedBy?->actorScope()->institutionId;
        if ($actorInstitution !== null && $ownerInstitution !== null && $actorInstitution !== $ownerInstitution) {
            throw new AuthorizationException('Evidence resource scope is not available.');
        }
    }

    private function assertCapability(User $actor): void
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR)) {
            throw new AuthorizationException('Evidence resource is not available.');
        }
    }

    private function resolveCapture(
        OmrTemplateVerificationRun $run,
        string $method,
        ?int $captureId,
    ): OmrTemplateVerificationCapture {
        $methodRow = OmrTemplateVerificationMethod::query()
            ->select(['id', 'run_id', 'method'])
            ->where('run_id', $run->id)
            ->where('method', $method)
            ->with(['pages' => static fn ($query) => $query
                ->select(['id', 'method_id', 'page_no', 'selected_attempt_id'])
                ->orderBy('page_no')])
            ->first();

        if ($methodRow === null) {
            throw (new ModelNotFoundException)->setModel(OmrTemplateVerificationMethod::class, [$run->id, $method]);
        }

        $captureQuery = OmrTemplateVerificationCapture::query()
            ->select([
                'id', 'run_id', 'method_id', 'challenge_id', 'method', 'page_no', 'attempt', 'attempt_no',
                'attempt_state', 'status', 'actual', 'failure_code', 'target_kind', 'target_id', 'target_version',
                'target_hash', 'plan_version', 'plan_hash', 'expected_vector_hash', 'vector_match',
                'discarded_at', 'lock_version', 'evidence_version', 'evidence_hash', 'input_hash',
                'page_manifest_hash', 'input_asset_hash',
                'input_asset_id', 'aligned_asset_hash', 'aligned_asset_id', 'overlay_asset_hash',
                'overlay_asset_id', 'summary',
            ])
            ->with([
                'inputAsset:id,disk,path,mime,bytes,sha256,metadata,page_no',
                'alignedAsset:id,disk,path,mime,bytes,sha256,metadata,page_no',
                'overlayAsset:id,disk,path,mime,bytes,sha256,metadata,page_no',
            ])
            ->where('run_id', $run->id)
            ->where('method_id', $methodRow->id)
            ->where('method', $method);

        if ($captureId !== null) {
            if ($captureId < 1) {
                throw new InvalidArgumentException('capture_id must be positive.');
            }
            $capture = $captureQuery->whereKey($captureId)->first();
        } else {
            $selectedIds = $methodRow->pages
                ->sortBy('page_no')
                ->pluck('selected_attempt_id')
                ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
                ->values();
            $capture = $selectedIds->isEmpty()
                ? null
                : $captureQuery->whereIn('id', $selectedIds->all())
                    ->orderByRaw('CASE id '.implode(' ', array_map(static fn (int $id, int $index): string => "WHEN {$id} THEN {$index}", $selectedIds->all(), array_keys($selectedIds->all()))).' ELSE 999999 END')
                    ->first();
        }

        if ($capture === null
            || (int) $capture->run_id !== (int) $run->id
            || (int) $capture->method_id !== (int) $methodRow->id
            || (string) $capture->method !== $method) {
            throw (new ModelNotFoundException)->setModel(OmrTemplateVerificationCapture::class, [$captureId ?? 0]);
        }

        return $capture;
    }

    private function captureAssetProjectionDigest(OmrTemplateVerificationCapture $capture): string
    {
        return hash('sha256', json_encode(
            array_map(
                static fn (mixed $asset): ?array => $asset === null ? null : [
                    'id' => (int) $asset->id,
                    'sha256' => (string) $asset->sha256,
                    'metadata' => is_array($asset->metadata) ? $asset->metadata : [],
                ],
                [$capture->inputAsset, $capture->alignedAsset, $capture->overlayAsset],
            ),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }

    private function verificationReviewRevisionMarker(OmrTemplateVerificationCapture $capture): int
    {
        $lockVersion = (int) ($capture->lock_version ?? 0);
        $latestRevisionId = (int) (OmrItemReviewRevision::query()
            ->whereExists(static function (\Illuminate\Database\Query\Builder $subquery) use ($capture): void {
                $subquery->selectRaw('1')
                    ->from('omr_template_verification_item_evidence as evidence')
                    ->whereColumn('evidence.id', 'omr_item_review_revisions.verification_item_evidence_id')
                    ->where('evidence.capture_id', $capture->id);
            })
            ->max('id') ?? 0);

        return max($lockVersion, $latestRevisionId);
    }

    /** @return array{total:int,mismatch_total:int,needs_review_total:int,reviewed_total:int} */
    private function counts(OmrTemplateVerificationCapture $capture, string $area, bool $blankNegative): array
    {
        $total = $this->itemQuery($capture, $area)->count();
        $mismatchQuery = $this->itemQuery($capture, $area);
        $this->applyMismatchFilter($mismatchQuery, $blankNegative);
        $needsQuery = $this->itemQuery($capture, $area);
        $this->applyVerificationFilter($needsQuery, OmrEvidencePage::FILTER_NEEDS_REVIEW, $blankNegative);
        $reviewedQuery = $this->itemQuery($capture, $area);
        $this->applyVerificationFilter($reviewedQuery, OmrEvidencePage::FILTER_REVIEWED, $blankNegative);

        return [
            'total' => (int) $total,
            'mismatch_total' => (int) $mismatchQuery->count(),
            'needs_review_total' => (int) $needsQuery->count(),
            'reviewed_total' => (int) $reviewedQuery->count(),
        ];
    }

    /**
     * @param  array{total:int,mismatch_total:int,needs_review_total:int,reviewed_total:int}|null  $previewCounts
     */
    private function buildProjection(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationCapture $capture,
        string $method,
        string $area,
        string $filter,
        int $page,
        int $perPage,
        ?array $previewCounts = null,
        ?array $reviewEligibility = null,
    ): OmrEvidencePage {
        $reviewEligibility ??= $this->verificationReviewResolver->eligibility($capture);
        $run->loadMissing(['draft', 'templateVersion']);
        $blankNegative = OmrSourcePolicy::sourceKindForRun($run) === OmrSourcePolicy::UPLOADED_ORIGINAL
            && $capture->method === OmrProofPlan::METHOD_PRE_SHADED
            && in_array($run->mode, [
                OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
                OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            ], true)
            && hash_equals(
                hash('sha256', json_encode([], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                (string) ($capture->expected_vector_hash ?: $run->expected_vector_hash),
            );
        $summary = is_array($capture->summary) ? $capture->summary : [];
        $captureEvidence = is_array($summary['evidence'] ?? null) ? $summary['evidence'] : [];
        $providerEvidenceByLabel = [];
        foreach ((array) ($captureEvidence['items'] ?? []) as $providerEvidence) {
            if (! is_array($providerEvidence)
                || (int) ($providerEvidence['page_no'] ?? $capture->page_no) !== (int) $capture->page_no) {
                continue;
            }
            $label = $this->scalarString($providerEvidence['native_label'] ?? null);
            if ($label === null) {
                continue;
            }
            if (array_key_exists($label, $providerEvidenceByLabel)) {
                $providerEvidenceByLabel[$label] = null;

                continue;
            }
            $providerEvidenceByLabel[$label] = $providerEvidence;
        }
        $counts = $previewCounts ?? $this->counts($capture, $area, $blankNegative);
        $viewQuery = $this->itemQuery($capture, $area);
        $this->applyVerificationFilter($viewQuery, $filter, $blankNegative);
        $filteredTotal = (int) $viewQuery->count();
        $pageCount = max(1, (int) ceil($filteredTotal / $perPage));
        $page = max(1, min($page, $pageCount));
        $rows = $viewQuery
            ->with(['cropAsset:id,disk,path,mime,bytes,sha256,metadata,page_no'])
            ->orderByRaw("CASE group_type WHEN 'aptitude_area' THEN 0 WHEN 'identity' THEN 1 WHEN 'calibration' THEN 2 ELSE 3 END")
            ->orderByRaw('CASE WHEN area_position IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(area_position, 2147483647)')
            ->orderByRaw('CASE WHEN question_no IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(question_no, 2147483647)')
            ->orderByRaw("COALESCE(item_id, '')")
            ->orderBy('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
        $reviewMap = $this->latestVerificationReviews($rows->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());
        $items = $rows->map(
            fn (OmrTemplateVerificationItemEvidence $item): array => $this->itemRow(
                $run,
                $capture,
                $item,
                $reviewMap[(int) $item->getKey()] ?? null,
                $providerEvidenceByLabel[(string) $item->item_id] ?? null,
                $blankNegative,
                (bool) $reviewEligibility['reviewable'],
                (int) ($capture->lock_version ?? 0),
            ),
        )->values()->all();
        $groups = $this->groupNavigation($run, $capture, $method, $perPage, $filter, $blankNegative);
        $lazyAssetUrls = $this->captureAssetUrls($run, $capture);

        return new OmrEvidencePage(
            method: $method,
            captureId: (int) $capture->id,
            groupNavigation: $groups,
            totals: [
                'total' => $counts['total'],
                'filtered_total' => $filteredTotal,
                'mismatch_total' => $counts['mismatch_total'],
                'needs_review_total' => $counts['needs_review_total'],
                'reviewed_total' => $counts['reviewed_total'],
                'page_count' => $pageCount,
            ],
            page: $page,
            perPage: $perPage,
            filter: $filter,
            items: $items,
            lazyAssetUrls: $lazyAssetUrls,
            area: $area,
            areaPosition: $this->areaPosition($groups, $area),
            total: $counts['total'],
            filteredTotal: $filteredTotal,
            mismatchTotal: $counts['mismatch_total'],
            needsReviewTotal: $counts['needs_review_total'],
            reviewedTotal: $counts['reviewed_total'],
            pageCount: $pageCount,
            reviewable: (bool) $reviewEligibility['reviewable'],
            parentVersion: (int) ($capture->lock_version ?? 0),
            reviewReasons: $reviewEligibility['reasons'],
            reviewNoteMax: 2000,
            includeReviewMetadata: true,
            evidenceVersion: (string) ($capture->evidence_version ?: 'v1'),
            evidenceHash: (string) $capture->evidence_hash,
            attemptNo: (int) ($capture->attempt_no ?? $capture->attempt),
            capturePageNo: (int) $capture->page_no,
            sheetMap: $this->verificationSheetMap($run, $capture),
        );
    }

    /** @return Builder<OmrTemplateVerificationItemEvidence> */
    private function itemQuery(OmrTemplateVerificationCapture $capture, string $area): Builder
    {
        $query = OmrTemplateVerificationItemEvidence::query()
            ->where('capture_id', $capture->id)
            ->where('group_type', '<>', OmrTemplateVerificationItemEvidence::GROUP_CALIBRATION);

        if ($area === 'identity') {
            $query->where('group_type', OmrTemplateVerificationItemEvidence::GROUP_IDENTITY);
        } elseif ($area === 'calibration') {
            $query->whereRaw('1 = 0');
        } elseif ($area !== 'all') {
            $query->where('group_type', OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA)
                ->where('area_key', $area);
        }

        return $query;
    }

    private function applyMismatchFilter(Builder $query, bool $blankNegative): void
    {
        if ($blankNegative) {
            $query->where(function (Builder $negative): void {
                $negative->whereNotNull('detected')
                    ->orWhereIn('state', [
                        OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                        OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                        'unreadable',
                    ])
                    ->orWhereIn('status', [
                        OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                        OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                        'unreadable',
                    ])
                    ->orWhereNull('options')
                    ->orWhereNull('crop_rect');
            });

            return;
        }

        $query->whereNotNull('expected')
            ->where(function (Builder $nested): void {
                $nested->whereIn('state', [
                    OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    OmrTemplateVerificationItemEvidence::STATE_MISSING,
                ])->orWhereIn('status', [
                    OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    OmrTemplateVerificationItemEvidence::STATE_MISSING,
                ]);
            });
    }

    private function applyVerificationFilter(Builder $query, string $filter, bool $blankNegative): void
    {
        if ($filter === OmrEvidencePage::FILTER_ALL) {
            return;
        }
        if ($filter === OmrEvidencePage::FILTER_MISMATCH) {
            $this->applyMismatchFilter($query, $blankNegative);

            return;
        }
        if ($filter === OmrEvidencePage::FILTER_REVIEWED) {
            $this->whereValidVerificationReview($query);

            return;
        }

        $query->where(function (Builder $needsReview) use ($blankNegative): void {
            $needsReview->where(function (Builder $hasBadReview): void {
                $this->whereLatestVerificationReview($hasBadReview, static function ($latest): void {
                    $latest->where(function ($invalid): void {
                        $invalid
                            ->where('review.decision', 'unresolved')
                            ->orWhereNull('review.reviewed_machine_evidence_hash')
                            ->orWhereColumn(
                                'review.reviewed_machine_evidence_hash',
                                '<>',
                                'omr_template_verification_item_evidence.evidence_hash',
                            );
                    });
                });
            })->orWhere(function (Builder $unreviewedNeeds) use ($blankNegative): void {
                $unreviewedNeeds->whereNot(function (Builder $valid): void {
                    $this->whereValidVerificationReview($valid);
                });
                if (! $blankNegative) {
                    $unreviewedNeeds->whereNotNull('omr_template_verification_item_evidence.expected');
                }
                $unreviewedNeeds->where(function (Builder $machine) use ($blankNegative): void {
                    $states = [OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS, 'unreadable'];
                    if (! $blankNegative) {
                        $states[] = OmrTemplateVerificationItemEvidence::STATE_MISSING;
                    }
                    $machine->whereIn('state', $states)
                        ->orWhereIn('status', $states)
                        ->orWhere(function (Builder $mismatch) use ($blankNegative): void {
                            $this->applyMismatchFilter($mismatch, $blankNegative);
                        });
                });
            });
        });
    }

    private function whereValidVerificationReview(Builder $query): void
    {
        $this->whereLatestVerificationReview($query, static function ($review): void {
            $review
                ->whereIn('review.decision', ['confirm_machine', 'set_option', 'blank'])
                ->whereColumn(
                    'review.reviewed_machine_evidence_hash',
                    'omr_template_verification_item_evidence.evidence_hash',
                );
        });
    }

    private function whereLatestVerificationReview(Builder $query, callable $extra): void
    {
        $query->whereExists(function ($review) use ($extra): void {
            $review
                ->selectRaw('1')
                ->from('omr_item_review_revisions as review')
                ->whereColumn(
                    'review.verification_item_evidence_id',
                    'omr_template_verification_item_evidence.id',
                )
                ->whereColumn(
                    'review.item_key',
                    'omr_template_verification_item_evidence.item_id',
                )
                ->whereNotExists(function ($newer): void {
                    $newer
                        ->selectRaw('1')
                        ->from('omr_item_review_revisions as newer_review')
                        ->whereColumn(
                            'newer_review.verification_item_evidence_id',
                            'review.verification_item_evidence_id',
                        )
                        ->whereColumn('newer_review.item_key', 'review.item_key')
                        ->whereColumn('newer_review.revision', '>', 'review.revision');
                });
            $extra($review);
        });
    }

    /** @return list<array<string,mixed>> */
    private function groupNavigation(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationCapture $capture,
        string $method,
        int $perPage,
        string $filter,
        bool $blankNegative,
    ): array {
        $groups = OmrTemplateVerificationItemEvidence::query()
            ->where('capture_id', $capture->id)
            ->where('group_type', '<>', OmrTemplateVerificationItemEvidence::GROUP_CALIBRATION)
            ->select(['group_type', 'area_key'])
            ->selectRaw('MIN(area_position) AS group_position')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw(
                $blankNegative
                    ? 'SUM(CASE WHEN detected IS NOT NULL OR state IN (?, ?, ?) OR status IN (?, ?, ?) OR options IS NULL OR crop_rect IS NULL THEN 1 ELSE 0 END) AS mismatch_total'
                    : 'SUM(CASE WHEN expected IS NOT NULL AND (state IN (?, ?, ?) OR status IN (?, ?, ?)) THEN 1 ELSE 0 END) AS mismatch_total',
                $blankNegative ? [
                    OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    'unreadable',
                    OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    'unreadable',
                ] : [
                    OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    OmrTemplateVerificationItemEvidence::STATE_MISSING,
                    OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    OmrTemplateVerificationItemEvidence::STATE_MISSING,
                ],
            )
            ->groupBy('group_type', 'area_key')
            ->get()
            ->sort(static function ($left, $right): int {
                $rank = static fn ($group): int => match ((string) $group->group_type) {
                    OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA => 0,
                    OmrTemplateVerificationItemEvidence::GROUP_IDENTITY => 1,
                    default => 2,
                };
                $comparison = $rank($left) <=> $rank($right);
                if ($comparison !== 0) {
                    return $comparison;
                }
                $positionComparison = ((int) ($left->group_position ?? PHP_INT_MAX)) <=> ((int) ($right->group_position ?? PHP_INT_MAX));
                if ($positionComparison !== 0) {
                    return $positionComparison;
                }

                return strcasecmp((string) $left->area_key, (string) $right->area_key);
            })
            ->values();

        $allTotal = (int) $groups->sum(fn ($group): int => (int) $group->total);
        $allMismatch = (int) $groups->sum(fn ($group): int => (int) $group->mismatch_total);
        $groupFilteredTotals = [];
        if ($filter === OmrEvidencePage::FILTER_REVIEWED || $filter === OmrEvidencePage::FILTER_NEEDS_REVIEW) {
            $filteredGroupQuery = OmrTemplateVerificationItemEvidence::query()
                ->where('capture_id', $capture->id)
                ->where('group_type', '<>', OmrTemplateVerificationItemEvidence::GROUP_CALIBRATION)
                ->select(['group_type', 'area_key'])
                ->selectRaw('COUNT(*) as filtered_total')
                ->groupBy('group_type', 'area_key');
            $this->applyVerificationFilter($filteredGroupQuery, $filter, $blankNegative);
            foreach ($filteredGroupQuery->get() as $groupRow) {
                $groupType = (string) $groupRow->group_type;
                $groupKey = $groupType === OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA
                    ? (string) $groupRow->area_key
                    : $groupType;
                $groupFilteredTotals[$groupKey] = (int) $groupRow->filtered_total;
            }
        }
        $allPageCount = match ($filter) {
            OmrEvidencePage::FILTER_MISMATCH => max(1, (int) ceil($allMismatch / $perPage)),
            OmrEvidencePage::FILTER_REVIEWED, OmrEvidencePage::FILTER_NEEDS_REVIEW => max(1, (int) ceil(array_sum($groupFilteredTotals) / $perPage)),
            default => max(1, (int) ceil($allTotal / $perPage)),
        };
        $navigation = [[
            'key' => 'all',
            'label' => 'All areas',
            'group_type' => 'all',
            'area_key' => null,
            'area_position' => null,
            'total' => $allTotal,
            'mismatch_total' => $allMismatch,
            'page_count' => $allPageCount,
            'url' => $this->evidenceUrl($run, $method, (int) $capture->id, 'all', $filter, 1, $perPage),
        ]];

        foreach ($groups as $group) {
            $groupType = (string) $group->group_type;
            $key = $groupType === OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA
                ? (string) $group->area_key
                : $groupType;
            $groupTotal = (int) $group->total;
            $groupMismatch = (int) $group->mismatch_total;
            $groupPageCount = match ($filter) {
                OmrEvidencePage::FILTER_MISMATCH => max(1, (int) ceil($groupMismatch / $perPage)),
                OmrEvidencePage::FILTER_REVIEWED, OmrEvidencePage::FILTER_NEEDS_REVIEW => max(1, (int) ceil(($groupFilteredTotals[$key] ?? 0) / $perPage)),
                default => max(1, (int) ceil($groupTotal / $perPage)),
            };
            $navigation[] = [
                'key' => $key,
                'label' => $groupType === OmrTemplateVerificationItemEvidence::GROUP_IDENTITY ? 'Identity' : $key,
                'group_type' => $groupType,
                'area_key' => $group->area_key,
                'area_position' => $group->group_position !== null ? (int) $group->group_position : null,
                'total' => $groupTotal,
                'mismatch_total' => $groupMismatch,
                'page_count' => $groupPageCount,
                'url' => $this->evidenceUrl($run, $method, (int) $capture->id, $key, $filter, 1, $perPage),
            ];
        }

        return $navigation;
    }

    /** @param list<array<string,mixed>> $groups */
    private function areaPosition(array $groups, string $area): ?int
    {
        foreach ($groups as $group) {
            if (($group['key'] ?? null) === $area) {
                return is_numeric($group['area_position'] ?? null) ? (int) $group['area_position'] : null;
            }
        }

        return null;
    }

    /** @return array<string,string|null> */
    private function captureAssetUrls(OmrTemplateVerificationRun $run, OmrTemplateVerificationCapture $capture): array
    {
        return [
            'input' => $this->assetUrl($run, $capture->inputAsset, $capture->input_asset_hash ?: $capture->inputAsset?->sha256),
            'aligned' => $this->assetUrl($run, $capture->alignedAsset, $capture->aligned_asset_hash ?: $capture->alignedAsset?->sha256),
            'overlay' => $this->assetUrl($run, $capture->overlayAsset, $capture->overlay_asset_hash ?: $capture->overlayAsset?->sha256),
        ];
    }

    /** @return array<string,mixed> */
    private function itemRow(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationCapture $capture,
        OmrTemplateVerificationItemEvidence $item,
        ?OmrItemReviewRevision $review,
        ?array $providerEvidence,
        bool $blankNegative,
        bool $reviewable = false,
        int $parentVersion = 0,
    ): array {
        $machine = $this->verificationMachine($item, $providerEvidence);
        $expected = $this->scalarString($item->expected);
        $geometry = $this->verificationGeometry($run, $capture, $item);
        $issues = array_values(array_unique([
            ...$machine['issue_codes'],
            ...$geometry['issues'],
        ]));

        return $this->withReview([
            'id' => (int) $item->id,
            'item_id' => (string) $item->item_id,
            'group_type' => (string) $item->group_type,
            'group' => (string) $item->group_type,
            'area_key' => $item->area_key,
            'area_position' => $item->area_position !== null ? (int) $item->area_position : null,
            'question_no' => $item->question_no !== null ? (int) $item->question_no : null,
            'page_no' => (int) $capture->page_no,
            'parent_version' => $parentVersion,
            'item_revision' => (int) ($review?->revision ?? 0),
            'reviewable' => $reviewable
                && $item->group_type === OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA,
            'machine' => $machine,
            'expected' => ['option' => $expected],
            'geometry' => $geometry['geometry'],
            'issues' => $issues,
            'provenance' => $geometry['provenance'] + [
                'machine' => [
                    'source' => 'verification_item_evidence',
                    'version' => (string) ($item->evidence_version ?: 'v1'),
                    'hash' => $machine['evidence_hash'],
                    'legacy_evidence_limited' => $machine['legacy_evidence_limited'],
                ],
                'expected' => [
                    'source' => $expected === null ? 'unavailable' : 'verification_expected_vector',
                    'available' => $expected !== null,
                ],
            ],
            '_context' => 'verification',
            '_expected_blank' => $blankNegative,
            '_machine_state' => $machine['state'],
            '_machine_selected_option' => $machine['selected_option'],
            '_machine_evidence_hash' => $machine['evidence_hash'],
        ], $review, true);
    }

    /**
     * @return array{
     *     state:string,
     *     selected_option:string|null,
     *     observed_options:list<string>,
     *     option_observations:list<array{code:string,mean:float|null,classification:string|null}>,
     *     confidence:float|null,
     *     issue_codes:list<string>,
     *     evidence_hash:string|null,
     *     legacy_evidence_limited:bool
     * }
     */
    private function verificationMachine(OmrTemplateVerificationItemEvidence $item, ?array $providerEvidence): array
    {
        $detected = is_array($providerEvidence['detected'] ?? null) ? $providerEvidence['detected'] : null;
        if ($providerEvidence !== null
            && (string) ($providerEvidence['native_label'] ?? '') === (string) $item->item_id
            && (int) ($providerEvidence['question_no'] ?? 0) === (int) $item->question_no
            && strtoupper((string) ($providerEvidence['area_code'] ?? '')) === strtoupper((string) $item->area_key)
            && is_array($detected)
            && array_key_exists('observed_options', $detected)
            && array_key_exists('option_observations', $detected)) {
            try {
                $observation = OmrDetectedItem::fromArray([
                    'schema_version' => OmrDetectedItem::SCHEMA_VERSION,
                    'position' => $providerEvidence['position'] ?? null,
                    'area_code' => $providerEvidence['area_code'] ?? null,
                    'question_no' => $providerEvidence['question_no'] ?? null,
                    'state' => $detected['state'] ?? null,
                    'selected_option' => $detected['selected_option'] ?? null,
                    'confidence' => $detected['confidence'] ?? null,
                    'region' => $providerEvidence['region'] ?? null,
                    'observed_options' => $detected['observed_options'],
                    'option_observations' => $detected['option_observations'],
                    'issue_codes' => $detected['issue_codes'] ?? [],
                ]);
                if (! in_array($observation->state, OmrDetectedItem::STATES, true)) {
                    throw new OmrInvalidDetectionOutput('Stored provider observation state is unsupported.');
                }
                $payload = $observation->toArray();

                return [
                    'state' => $payload['state'],
                    'selected_option' => $payload['selected_option'],
                    'observed_options' => $payload['observed_options'],
                    'option_observations' => $payload['option_observations'],
                    'confidence' => $payload['confidence'],
                    'issue_codes' => $payload['issue_codes'],
                    'evidence_hash' => $this->hashOrNull($item->evidence_hash),
                    'legacy_evidence_limited' => false,
                ];
            } catch (OmrInvalidDetectionOutput|InvalidArgumentException $exception) {
                return [
                    'state' => 'unreadable',
                    'selected_option' => null,
                    'observed_options' => [],
                    'option_observations' => [],
                    'confidence' => null,
                    'issue_codes' => ['candidate_evidence_incomplete'],
                    'evidence_hash' => $this->hashOrNull($item->evidence_hash),
                    'legacy_evidence_limited' => true,
                ];
            }
        }
        $options = $this->safeOptions($item->options);
        $means = $this->safeMeans($item->means) ?? [];
        $observed = [];
        $observations = [];
        $hasCandidateEvidence = false;
        foreach ($options as $option) {
            $code = $this->scalarString($option['code'] ?? ($option['option'] ?? null));
            if ($code === null) {
                continue;
            }
            $classification = $this->scalarString($option['classification'] ?? ($option['threshold_status'] ?? null));
            $isObserved = ($option['observed'] ?? null) === true
                || in_array(strtolower((string) $classification), ['observed', 'marked'], true);
            if (array_key_exists('observed', $option)
                || in_array(strtolower((string) $classification), ['observed', 'not_observed', 'marked', 'unmarked'], true)) {
                $hasCandidateEvidence = true;
            }
            if ($isObserved && ! in_array($code, $observed, true)) {
                $observed[] = $code;
            }
            $mean = $option['mean'] ?? ($means[$code] ?? null);
            $observations[] = [
                'code' => $code,
                'mean' => is_numeric($mean) ? (float) $mean : null,
                'classification' => $classification,
            ];
        }

        $detected = $this->scalarString($item->detected);
        $rawState = strtolower(trim((string) ($item->state ?: $item->status)));
        $state = match ($rawState) {
            OmrTemplateVerificationItemEvidence::STATE_MATCH,
            OmrTemplateVerificationItemEvidence::STATE_MISMATCH => $detected === null ? 'blank' : 'marked',
            OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS => 'ambiguous',
            OmrTemplateVerificationItemEvidence::STATE_MISSING => 'blank',
            'unreadable' => 'unreadable',
            default => $detected === null ? 'unreadable' : 'marked',
        };
        $selected = $state === 'marked' ? $detected : null;
        $evidenceHash = $this->hashOrNull($item->evidence_hash);
        $issues = [];
        if (! $hasCandidateEvidence) {
            $issues[] = 'legacy_evidence_limited';
        }
        if ($evidenceHash === null) {
            $issues[] = 'missing_evidence_hash';
        }
        if ($state === 'marked' && $selected === null) {
            $issues[] = 'machine_cardinality_limited';
        }

        return [
            'state' => $state,
            'selected_option' => $selected,
            'observed_options' => $observed,
            'option_observations' => $observations,
            'confidence' => is_numeric($means['confidence'] ?? null) ? (float) $means['confidence'] : null,
            'issue_codes' => array_values(array_unique($issues)),
            'evidence_hash' => $evidenceHash,
            'legacy_evidence_limited' => ! $hasCandidateEvidence,
        ];
    }

    /** @return array{geometry:array<string,mixed>,issues:list<string>,provenance:array<string,mixed>} */
    private function verificationGeometry(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationCapture $capture,
        OmrTemplateVerificationItemEvidence $item,
    ): array {
        $itemRect = $this->normalizedRegion($item->crop_rect);
        $options = [];
        foreach ($this->safeOptions($item->options) as $option) {
            $code = $this->scalarString($option['code'] ?? ($option['option'] ?? null));
            $region = $this->normalizedRegion($option['region'] ?? ($option['rect'] ?? null));
            if ($code !== null && $region !== null) {
                $options[] = ['code' => $code, 'region' => $region];
            }
        }
        if ($options === [] && $item->group_type === OmrTemplateVerificationItemEvidence::GROUP_IDENTITY) {
            $options = $this->manifestOptions(
                $run,
                (string) $item->item_id,
                (int) $capture->page_no,
            );
            if ($itemRect === null && $options !== []) {
                $itemRect = $this->unionRegions(array_column($options, 'region'));
            }
        }
        $manifestHash = $this->hashOrNull($capture->page_manifest_hash)
            ?? $this->hashOrNull($run->templateVersion?->review_manifest_hash);
        $aligned = $this->assetProvenance(
            $run,
            $capture->alignedAsset,
            $capture->aligned_asset_hash ?: $capture->alignedAsset?->sha256,
            'aligned_page',
            (int) $capture->page_no,
        );
        $cropHash = $this->hashOrNull($item->crop_asset_hash ?: $item->cropAsset?->sha256);
        $crop = $item->cropAsset === null
            ? null
            : $this->assetProvenance($run, $item->cropAsset, $cropHash, 'dedicated_crop', (int) $capture->page_no);
        $issues = [];
        if ($itemRect === null) {
            $issues[] = 'missing_item_geometry';
        }
        if ($options === []) {
            $issues[] = 'missing_option_geometry';
        }
        if ($manifestHash === null) {
            $issues[] = 'missing_geometry_hash';
        }
        if ($aligned === null) {
            $issues[] = 'missing_aligned_asset';
        } elseif (($aligned['width'] ?? null) === null || ($aligned['height'] ?? null) === null) {
            $issues[] = 'missing_asset_dimensions';
        } elseif ($this->hashOrNull($capture->aligned_asset_hash ?: $capture->alignedAsset?->sha256) === null) {
            $issues[] = 'missing_asset_hash';
        }
        $rawTransform = $this->safeArray($item->cropAsset?->metadata['transform'] ?? null);
        $transform = $rawTransform ?? ($crop !== null ? [
            'source_rect' => $itemRect,
            'rect' => $itemRect,
            'scale_x' => 1.0,
            'scale_y' => 1.0,
            'translate_x' => 0.0,
            'translate_y' => 0.0,
        ] : null);
        $source = $crop !== null
            ? [
                'kind' => 'dedicated_crop',
                'type' => 'dedicated_crop',
                'source_rect' => $itemRect,
                'rect' => $itemRect,
                'aligned_rect' => $itemRect,
                'transform' => $transform,
            ]
            : ($aligned === null ? null : [
                'kind' => 'aligned_page',
                'type' => 'aligned_page',
                'source_rect' => $itemRect,
                'rect' => $itemRect,
                'aligned_rect' => $itemRect,
                'transform' => null,
            ]);
        $status = $issues === [] ? 'available' : 'unavailable';

        return [
            'geometry' => [
                'item_rect' => $itemRect,
                'crop_rect' => $itemRect,
                'options' => $options,
                'aligned_asset' => $aligned,
                'crop_asset' => $crop,
                'crop_source' => $source,
                'geometry_manifest_hash' => $manifestHash,
                'evidence_version' => (string) ($capture->evidence_version ?: 'v1'),
                'page_no' => (int) $capture->page_no,
            ],
            'issues' => $issues,
            'provenance' => [
                'asset' => $aligned,
                'page_asset' => $aligned,
                'crop_source' => $source,
                'geometry_manifest_hash' => $manifestHash,
                'evidence_version' => (string) ($capture->evidence_version ?: 'v1'),
                'page_no' => (int) $capture->page_no,
                'geometry' => [
                    'source' => 'pinned_native_manifest',
                    'manifest_hash' => $manifestHash,
                    'status' => $status,
                ],
                'assets' => [
                    'aligned_hash' => $aligned['sha256'] ?? null,
                    'crop_hash' => $crop['sha256'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function withReview(array $row, ?OmrItemReviewRevision $revision, bool $verification): array
    {
        $machineHash = $this->hashOrNull($row['_machine_evidence_hash'] ?? ($row['machine']['evidence_hash'] ?? null));
        $review = null;
        $reviewIsStale = false;
        $reviewIsValid = false;
        if ($revision !== null) {
            $reviewHash = $this->hashOrNull($revision->reviewed_machine_evidence_hash);
            $reviewIsStale = $machineHash === null
                || $reviewHash === null
                || ! hash_equals($machineHash, $reviewHash);
            $decision = $this->reviewDecisionValue($revision);
            $reviewIsValid = ! $reviewIsStale && $decision !== 'unresolved';
            $review = [
                'revision' => (int) $revision->revision,
                'decision' => $decision,
                'selected_option' => $revision->selected_option,
                'reason_code' => $revision->reason_code,
                'reviewer' => $revision->relationLoaded('reviewedBy') && $revision->reviewedBy !== null
                    ? [
                        'id' => (int) $revision->reviewedBy->getKey(),
                        'display_name' => (string) $revision->reviewedBy->name,
                    ]
                    : null,
                'reviewed_at' => $revision->created_at?->toIso8601String(),
                'evidence_hash' => $reviewHash,
            ];
            if ($reviewIsStale) {
                $review['stale'] = true;
            }
        }

        $effective = $verification
            ? ['state' => 'not_applicable', 'selected_option' => null, 'source' => 'none']
            : $this->effectiveForGrading($row['machine'], $review, $reviewIsValid);
        $issues = $row['issues'] ?? [];
        if ($reviewIsStale) {
            $issues[] = 'stale_review';
        }
        if (! $reviewIsValid && $review !== null && ($review['decision'] ?? null) === 'unresolved') {
            $issues[] = 'review_unresolved';
        }
        $row['review'] = $review;
        $row['effective'] = $effective;
        $row['issues'] = array_values(array_unique(array_filter($issues, static fn (mixed $issue): bool => is_string($issue) && $issue !== '')));
        $row['provenance']['review'] = [
            'source' => 'omr_item_review_revisions',
            'revision' => $review['revision'] ?? null,
            'status' => $review === null ? 'none' : ($reviewIsStale ? 'stale' : ($reviewIsValid ? 'current' : 'unresolved')),
        ];
        unset(
            $row['_context'],
            $row['_item_key'],
            $row['_machine_state'],
            $row['_machine_selected_option'],
            $row['_machine_evidence_hash'],
        );

        return $row;
    }

    /** @param array<string,mixed> $machine @param array<string,mixed>|null $review */
    private function effectiveForGrading(array $machine, ?array $review, bool $reviewIsValid): array
    {
        if ($reviewIsValid && $review !== null) {
            return match ($review['decision'] ?? null) {
                'confirm_machine', 'set_option' => [
                    'state' => 'selected',
                    'selected_option' => $review['selected_option'] ?? null,
                    'source' => 'review',
                ],
                'blank' => ['state' => 'blank', 'selected_option' => null, 'source' => 'review'],
                default => ['state' => 'unresolved', 'selected_option' => null, 'source' => 'none'],
            };
        }
        if (($machine['state'] ?? null) === 'marked' && ($machine['selected_option'] ?? null) !== null) {
            return [
                'state' => 'selected',
                'selected_option' => $machine['selected_option'],
                'source' => 'machine',
            ];
        }
        if (($machine['state'] ?? null) === 'blank') {
            return ['state' => 'blank', 'selected_option' => null, 'source' => 'machine'];
        }

        return ['state' => 'unresolved', 'selected_option' => null, 'source' => 'none'];
    }

    private function reviewDecisionValue(OmrItemReviewRevision $revision): ?string
    {
        $decision = $revision->decision;

        return is_object($decision) && isset($decision->value)
            ? (string) $decision->value
            : ($decision === null ? null : (string) $decision);
    }

    /** @param array<string,mixed> $row */
    private function isReviewed(array $row): bool
    {
        return is_array($row['review'] ?? null)
            && ! ($row['review']['stale'] ?? false)
            && in_array((string) ($row['review']['decision'] ?? ''), ['confirm_machine', 'set_option', 'blank'], true);
    }

    /** @param array<string,mixed> $row */
    private function needsReview(array $row): bool
    {
        if (is_array($row['review'] ?? null)) {
            if (($row['review']['stale'] ?? false) || ($row['review']['decision'] ?? null) === 'unresolved') {
                return true;
            }
            if ($this->isReviewed($row)) {
                return false;
            }
        }

        return in_array((string) ($row['machine']['state'] ?? ''), ['ambiguous', 'unreadable'], true)
            || $this->isMismatch($row);
    }

    /** @param array<string,mixed> $row */
    private function matchesFilter(array $row, string $filter): bool
    {
        return match ($filter) {
            OmrEvidencePage::FILTER_ALL => true,
            OmrEvidencePage::FILTER_MISMATCH => $this->isMismatch($row),
            OmrEvidencePage::FILTER_NEEDS_REVIEW => $this->needsReview($row),
            OmrEvidencePage::FILTER_REVIEWED => $this->isReviewed($row),
            default => false,
        };
    }

    /** @return array{items:array<string,array<string,mixed>>,manifest_hash:string|null,status:string} */
    private function gradingGeometry(OmrCaptureSheet $sheet): array
    {
        $template = $sheet->batch?->templateVersion;
        $booklet = $sheet->batch?->bookletVersion;
        $manifestHash = $this->hashOrNull($template?->review_manifest_hash)
            ?? $this->hashOrNull($template?->native_bundle_hash);
        if ($template === null || $booklet === null) {
            return ['items' => [], 'manifest_hash' => $manifestHash, 'status' => 'unavailable'];
        }

        try {
            $set = null;
            $kitContext = null;
            $materialSnapshot = null;
            if ($sheet->admission_attempt_id !== null || $sheet->detected_applicant_number !== null) {
                $resolution = $this->captureResolver->resolveSheet($sheet);
                if (! $resolution->isResolved()
                    || $resolution->materialSet === null
                    || $resolution->snapshot === null
                    || $sheet->booklet_set_id === null
                    || (int) $resolution->materialSet->booklet_set_id !== (int) $sheet->booklet_set_id) {
                    throw new RuntimeException('The capture has no exact frozen material and Set resolution.');
                }
                $set = $resolution->materialSet->bookletSet()->first();
                $materialSnapshot = $resolution->snapshot;
                if ($set === null || (int) $set->id !== (int) $sheet->booklet_set_id) {
                    throw new RuntimeException('The capture Set is unavailable from its frozen material.');
                }
            } else {
                $sheet->loadMissing('batch.gradingSession.examSession.currentMaterialSnapshot');
                $materialSnapshot = $sheet->batch?->gradingSession?->examSession?->currentMaterialSnapshot;
                if ($materialSnapshot?->test_kit_version_id !== null) {
                    throw new RuntimeException('A Kit-bound capture requires exact examinee and administration resolution.');
                }
                if ($sheet->booklet_set_id !== null) {
                    $set = OmrBookletSet::query()->find((int) $sheet->booklet_set_id);
                    if ($set === null) {
                        throw new RuntimeException('The captured Booklet Set is unavailable.');
                    }
                }
            }

            if ($materialSnapshot?->test_kit_version_id !== null) {
                $kitVersion = TestKitVersion::query()->findOrFail((int) $materialSnapshot->test_kit_version_id);
                $kitContext = $this->kitCompositionContext->resolveVersion($kitVersion);
                if ((int) $kitContext['test_kit_version_id'] !== (int) $materialSnapshot->test_kit_version_id
                    || (string) $kitContext['manifest_sha256'] !== (string) $materialSnapshot->manifest_sha256
                    || (int) $kitContext['template_version_id'] !== (int) $materialSnapshot->template_version_id
                    || (int) $kitContext['booklet_version_id'] !== (int) $materialSnapshot->booklet_version_id
                    || (int) $kitContext['test_instrument_version_id'] !== (int) $materialSnapshot->test_instrument_version_id
                    || (int) ($kitContext['version']->selected_acceptance_receipt_id ?? 0) !== (int) $materialSnapshot->acceptance_receipt_id
                    || $set === null
                    || (int) $kitContext['booklet_set_id'] !== (int) $set->id
                    || (int) $materialSnapshot->template_version_id !== (int) $template->id
                    || (int) $materialSnapshot->booklet_version_id !== (int) $booklet->id) {
                    throw new RuntimeException('The exact frozen Kit composition conflicts with the capture components.');
                }
            }

            $projected = $this->geometryQuery->itemsFor($template, $booklet, $set, $kitContext);
        } catch (Throwable) {
            return ['items' => [], 'manifest_hash' => $manifestHash, 'status' => 'stale'];
        }
        $items = [];
        foreach ($projected as $geometry) {
            $key = $this->itemKey(
                $geometry['area_code'] ?? null,
                $geometry['question_no'] ?? null,
                $geometry['position'] ?? null,
            );
            if ($key !== null) {
                $items[$key] = $geometry;
            }
        }

        return [
            'items' => $items,
            'manifest_hash' => $manifestHash,
            'status' => $items === [] || $manifestHash === null ? 'unavailable' : 'available',
        ];
    }

    /** @param array<string,mixed> $proposalItem @param array<string,mixed> $geometry */
    private function gradingItemRow(
        OmrCaptureSheet $sheet,
        object $session,
        array $proposalItem,
        int $index,
        array $geometry,
        array &$areaPositions,
        bool $blind = false,
    ): ?array {
        $areaKey = $this->scalarString($proposalItem['area_code'] ?? ($proposalItem['area_key'] ?? null));
        $questionNo = is_numeric($proposalItem['question_no'] ?? null)
            ? (int) $proposalItem['question_no']
            : null;
        $itemKey = $this->itemKey($areaKey, $questionNo, $proposalItem['position'] ?? ($index + 1));
        if ($itemKey === null) {
            return null;
        }
        $areaPositionKey = strtolower((string) ($areaKey ?? ''));
        if ($areaPositionKey !== '' && ! isset($areaPositions[$areaPositionKey])) {
            $areaPositions[$areaPositionKey] = count($areaPositions) + 1;
        }
        $projectedGeometry = $geometry['items'][$itemKey] ?? null;
        $options = $projectedGeometry === null
            ? []
            : $this->geometryOptions($projectedGeometry['options'] ?? []);
        $optionSet = array_values(array_unique(array_map(
            fn (array $option): string => (string) $option['code'],
            $options,
        )));
        $machine = $this->gradingMachine($proposalItem, $optionSet, $sheet, $itemKey);
        $pageNo = $projectedGeometry !== null && is_numeric($projectedGeometry['page_no'] ?? null)
            ? (int) $projectedGeometry['page_no']
            : (is_numeric($proposalItem['page_no'] ?? null) ? (int) $proposalItem['page_no'] : 1);
        $expected = $blind
            ? null
            : ($projectedGeometry !== null && isset($projectedGeometry['correct_option_code'])
                ? $this->scalarString($projectedGeometry['correct_option_code'])
                : $this->scalarString($proposalItem['correct_option_code'] ?? null));
        $geometryProjection = $this->gradingItemGeometry($sheet, $session, $projectedGeometry, $geometry, $pageNo);
        $issues = array_values(array_unique([
            ...$machine['issue_codes'],
            ...$geometryProjection['issues'],
        ]));

        return [
            'id' => (int) ($proposalItem['position'] ?? $index + 1),
            'item_id' => $itemKey,
            'group_type' => OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA,
            'group' => OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA,
            'area_key' => $areaKey,
            'area_position' => $areaKey !== null ? $areaPositions[$areaPositionKey] : null,
            'question_no' => $questionNo,
            'page_no' => $pageNo,
            'machine' => $machine,
            'expected' => ['option' => $expected],
            'review' => null,
            'effective' => $this->effectiveForGrading($machine, null, false),
            'geometry' => $geometryProjection['geometry'],
            'issues' => $issues,
            'provenance' => $geometryProjection['provenance'] + [
                'machine' => [
                    'source' => 'capture_sheet.proposal_json',
                    'version' => 'scanner-'.(int) ($sheet->version ?? 0),
                    'hash' => $machine['evidence_hash'],
                    'legacy_evidence_limited' => $machine['legacy_evidence_limited'],
                ],
                'expected' => [
                    'source' => $blind ? 'blind_review_redacted' : ($expected === null ? 'unavailable' : ($projectedGeometry !== null && isset($projectedGeometry['correct_option_code']) ? 'pinned_booklet' : 'proposal_json')),
                    'available' => ! $blind && $expected !== null,
                ],
            ],
            '_item_key' => $itemKey,
            '_machine_state' => $machine['state'],
            '_machine_selected_option' => $machine['selected_option'],
            '_machine_evidence_hash' => $machine['evidence_hash'],
        ];
    }

    /** @param list<array<string,mixed>> $raw */
    private function geometryOptions(array $raw): array
    {
        $options = [];
        foreach ($raw as $option) {
            if (! is_array($option)) {
                continue;
            }
            $code = $this->scalarString($option['code'] ?? null);
            $region = $this->normalizedRegion($option['region'] ?? null);
            if ($code !== null && $region !== null) {
                $options[] = ['code' => $code, 'region' => $region];
            }
        }

        return $options;
    }

    /** @param array<string,mixed>|null $projectedGeometry @param array<string,mixed> $geometry */
    private function gradingItemGeometry(
        OmrCaptureSheet $sheet,
        object $session,
        ?array $projectedGeometry,
        array $geometry,
        int $pageNo,
    ): array {
        $itemRect = $projectedGeometry === null
            ? null
            : $this->normalizedRegion($projectedGeometry['region'] ?? null);
        $options = $projectedGeometry === null
            ? []
            : $this->geometryOptions($projectedGeometry['options'] ?? []);
        $aligned = $this->gradingPageAsset($session, $sheet, $pageNo);
        $issues = [];
        if ($projectedGeometry === null) {
            $issues[] = 'geometry_projection_unavailable';
        }
        if ($itemRect === null) {
            $issues[] = 'missing_item_geometry';
        }
        if ($options === []) {
            $issues[] = 'missing_option_geometry';
        }
        if (($geometry['manifest_hash'] ?? null) === null) {
            $issues[] = 'missing_geometry_hash';
        }
        if ($aligned === null) {
            $issues[] = 'missing_aligned_asset';
        } elseif (($aligned['width'] ?? null) === null || ($aligned['height'] ?? null) === null) {
            $issues[] = 'missing_asset_dimensions';
        } elseif (($aligned['sha256'] ?? null) === null) {
            $issues[] = 'missing_asset_hash';
        }
        if (($geometry['status'] ?? null) === 'stale') {
            $issues[] = 'geometry_stale';
        }
        $status = ($geometry['status'] ?? null) === 'stale' ? 'stale' : ($issues === [] && ($geometry['status'] ?? null) === 'available' ? 'available' : 'unavailable');
        $source = $aligned === null ? null : [
            'kind' => 'aligned_page',
            'type' => 'aligned_page',
            'source_rect' => $itemRect,
            'rect' => $itemRect,
            'aligned_rect' => $itemRect,
            'transform' => null,
        ];

        return [
            'geometry' => [
                'item_rect' => $itemRect,
                'crop_rect' => $itemRect,
                'options' => $options,
                'aligned_asset' => $aligned,
                'crop_asset' => null,
                'crop_source' => $source,
                'geometry_manifest_hash' => $geometry['manifest_hash'] ?? null,
                'evidence_version' => 'scanner-'.(int) ($sheet->version ?? 0),
                'page_no' => $pageNo,
            ],
            'issues' => array_values(array_unique($issues)),
            'provenance' => [
                'asset' => $aligned,
                'page_asset' => $aligned,
                'crop_source' => $source,
                'geometry_manifest_hash' => $geometry['manifest_hash'] ?? null,
                'evidence_version' => 'scanner-'.(int) ($sheet->version ?? 0),
                'page_no' => $pageNo,
                'geometry' => [
                    'source' => 'pinned_native_manifest',
                    'manifest_hash' => $geometry['manifest_hash'] ?? null,
                    'status' => $status,
                ],
                'assets' => [
                    'aligned_hash' => $aligned['sha256'] ?? null,
                    'crop_hash' => null,
                ],
            ],
        ];
    }

    /** @param list<string> $optionSet @return array<string,mixed> */
    private function gradingMachine(array $item, array $optionSet, OmrCaptureSheet $sheet, string $itemKey): array
    {
        $machine = is_array($item['machine'] ?? null) ? $item['machine'] : [];
        $selectedRaw = $machine['selected_option'] ?? ($item['selected_option'] ?? ($item['detected_answer'] ?? null));
        $selected = $this->scalarString($selectedRaw);
        $rawState = strtolower(trim((string) ($machine['state'] ?? ($item['state'] ?? ''))));
        $state = match ($rawState) {
            'marked', 'match', 'mismatch' => 'marked',
            'blank', 'missing' => 'blank',
            'ambiguous', 'multiple' => 'ambiguous',
            'unreadable' => 'unreadable',
            default => $selected === null ? 'unreadable' : 'marked',
        };
        if ($state !== 'marked') {
            $selected = null;
        }
        $rawObserved = $machine['observed_options'] ?? ($item['observed_options'] ?? []);
        $observed = [];
        foreach ((array) $rawObserved as $option) {
            $code = $this->scalarString(is_array($option) ? ($option['code'] ?? null) : $option);
            if ($code !== null && in_array($code, $optionSet, true) && ! in_array($code, $observed, true)) {
                $observed[] = $code;
            }
        }
        $hashObserved = $observed;
        if ($hashObserved === [] && $selected !== null) {
            $hashObserved = [$selected];
        }
        $rawObservations = $machine['option_observations'] ?? ($item['option_observations'] ?? []);
        $observationByCode = [];
        foreach ((array) $rawObservations as $observation) {
            if (! is_array($observation)) {
                continue;
            }
            $code = $this->scalarString($observation['code'] ?? null);
            if ($code !== null) {
                $observationByCode[$code] = $observation;
            }
        }
        $means = is_array($item['means'] ?? null) ? $item['means'] : [];
        $observations = [];
        foreach ($optionSet as $code) {
            $observation = $observationByCode[$code] ?? [];
            $mean = $observation['mean'] ?? ($means[$code] ?? null);
            $observations[] = [
                'code' => $code,
                'mean' => is_numeric($mean) ? (float) $mean : null,
                'classification' => $this->scalarString($observation['classification'] ?? null),
            ];
        }
        $issues = [];
        $hasCandidates = array_key_exists('observed_options', $machine) || array_key_exists('observed_options', $item);
        if (! $hasCandidates) {
            $issues[] = 'legacy_evidence_limited';
        }
        $rawIssues = $machine['issue_codes'] ?? ($item['issue_codes'] ?? []);
        foreach ((array) $rawIssues as $issue) {
            if (is_scalar($issue) && trim((string) $issue) !== '') {
                $issues[] = (string) $issue;
            }
        }
        $confidence = $machine['confidence'] ?? ($item['confidence'] ?? null);
        $confidence = is_numeric($confidence) && is_finite((float) $confidence) ? (float) $confidence : null;
        $providedHash = $this->hashOrNull($item['evidence_hash'] ?? ($machine['evidence_hash'] ?? null));
        $hashMachine = [
            'state' => $state,
            'selected_option' => $selected,
            'observed_options' => $hashObserved,
            'option_observations' => $observations,
            'confidence' => $confidence,
            'issue_codes' => array_values(array_unique($issues)),
        ];
        $evidenceHash = $providedHash ?? hash('sha256', json_encode([
            'item_key' => $itemKey,
            'machine' => $hashMachine,
            'option_set' => $optionSet,
            'sheet' => [
                'sheet_id' => (int) $sheet->getKey(),
                'version' => 0,
                'aligned_hash' => (string) ($sheet->aligned_hash ?? ''),
                'proposal_input_hash' => (string) (($sheet->proposal_json['input_hash'] ?? '')),
                'proposal_native_bundle_hash' => (string) (($sheet->proposal_json['native_bundle_hash'] ?? '')),
                'proposal_manifest_hash' => (string) (($sheet->proposal_json['manifest_hash'] ?? '')),
                'proposal_config_hash' => (string) (($sheet->proposal_json['config_hash'] ?? '')),
                'envelope' => $sheet->proposal_json['envelope'] ?? null,
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return [
            'state' => $state,
            'selected_option' => $selected,
            'observed_options' => $observed,
            'option_observations' => $observations,
            'confidence' => $confidence,
            'issue_codes' => array_values(array_unique($issues)),
            'evidence_hash' => $evidenceHash,
            'legacy_evidence_limited' => ! $hasCandidates,
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function filterRowsByAreaAndPage(array $rows, string $area, ?int $pageNo): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($area, $pageNo): bool {
            if ($area !== 'all') {
                $matchesArea = ($area === 'identity' || $area === 'calibration')
                    ? ($row['group_type'] ?? null) === $area
                    : ($row['group_type'] ?? null) === OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA
                        && strcasecmp((string) ($row['area_key'] ?? ''), $area) === 0;
                if (! $matchesArea) {
                    return false;
                }
            }

            return $pageNo === null || (int) ($row['page_no'] ?? 0) === $pageNo;
        }));
    }

    /** @param list<string> $itemKeys @return array<string,OmrItemReviewRevision> */
    private function latestGradingReviews(int $sheetId, array $itemKeys): array
    {
        if ($itemKeys === []) {
            return [];
        }

        return OmrItemReviewRevision::query()
            ->with('reviewedBy:id,name')
            ->where('capture_sheet_id', $sheetId)
            ->latestRevision()
            ->orderByDesc('revision')
            ->get()
            ->keyBy(static fn (OmrItemReviewRevision $revision): string => (string) $revision->item_key)
            ->all();
    }

    /** @param list<int> $evidenceIds @return array<int,OmrItemReviewRevision> */
    private function latestVerificationReviews(array $evidenceIds): array
    {
        if ($evidenceIds === []) {
            return [];
        }

        return OmrItemReviewRevision::query()
            ->with('reviewedBy:id,name')
            ->whereIn('verification_item_evidence_id', array_values(array_unique(array_map('intval', $evidenceIds))))
            ->latestRevision()
            ->orderByDesc('revision')
            ->get()
            ->keyBy(static fn (OmrItemReviewRevision $revision): int => (int) $revision->verification_item_evidence_id)
            ->all();
    }

    /** @return array<string,string|null> */
    private function gradingAssetUrls(object $session, OmrCaptureSheet $sheet, int $pageNo): array
    {
        $asset = $this->gradingPageAsset($session, $sheet, $pageNo);

        return [
            'input' => null,
            'aligned' => $asset['url'] ?? null,
            'overlay' => null,
        ];
    }

    /** @return array<string,mixed>|null */
    private function gradingPageAsset(object $session, OmrCaptureSheet $sheet, int $pageNo): ?array
    {
        $page = $sheet->relationLoaded('pages')
            ? $sheet->pages->firstWhere('page_no', $pageNo)
            : null;
        $path = $page?->aligned_asset_path ?: ($page?->asset_path ?: ($sheet->aligned_asset_path ?: $sheet->asset_path));
        $hmac = $page?->aligned_asset_hmac ?: ($page?->asset_hmac ?: ($sheet->aligned_asset_hmac ?: $sheet->asset_hmac));
        if (! is_string($path) || trim($path) === '' || ! is_string($hmac) || trim($hmac) === '') {
            return null;
        }
        $hashValue = $this->hashOrNull($page?->aligned_hash ?: ($sheet->aligned_hash ?? null));
        $width = $page?->aligned_width ?: ($sheet->aligned_width ?? null);
        $height = $page?->aligned_height ?: ($sheet->aligned_height ?? null);
        try {
            $url = route('scoring.sessions.omr.sheets.pages.image', [
                'examSession' => $session->exam_session_id ?? $session,
                'sheet' => $sheet,
                'page' => $pageNo,
                ...($hashValue === null ? [] : ['sha256' => $hashValue]),
            ]);
        } catch (Throwable) {
            $url = null;
        }

        return [
            'url' => $url,
            'width' => is_numeric($width) && (int) $width > 0 ? (int) $width : null,
            'height' => is_numeric($height) && (int) $height > 0 ? (int) $height : null,
            'sha256' => $hashValue,
            'page_no' => $pageNo,
            'kind' => 'aligned_page',
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hashableItem(array $row): array
    {
        return [
            'item_id' => $row['item_id'] ?? null,
            'page_no' => $row['page_no'] ?? null,
            'machine' => $row['machine'] ?? null,
            'expected' => $row['expected'] ?? null,
            'geometry' => $row['geometry'] ?? null,
            'issues' => $row['issues'] ?? [],
            'review' => $row['review'] ?? null,
        ];
    }

    private function itemKey(mixed $area, mixed $questionNo, mixed $position): ?string
    {
        $areaCode = trim((string) $area);
        $question = is_numeric($questionNo) ? (int) $questionNo : 0;
        if ($areaCode !== '' && $question > 0) {
            return strtoupper($areaCode).'_Q_'.$question;
        }
        $positionValue = is_numeric($position) ? (int) $position : 0;

        return $positionValue > 0 ? 'POSITION_'.$positionValue : null;
    }

    /** @return array<string,mixed> */
    private function pinnedReviewManifest(OmrTemplateVerificationRun $run): array
    {
        $versionManifest = $run->templateVersion?->review_manifest_json;
        if (is_array($versionManifest)) {
            return $versionManifest;
        }

        $authoring = is_array($run->draft?->authoring_json)
            ? $run->draft->authoring_json
            : (is_array($run->templateVersion?->authoring_manifest) ? $run->templateVersion->authoring_manifest : []);
        $candidateManifest = $authoring['canonical']['artifact_set']['review_manifest']
            ?? $authoring['artifact_set']['review_manifest']
            ?? null;

        return is_array($candidateManifest) ? $candidateManifest : [];
    }

    /** @return list<array{code:string,region:array{x:float,y:float,w:float,h:float}}> */
    private function manifestOptions(OmrTemplateVerificationRun $run, string $itemId, int $pageNo): array
    {
        $options = [];
        foreach ((array) ($this->pinnedReviewManifest($run)['fields'] ?? []) as $field) {
            if (! is_array($field)
                || (string) ($field['label'] ?? '') !== $itemId
                || (int) ($field['page'] ?? 0) !== $pageNo) {
                continue;
            }
            $code = $this->scalarString($field['option'] ?? null);
            $region = $this->normalizedRegion($field['region'] ?? null);
            if ($code !== null && $region !== null) {
                $options[] = ['code' => $code, 'region' => $region];
            }
        }

        return $options;
    }

    /**
     * @param  list<array{x:float,y:float,w:float,h:float}>  $regions
     * @return array{x:float,y:float,w:float,h:float}
     */
    private function unionRegions(array $regions): array
    {
        $left = min(array_column($regions, 'x'));
        $top = min(array_column($regions, 'y'));
        $right = max(array_map(static fn (array $region): float => $region['x'] + $region['w'], $regions));
        $bottom = max(array_map(static fn (array $region): float => $region['y'] + $region['h'], $regions));

        return ['x' => $left, 'y' => $top, 'w' => $right - $left, 'h' => $bottom - $top];
    }

    /** @return list<array<string,mixed>> */
    private function documentFields(
        OmrTemplateVerificationRun $run,
        int $pageNo,
        ?array $identityWriteIn = null,
        ?OmrTemplateVerificationCapture $capture = null,
    ): array {
        $authoring = is_array($run->draft?->authoring_json)
            ? $run->draft->authoring_json
            : (is_array($run->templateVersion?->authoring_manifest) ? $run->templateVersion->authoring_manifest : []);
        $components = $authoring['studio']['components'] ?? $authoring['components'] ?? [];
        $rawFields = $authoring['studio']['document_fields']
            ?? $authoring['mapping']['input']['document_fields']
            ?? $authoring['canonical']['mapping']['document_fields']
            ?? $run->templateVersion?->authoring_manifest['studio']['document_fields']
            ?? $run->templateVersion?->authoring_manifest['mapping']['input']['document_fields']
            ?? $run->templateVersion?->authoring_manifest['canonical']['mapping']['document_fields']
            ?? $run->templateVersion?->authoring_manifest['document_fields']
            ?? $run->templateVersion?->review_manifest_json['document_fields']
            ?? [];
        $settings = $authoring['canonical']['settings'] ?? $authoring['settings'] ?? [];
        $paper = $authoring['paper'] ?? $run->templateVersion?->authoring_manifest['paper'] ?? [];
        $paperWidth = is_numeric($settings['paper_width_mm'] ?? $paper['width_mm'] ?? null)
            ? (float) ($settings['paper_width_mm'] ?? $paper['width_mm'])
            : 210.0;
        $paperHeight = is_numeric($settings['paper_height_mm'] ?? $paper['height_mm'] ?? null)
            ? (float) ($settings['paper_height_mm'] ?? $paper['height_mm'])
            : 297.0;
        $fiducialAnchored = $run->templateVersion?->registration_mode === OmrRegistrationMode::FiducialAnchored;
        $markerInsets = (array) (
            $authoring['marker']['insets_mm']
            ?? $settings['marker_inset_mm']
            ?? $settings['marker_insets']
            ?? $run->templateVersion?->authoring_manifest['marker_insets_mm']
            ?? ['left' => 12.0, 'top' => 12.0, 'right' => 12.0, 'bottom' => 14.0]
        );
        $frameLeft = $fiducialAnchored ? (float) ($markerInsets['left'] ?? 12.0) : 0.0;
        $frameTop = $fiducialAnchored ? (float) ($markerInsets['top'] ?? 12.0) : 0.0;
        $frameWidth = $fiducialAnchored
            ? $paperWidth - $frameLeft - (float) ($markerInsets['right'] ?? 12.0)
            : $paperWidth;
        $frameHeight = $fiducialAnchored
            ? $paperHeight - $frameTop - (float) ($markerInsets['bottom'] ?? 14.0)
            : $paperHeight;
        $labels = [
            'candidate_name' => 'Applicant name',
            'birthdate' => 'Birthdate',
            'room_seat' => 'Room / seat',
            'custom_input' => 'Applicant field',
            'signature' => 'Signature',
        ];
        $fields = [];
        $urls = $capture !== null && (int) $capture->page_no === $pageNo
            ? $this->captureAssetUrls($run, $capture)
            : [];
        $pageAlignedUrl = $urls['aligned'] ?? null;

        foreach ((array) $rawFields as $field) {
            if (! is_array($field) || (int) ($field['page_no'] ?? 1) !== $pageNo) {
                continue;
            }
            $type = strtolower((string) ($field['type'] ?? 'custom_input'));
            $region = $this->normalizedRegion($field['region'] ?? null);
            if ($region === null) {
                $coordinates = is_array($field['region'] ?? null) ? $field['region'] : $field;
                $x = $field['x_mm'] ?? $field['posX'] ?? $coordinates['x'] ?? null;
                $y = $field['y_mm'] ?? $field['posY'] ?? $coordinates['y'] ?? null;
                $width = $field['w_mm'] ?? $field['width_mm'] ?? $coordinates['w'] ?? null;
                $height = $field['h_mm'] ?? $field['height_mm'] ?? $coordinates['h'] ?? null;
                if (is_numeric($x) && is_numeric($y) && is_numeric($width) && is_numeric($height) && $frameWidth > 0 && $frameHeight > 0) {
                    $isMillimeters = isset($field['x_mm'], $field['y_mm'], $field['w_mm'], $field['h_mm'])
                        || (abs((float) $x) > 1 || abs((float) $y) > 1 || abs((float) $width) > 1 || abs((float) $height) > 1);
                    $region = $this->normalizedRegion($isMillimeters
                        ? [
                            'x' => max(0.0, min(1.0, ((float) $x - $frameLeft) / $frameWidth)),
                            'y' => max(0.0, min(1.0, ((float) $y - $frameTop) / $frameHeight)),
                            'w' => max(0.0, min(1.0, (float) $width / $frameWidth)),
                            'h' => max(0.0, min(1.0, (float) $height / $frameHeight)),
                        ]
                        : ['x' => $x, 'y' => $y, 'w' => $width, 'h' => $height]);
                }
            }
            if ($region === null) {
                continue;
            }
            $fields[] = [
                'id' => (string) ($field['id'] ?? $type.'-'.$pageNo),
                'type' => $type,
                'label' => (string) ($field['label'] ?? $labels[$type] ?? 'Document Field'),
                'page_no' => $pageNo,
                'region' => $region,
                'transcription' => false,
                'aligned_url' => $pageAlignedUrl,
            ];
        }

        foreach ((array) $components as $component) {
            if (! is_array($component)) {
                continue;
            }
            $type = strtolower((string) ($component['type'] ?? ''));
            $componentPage = (int) ($component['page_no'] ?? $component['page'] ?? 1);
            if (! isset($labels[$type]) || $componentPage !== $pageNo) {
                continue;
            }
            $region = $this->normalizedRegion($component['region'] ?? null);
            if ($region === null) {
                $x = $component['posX'] ?? $component['x_mm'] ?? null;
                $y = $component['posY'] ?? $component['y_mm'] ?? null;
                $width = $component['w_mm'] ?? $component['width_mm'] ?? null;
                $height = $component['h_mm'] ?? $component['height_mm'] ?? null;
                if (is_numeric($x) && is_numeric($y) && is_numeric($width) && is_numeric($height)) {
                    $region = $this->normalizedRegion([
                        'x' => (float) $x / $paperWidth,
                        'y' => (float) $y / $paperHeight,
                        'w' => (float) $width / $paperWidth,
                        'h' => (float) $height / $paperHeight,
                    ]);
                }
            }
            if ($region === null) {
                continue;
            }
            $label = trim((string) ($component['label'] ?? $component['title'] ?? ''));
            $fields[] = [
                'id' => (string) ($component['id'] ?? $type.'-'.$pageNo),
                'type' => $type,
                'label' => $label !== '' ? $label : $labels[$type],
                'page_no' => $pageNo,
                'region' => $region,
                'transcription' => false,
                'aligned_url' => $pageAlignedUrl,
            ];
        }

        if ($identityWriteIn !== null && $identityWriteIn['page_no'] === $pageNo) {
            $fields[] = [
                'id' => 'applicant_number_write_in-'.$pageNo,
                'type' => 'applicant_number_write_in',
                'label' => 'Applicant Number (Handwritten)',
                'page_no' => $pageNo,
                'region' => $identityWriteIn['region'],
            ];
        }

        return $fields;
    }

    /** @return array{x:float,y:float,w:float,h:float}|null */
    private function normalizedRegion(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }
        foreach (['x', 'y', 'w', 'h'] as $key) {
            if (! is_numeric($value[$key] ?? null) || ! is_finite((float) $value[$key])) {
                return null;
            }
        }
        $region = [
            'x' => (float) $value['x'],
            'y' => (float) $value['y'],
            'w' => (float) $value['w'],
            'h' => (float) $value['h'],
        ];
        if ($region['x'] < 0.0 || $region['y'] < 0.0 || $region['w'] <= 0.0 || $region['h'] <= 0.0
            || $region['x'] + $region['w'] > 1.0 || $region['y'] + $region['h'] > 1.0) {
            return null;
        }

        return $region;
    }

    private function hashOrNull(mixed $value): ?string
    {
        $hashValue = is_scalar($value) ? strtolower(trim((string) $value)) : '';

        return preg_match('/^[a-f0-9]{64}$/', $hashValue) === 1 ? $hashValue : null;
    }

    /** @return array<string,mixed>|null */
    private function assetProvenance(
        OmrTemplateVerificationRun $run,
        mixed $asset,
        mixed $hashValue,
        string $kind,
        int $pageNo,
    ): ?array {
        if ($asset === null || (int) ($asset->id ?? 0) < 1) {
            return null;
        }
        $metadata = is_array($asset->metadata ?? null) ? $asset->metadata : [];
        $width = $metadata['width'] ?? ($metadata['dimensions']['width'] ?? null);
        $height = $metadata['height'] ?? ($metadata['dimensions']['height'] ?? null);
        $resolvedHash = $this->hashOrNull($hashValue);

        return [
            'url' => $this->assetUrl($run, $asset, $resolvedHash),
            'width' => is_numeric($width) && (int) $width > 0 ? (int) $width : null,
            'height' => is_numeric($height) && (int) $height > 0 ? (int) $height : null,
            'sha256' => $resolvedHash,
            'page_no' => $pageNo,
            'kind' => $kind,
        ];
    }

    /** @param mixed $value @return list<array<string,mixed>> */
    private function safeOptions(mixed $value): array
    {
        $options = [];
        foreach ((array) $value as $option) {
            if (! is_array($option)) {
                continue;
            }
            $row = [];
            foreach ($option as $key => $candidate) {
                $key = strtolower((string) $key);
                if ($this->forbiddenKey($key) || ($this->urlKey($key) && ! $this->safeUrl($candidate))) {
                    continue;
                }
                if (is_scalar($candidate) || $candidate === null) {
                    $row[$key] = $candidate;
                } elseif (is_array($candidate)) {
                    $row[$key] = $this->safeValue($candidate);
                }
            }
            if (isset($row['code']) || isset($row['option'])) {
                $options[] = $row;
            }
        }

        return array_values($options);
    }

    /** @param mixed $value @return array<string,mixed>|list<mixed>|null */
    private function safeMeans(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $safe = $this->safeValue($value);

        return is_array($safe) ? $safe : null;
    }

    /** @param mixed $value @return array<string,mixed>|list<mixed>|null */
    private function safeArray(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }
        $safe = $this->safeValue($value);

        return is_array($safe) ? $safe : null;
    }

    private function safeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_scalar($value) || $value === null ? $value : null;
        }
        $safe = [];
        foreach ($value as $key => $nested) {
            $normalizedKey = strtolower((string) $key);
            if ($this->forbiddenKey($normalizedKey) || ($this->urlKey($normalizedKey) && ! $this->safeUrl($nested))) {
                continue;
            }
            $safe[$key] = is_array($nested) ? $this->safeValue($nested) : (is_scalar($nested) || $nested === null ? $nested : null);
        }

        return $safe;
    }

    private function forbiddenKey(string $key): bool
    {
        return $key === 'bytes'
            || $key === 'base64'
            || str_contains($key, 'base64')
            || str_contains($key, 'bytes')
            || str_starts_with($key, 'provider')
            || str_contains($key, 'applicant')
            || str_contains($key, 'score')
            || str_contains($key, 'answer_key')
            || str_contains($key, 'correct_')
            || str_contains($key, 'secret')
            || str_contains($key, 'token')
            || $key === 'authorization';
    }

    private function urlKey(string $key): bool
    {
        return $key === 'url' || $key === 'href' || $key === 'src'
            || str_ends_with($key, '_url') || str_ends_with($key, '_href') || str_ends_with($key, '_src');
    }

    private function safeUrl(mixed $url): bool
    {
        return is_string($url) && preg_match('#^(?:https?://|/)#i', $url) === 1 && ! str_starts_with($url, '//');
    }

    private function normalizeMethod(string $method): string
    {
        if (! in_array($method, OmrTemplateVerificationMethod::METHODS, true)) {
            throw new InvalidArgumentException('Evidence method must be pre_shaded or hand_shaded.');
        }

        return $method;
    }

    private function normalizeArea(string $area): string
    {
        $area = trim($area);
        if ($area === 'all' || $area === 'identity' || $area === 'calibration') {
            return $area;
        }
        if ($area === '' || preg_match('/^[A-Za-z0-9._-]{1,64}$/', $area) !== 1) {
            throw new InvalidArgumentException('Evidence area is invalid.');
        }

        return $area;
    }

    private function normalizeRequestedFilter(?string $filter): ?string
    {
        if ($filter === null || trim($filter) === '') {
            return null;
        }
        $filter = strtolower(trim($filter));
        if (! in_array($filter, OmrEvidencePage::FILTERS, true)) {
            throw new InvalidArgumentException('Evidence filter must be all, mismatch, needs_review, or reviewed.');
        }

        return $filter;
    }

    private function normalizePage(int $page): int
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Evidence page must be positive.');
        }

        return $page;
    }

    private function normalizePerPage(int $perPage): int
    {
        if ($perPage < 1 || $perPage > OmrEvidencePage::MAX_PER_PAGE) {
            throw new InvalidArgumentException('Evidence per-page must be between 1 and 50.');
        }

        return $perPage;
    }

    private function resourceScopeDigest(OmrTemplateVerificationRun $run, User $actor): string
    {
        $scope = [
            'actor_id' => (int) $actor->getAuthIdentifier(),
            'actor_scope' => $actor->actorScope()->toArray(),
            'run_target' => [
                'draft_id' => $run->draft_id,
                'template_version_id' => $run->template_version_id,
            ],
        ];

        return hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function assetUrl(OmrTemplateVerificationRun $run, mixed $asset, ?string $hashValue): ?string
    {
        if ($asset === null || (int) ($asset->id ?? 0) < 1) {
            return null;
        }
        try {
            $parameters = ['run' => $run->id, 'asset' => $asset->id];
            if (is_string($hashValue) && preg_match('/^[a-f0-9]{64}$/i', $hashValue) === 1) {
                $parameters['sha256'] = strtolower($hashValue);
            }

            return route('setup.omr.templates.canonical.verification_runs.assets.show', $parameters);
        } catch (Throwable) {
            return null;
        }
    }

    private function evidenceUrl(
        OmrTemplateVerificationRun $run,
        string $method,
        int $captureId,
        string $area,
        string $filter,
        int $page,
        int $perPage,
    ): ?string {
        try {
            return route('setup.omr.templates.canonical.verification_runs.evidence', [
                'run' => $run->id,
                'method' => $method,
                'capture_id' => $captureId,
                'area' => $area,
                'filter' => $filter,
                'page' => $page,
                'per_page' => $perPage,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function fromArray(array $value): OmrEvidencePage
    {
        return new OmrEvidencePage(
            method: (string) ($value['method'] ?? ''),
            captureId: (int) ($value['capture_id'] ?? 0),
            groupNavigation: is_array($value['groups'] ?? null) ? $value['groups'] : [],
            totals: [
                'total' => (int) ($value['total'] ?? 0),
                'filtered_total' => (int) ($value['filtered_total'] ?? $value['total'] ?? 0),
                'mismatch_total' => (int) ($value['mismatch_total'] ?? 0),
                'needs_review_total' => (int) ($value['needs_review_total'] ?? 0),
                'reviewed_total' => (int) ($value['reviewed_total'] ?? 0),
                'page_count' => (int) ($value['page_count'] ?? 1),
            ],
            page: (int) ($value['page'] ?? 1),
            perPage: (int) ($value['per_page'] ?? OmrEvidencePage::DEFAULT_PER_PAGE),
            filter: (string) ($value['filter'] ?? OmrEvidencePage::FILTER_ALL),
            items: is_array($value['items'] ?? null) ? $value['items'] : [],
            lazyAssetUrls: is_array($value['lazy_asset_urls'] ?? null) ? $value['lazy_asset_urls'] : [],
            area: (string) ($value['area'] ?? 'all'),
            areaPosition: isset($value['area_position']) ? (int) $value['area_position'] : null,
            total: (int) ($value['total'] ?? 0),
            mismatchTotal: (int) ($value['mismatch_total'] ?? 0),
            needsReviewTotal: (int) ($value['needs_review_total'] ?? 0),
            reviewedTotal: (int) ($value['reviewed_total'] ?? 0),
            filteredTotal: (int) ($value['filtered_total'] ?? $value['total'] ?? 0),
            pageCount: (int) ($value['page_count'] ?? 1),
            evidenceVersion: (string) ($value['evidence_version'] ?? 'v1'),
            evidenceHash: isset($value['evidence_hash']) ? (string) $value['evidence_hash'] : null,
            attemptNo: (int) ($value['attempt_no'] ?? 0),
            capturePageNo: (int) ($value['capture_page_no'] ?? 1),
            sheetMap: is_array($value['sheet_map'] ?? null) ? $value['sheet_map'] : [],
            reviewable: (bool) ($value['reviewable'] ?? false),
            parentVersion: (int) ($value['parent_version'] ?? 0),
            reviewReasons: is_array($value['review_reasons'] ?? null) ? $value['review_reasons'] : [],
            reviewNoteMax: (int) ($value['review_note_max'] ?? 2000),
            includeReviewMetadata: array_key_exists('reviewable', $value),
        );
    }

    /**
     * @return array{
     *     page_no:int,
     *     region:array{x:float,y:float,w:float,h:float},
     *     aligned_url:string|null,
     *     sha256:string|null,
     *     width:int|null,
     *     height:int|null
     * }|null
     */
    private function resolveIdentityWriteIn(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationCapture $capture,
    ): ?array {
        $authoring = is_array($run->draft?->authoring_json)
            ? $run->draft->authoring_json
            : (is_array($run->templateVersion?->authoring_manifest) ? $run->templateVersion->authoring_manifest : []);
        $template = $run->templateVersion;
        $bundle = is_array($template?->native_bundle_json) ? $template->native_bundle_json : [];
        $regions = $authoring['canonical']['artifact_set']['identity_regions']
            ?? $bundle['artifact_set']['identity_regions']
            ?? $template?->identity_regions_json
            ?? [];
        if (is_array($regions['applicant_number_write_in'] ?? null)) {
            $candidate = $regions['applicant_number_write_in'];
            $regions = [max(1, (int) ($candidate['page_no'] ?? 1)) => ['applicant_number_write_in' => $candidate]];
        }
        $mappingInput = $authoring['mapping']['input'] ?? [];
        $mappingInput = is_array($mappingInput) ? $mappingInput : [];
        $mappingInputIdentity = is_array($mappingInput['identity'] ?? null) ? $mappingInput['identity'] : [];
        if (is_array($mappingInputIdentity['regions'] ?? null)) {
            foreach ($mappingInputIdentity['regions'] as $regionPage => $candidate) {
                $regions[$regionPage] ??= [
                    'applicant_number_write_in' => is_array($candidate)
                        ? ($candidate['applicant_number_write_in'] ?? $candidate)
                        : null,
                ];
            }
        }
        $region = null;
        $pageNo = 1;

        if (is_array($regions['applicant_number_write_in'] ?? null)) {
            $candidate = $regions['applicant_number_write_in'];
            $region = $this->normalizedRegion($candidate);
            $pageNo = max(1, (int) ($candidate['page_no'] ?? 1));
        } else {
            foreach ($this->orderedRegionPages((array) $regions, (int) $capture->page_no) as $regionPage => $pageRegions) {
                if (! is_array($pageRegions) || ! is_array($pageRegions['applicant_number_write_in'] ?? null)) {
                    continue;
                }
                $region = $this->normalizedIdentityRegion($pageRegions['applicant_number_write_in'], [
                    'image_width' => $mappingInput['width_px'] ?? null,
                    'image_height' => $mappingInput['height_px'] ?? null,
                ]);
                if ($region !== null) {
                    $pageNo = is_numeric($regionPage) ? max(1, (int) $regionPage) : 1;
                    break;
                }
            }
        }

        if ($region === null) {
            $identity = $authoring['studio']['identity']
                ?? $authoring['canonical']['mapping']['identity']
                ?? $authoring['identity_method']
                ?? $authoring['identity']
                ?? [];
            $boxes = is_array($identity) ? ($identity['write_in_boxes'] ?? []) : [];
            $boxes = array_values((array) $boxes);
            usort($boxes, static function (mixed $left, mixed $right) use ($capture): int {
                $leftPage = is_array($left) ? max(1, (int) ($left['page_no'] ?? 1)) : 1;
                $rightPage = is_array($right) ? max(1, (int) ($right['page_no'] ?? 1)) : 1;
                if ($leftPage === (int) $capture->page_no) {
                    return $rightPage === (int) $capture->page_no ? 0 : -1;
                }
                if ($rightPage === (int) $capture->page_no) {
                    return 1;
                }

                return $leftPage <=> $rightPage;
            });
            $settings = $authoring['canonical']['settings'] ?? $authoring['settings'] ?? [];
            $paper = $authoring['paper'] ?? [];
            $paperWidth = is_numeric($settings['paper_width_mm'] ?? null)
                ? (float) $settings['paper_width_mm']
                : (is_numeric($paper['width_mm'] ?? null) ? (float) $paper['width_mm'] : 210.0);
            $paperHeight = is_numeric($settings['paper_height_mm'] ?? null)
                ? (float) $settings['paper_height_mm']
                : (is_numeric($paper['height_mm'] ?? null) ? (float) $paper['height_mm'] : 297.0);
            foreach ((array) $boxes as $box) {
                if (! is_array($box)) {
                    continue;
                }
                $pageNo = max(1, (int) ($box['page_no'] ?? 1));
                if (isset($box['x_mm'], $box['y_mm'], $box['w_mm'], $box['h_mm'])
                    && $paperWidth > 0 && $paperHeight > 0) {
                    $region = $this->normalizedRegion([
                        'x' => (float) $box['x_mm'] / $paperWidth,
                        'y' => (float) $box['y_mm'] / $paperHeight,
                        'w' => (float) $box['w_mm'] / $paperWidth,
                        'h' => (float) $box['h_mm'] / $paperHeight,
                    ]);
                } else {
                    $region = $this->normalizedRegion($box['region'] ?? $box);
                }
                if ($region !== null) {
                    break;
                }
            }
            $mappingInput = $authoring['mapping']['input'] ?? [];
            $mappingInput = is_array($mappingInput) ? $mappingInput : [];
            $mappingInputIdentity = is_array($mappingInput['identity'] ?? null) ? $mappingInput['identity'] : [];
            if ($region === null && is_array($mappingInputIdentity['regions'] ?? null)) {
                foreach ($this->orderedRegionPages($mappingInputIdentity['regions'], (int) $capture->page_no) as $regionPage => $candidate) {
                    $candidate = is_array($candidate)
                        ? ($candidate['applicant_number_write_in'] ?? $candidate)
                        : null;
                    $region = $this->normalizedIdentityRegion($candidate, [
                        'image_width' => $mappingInput['width_px'] ?? null,
                        'image_height' => $mappingInput['height_px'] ?? null,
                    ]);
                    if ($region !== null) {
                        $pageNo = is_numeric($regionPage) ? max(1, (int) $regionPage) : 1;
                        break;
                    }
                }
            }
            if ($region === null && is_array($identity['regions'] ?? null)) {
                foreach ($this->orderedRegionPages($identity['regions'], (int) $capture->page_no) as $regionPage => $candidate) {
                    $candidate = is_array($candidate)
                        ? ($candidate['applicant_number_write_in'] ?? $candidate)
                        : null;
                    $region = $this->normalizedIdentityRegion($candidate, $identity);
                    if ($region !== null) {
                        $pageNo = is_numeric($regionPage) ? max(1, (int) $regionPage) : 1;
                        break;
                    }
                }
            }
        }
        if ($region === null) {
            return null;
        }

        $pageNo = max(1, $pageNo);
        $selectedAttemptId = OmrTemplateVerificationMethodPage::query()
            ->where('method_id', $capture->method_id)
            ->where('page_no', $pageNo)
            ->value('selected_attempt_id');
        $pageCaptureQuery = OmrTemplateVerificationCapture::query()
            ->where('run_id', $run->id)
            ->where('method_id', $capture->method_id)
            ->where('page_no', $pageNo);
        $pageCapture = $selectedAttemptId !== null
            ? $pageCaptureQuery->whereKey((int) $selectedAttemptId)->first()
            : $pageCaptureQuery->orderByDesc('attempt_no')->orderByDesc('id')->first();
        $asset = $pageCapture?->alignedAsset;
        $urls = $pageCapture === null ? [] : $this->captureAssetUrls($run, $pageCapture);
        $metadata = is_array($asset?->metadata) ? $asset->metadata : [];
        $dimensions = is_array($metadata['dimensions'] ?? null) ? $metadata['dimensions'] : [];

        return [
            'page_no' => $pageNo,
            'region' => $region,
            'aligned_url' => $urls['aligned'] ?? null,
            'sha256' => $this->hashOrNull($pageCapture?->aligned_asset_hash ?: $asset?->sha256),
            'width' => is_numeric($metadata['width'] ?? $dimensions['width'] ?? null)
                ? (int) ($metadata['width'] ?? $dimensions['width'])
                : null,
            'height' => is_numeric($metadata['height'] ?? $dimensions['height'] ?? null)
                ? (int) ($metadata['height'] ?? $dimensions['height'])
                : null,
        ];
    }

    /** @param array<int|string,mixed> $regions @return array<int|string,mixed> */
    private function orderedRegionPages(array $regions, int $preferredPageNo): array
    {
        $pages = array_keys($regions);
        usort($pages, static function (int|string $left, int|string $right) use ($preferredPageNo): int {
            $leftPage = is_numeric($left) ? max(1, (int) $left) : 1;
            $rightPage = is_numeric($right) ? max(1, (int) $right) : 1;
            if ($leftPage === $preferredPageNo) {
                return $rightPage === $preferredPageNo ? 0 : -1;
            }
            if ($rightPage === $preferredPageNo) {
                return 1;
            }

            return $leftPage <=> $rightPage;
        });

        $ordered = [];
        foreach ($pages as $page) {
            $ordered[$page] = $regions[$page];
        }

        return $ordered;
    }

    /** @return array{x:float,y:float,w:float,h:float}|null */
    private function normalizedIdentityRegion(mixed $value, array $identity): ?array
    {
        $region = $this->normalizedRegion($value);
        if ($region !== null || ! is_array($value)) {
            return $region;
        }
        $width = $identity['image_width'] ?? $identity['canvas_width'] ?? $identity['width'] ?? null;
        $height = $identity['image_height'] ?? $identity['canvas_height'] ?? $identity['height'] ?? null;
        if (! is_numeric($width) || ! is_numeric($height) || (float) $width <= 0 || (float) $height <= 0) {
            return null;
        }

        return $this->normalizedRegion([
            'x' => (float) ($value['x'] ?? 0) / (float) $width,
            'y' => (float) ($value['y'] ?? 0) / (float) $height,
            'w' => (float) ($value['w'] ?? 0) / (float) $width,
            'h' => (float) ($value['h'] ?? 0) / (float) $height,
        ]);
    }

    /** @param array<string,mixed> $map @return array<string,mixed> */
    private function gradingSheetMap(OmrCaptureSheet $sheet, object $session, int $pageNo, array $map): array
    {
        $pageAsset = $this->gradingPageAsset($session, $sheet, $pageNo);
        $map['width'] = is_numeric($map['width'] ?? null) && (int) $map['width'] > 0
            ? (int) $map['width']
            : (int) ($pageAsset['width'] ?? 0);
        $map['height'] = is_numeric($map['height'] ?? null) && (int) $map['height'] > 0
            ? (int) $map['height']
            : (int) ($pageAsset['height'] ?? 0);
        if ($map['width'] <= 0) {
            $map['width'] = 2060;
        }
        if ($map['height'] <= 0) {
            $map['height'] = 3144;
        }
        $template = $sheet->batch?->templateVersion;
        $authoring = is_array($template?->authoring_manifest) ? $template->authoring_manifest : [];
        $mappingInput = $authoring['mapping']['input'] ?? [];
        $mappingInput = is_array($mappingInput) ? $mappingInput : [];
        $bundle = is_array($template?->native_bundle_json) ? $template->native_bundle_json : [];
        $regions = $bundle['artifact_set']['identity_regions']
            ?? $template?->identity_regions_json
            ?? [];
        $mappingInputIdentity = is_array($mappingInput['identity'] ?? null) ? $mappingInput['identity'] : [];
        if (is_array($regions['applicant_number_write_in'] ?? null)) {
            $candidate = $regions['applicant_number_write_in'];
            $regions = [max(1, (int) ($candidate['page_no'] ?? 1)) => ['applicant_number_write_in' => $candidate]];
        }
        if (is_array($mappingInputIdentity['regions'] ?? null)) {
            foreach ($mappingInputIdentity['regions'] as $regionPage => $candidate) {
                $regions[$regionPage] ??= [
                    'applicant_number_write_in' => is_array($candidate)
                        ? ($candidate['applicant_number_write_in'] ?? $candidate)
                        : null,
                ];
            }
        }
        $hasWriteInRegion = is_array($regions['applicant_number_write_in'] ?? null);
        foreach ((array) $regions as $pageRegions) {
            $hasWriteInRegion = $hasWriteInRegion
                || (is_array($pageRegions) && is_array($pageRegions['applicant_number_write_in'] ?? null));
        }
        if (! $hasWriteInRegion) {
            $identity = $authoring['studio']['identity']
                ?? $authoring['canonical']['mapping']['identity']
                ?? $authoring['identity_method']
                ?? $authoring['identity']
                ?? [];
            $mappingInput = $authoring['mapping']['input'] ?? [];
            $mappingInput = is_array($mappingInput) ? $mappingInput : [];
            $mappingInputIdentity = is_array($mappingInput['identity'] ?? null) ? $mappingInput['identity'] : [];
            $boxes = is_array($identity) ? ($identity['write_in_boxes'] ?? []) : [];
            $boxes = array_values((array) $boxes);
            usort($boxes, static function (mixed $left, mixed $right) use ($pageNo): int {
                $leftPage = is_array($left) ? max(1, (int) ($left['page_no'] ?? 1)) : 1;
                $rightPage = is_array($right) ? max(1, (int) ($right['page_no'] ?? 1)) : 1;
                if ($leftPage === $pageNo) {
                    return $rightPage === $pageNo ? 0 : -1;
                }
                if ($rightPage === $pageNo) {
                    return 1;
                }

                return $leftPage <=> $rightPage;
            });
            $paper = $authoring['paper'] ?? [];
            $paperWidth = is_numeric($paper['width_mm'] ?? null) ? (float) $paper['width_mm'] : 210.0;
            $paperHeight = is_numeric($paper['height_mm'] ?? null) ? (float) $paper['height_mm'] : 297.0;
            foreach ((array) $boxes as $box) {
                if (! is_array($box)) {
                    continue;
                }
                $writeInPage = max(1, (int) ($box['page_no'] ?? 1));
                $region = isset($box['x_mm'], $box['y_mm'], $box['w_mm'], $box['h_mm'])
                    ? $this->normalizedRegion([
                        'x' => (float) $box['x_mm'] / $paperWidth,
                        'y' => (float) $box['y_mm'] / $paperHeight,
                        'w' => (float) $box['w_mm'] / $paperWidth,
                        'h' => (float) $box['h_mm'] / $paperHeight,
                    ])
                    : $this->normalizedRegion($box['region'] ?? $box);
                if ($region !== null) {
                    $regions = [$writeInPage => ['applicant_number_write_in' => $region]];
                    break;
                }
            }
            if ($regions === [] && is_array($mappingInputIdentity['regions'] ?? null)) {
                $regions = $mappingInputIdentity['regions'];
            }
            if ($regions === [] && is_array($identity['regions'] ?? null)) {
                $regions = $identity['regions'];
            }
        }
        if (is_array($regions['applicant_number_write_in'] ?? null)) {
            $candidate = $regions['applicant_number_write_in'];
            $regions = [max(1, (int) ($candidate['page_no'] ?? 1)) => ['applicant_number_write_in' => $candidate]];
        }

        $writeIn = null;
        foreach ($this->orderedRegionPages((array) $regions, $pageNo) as $regionPage => $pageRegions) {
            if (! is_array($pageRegions) || ! is_array($pageRegions['applicant_number_write_in'] ?? null)) {
                continue;
            }
            $region = $this->normalizedIdentityRegion($pageRegions['applicant_number_write_in'], [
                'image_width' => $mappingInput['width_px'] ?? null,
                'image_height' => $mappingInput['height_px'] ?? null,
            ]);
            if ($region === null) {
                continue;
            }
            $writeInPage = is_numeric($regionPage) ? max(1, (int) $regionPage) : 1;
            $asset = $this->gradingPageAsset($session, $sheet, $writeInPage);
            $writeIn = [
                'page_no' => $writeInPage,
                'region' => $region,
                'aligned_url' => $asset['url'] ?? null,
                'sha256' => $asset['sha256'] ?? null,
                'width' => $asset['width'] ?? null,
                'height' => $asset['height'] ?? null,
            ];
            break;
        }

        $map['identity_write_in'] = $writeIn;
        $documentFields = [];
        $manifest = is_array($template?->authoring_manifest)
            ? $template->authoring_manifest
            : (is_array($template?->review_manifest_json) ? $template->review_manifest_json : []);
        $documentFieldsManifest = $template?->authoring_manifest['studio']['document_fields']
            ?? $template?->authoring_manifest['mapping']['input']['document_fields']
            ?? $template?->authoring_manifest['canonical']['mapping']['document_fields']
            ?? $template?->authoring_manifest['document_fields']
            ?? $template?->review_manifest_json['document_fields']
            ?? [];
        $paper = $manifest['paper'] ?? [];
        $paperWidth = is_numeric($paper['width_mm'] ?? null) ? (float) $paper['width_mm'] : 210.0;
        $paperHeight = is_numeric($paper['height_mm'] ?? null) ? (float) $paper['height_mm'] : 297.0;
        $fiducialAnchored = $template?->registration_mode === OmrRegistrationMode::FiducialAnchored;
        $markerInsets = (array) (
            $manifest['marker']['insets_mm']
            ?? $manifest['insets_mm']
            ?? ['left' => 12.0, 'top' => 12.0, 'right' => 12.0, 'bottom' => 14.0]
        );
        $frameLeft = $fiducialAnchored ? (float) ($markerInsets['left'] ?? 12.0) : 0.0;
        $frameTop = $fiducialAnchored ? (float) ($markerInsets['top'] ?? 12.0) : 0.0;
        $frameWidth = $fiducialAnchored
            ? $paperWidth - $frameLeft - (float) ($markerInsets['right'] ?? 12.0)
            : $paperWidth;
        $frameHeight = $fiducialAnchored
            ? $paperHeight - $frameTop - (float) ($markerInsets['bottom'] ?? 14.0)
            : $paperHeight;
        $asset = $this->gradingPageAsset($session, $sheet, $pageNo);
        foreach ((array) $documentFieldsManifest as $field) {
            if (! is_array($field) || (int) ($field['page_no'] ?? 1) !== $pageNo) {
                continue;
            }
            $region = $this->normalizedRegion($field['region'] ?? null);
            if ($region === null && $frameWidth > 0 && $frameHeight > 0) {
                $x = $field['x_mm'] ?? null;
                $y = $field['y_mm'] ?? null;
                $width = $field['w_mm'] ?? null;
                $height = $field['h_mm'] ?? null;
                if (is_numeric($x) && is_numeric($y) && is_numeric($width) && is_numeric($height)) {
                    $region = $this->normalizedRegion([
                        'x' => max(0.0, min(1.0, ((float) $x - $frameLeft) / $frameWidth)),
                        'y' => max(0.0, min(1.0, ((float) $y - $frameTop) / $frameHeight)),
                        'w' => max(0.0, min(1.0, (float) $width / $frameWidth)),
                        'h' => max(0.0, min(1.0, (float) $height / $frameHeight)),
                    ]);
                }
            }
            if ($region === null) {
                continue;
            }
            $documentFields[] = [
                'id' => (string) ($field['id'] ?? 'field-'.$pageNo),
                'type' => (string) ($field['type'] ?? 'custom_input'),
                'label' => (string) ($field['label'] ?? 'Document Field'),
                'page_no' => $pageNo,
                'region' => $region,
                'transcription' => false,
                'aligned_url' => $asset['url'] ?? null,
            ];
        }
        if ($writeIn !== null && $writeIn['page_no'] === $pageNo) {
            $documentFields[] = [
                'id' => 'applicant_number_write_in-'.$pageNo,
                'type' => 'applicant_number_write_in',
                'label' => 'Applicant Number (Handwritten)',
                'page_no' => $pageNo,
                'region' => $writeIn['region'],
                'aligned_url' => $writeIn['aligned_url'],
            ];
        }
        $map['document_fields'] = $documentFields;

        return $map;
    }

    /** @return array<string,mixed> */
    private function verificationSheetMap(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationCapture $capture,
    ): array {
        $summary = is_array($capture->summary) ? $capture->summary : [];
        $evidence = is_array($summary['evidence'] ?? null) ? $summary['evidence'] : [];
        $rows = [];
        foreach ((array) ($evidence['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $options = [];
            foreach ((array) ($item['options'] ?? []) as $option) {
                if (! is_array($option)) {
                    continue;
                }
                $code = $this->scalarString($option['code'] ?? ($option['option'] ?? null));
                $region = $this->normalizedRegion($option['region'] ?? ($option['rect'] ?? null));
                if ($code !== null && $region !== null) {
                    $options[] = ['code' => $code, 'region' => $region];
                }
            }
            $detected = is_array($item['detected'] ?? null) ? $item['detected'] : [];
            $expected = $this->scalarString($item['expected'] ?? null);
            $itemRect = $this->normalizedRegion($item['region'] ?? null);
            $rows[] = [
                'item_id' => $item['native_label'] ?? null,
                'group_type' => OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA,
                'area_key' => $item['area_code'] ?? null,
                'area_position' => $item['position'] ?? null,
                'question_no' => $item['question_no'] ?? null,
                'page_no' => $item['page_no'] ?? $capture->page_no,
                'machine' => [
                    'state' => $detected['state'] ?? null,
                    'selected_option' => $detected['selected_option'] ?? null,
                    'observed_options' => array_values((array) ($detected['observed_options'] ?? [])),
                    'option_observations' => array_values((array) ($detected['option_observations'] ?? [])),
                    'confidence' => $detected['confidence'] ?? null,
                    'issue_codes' => array_values((array) ($detected['issue_codes'] ?? [])),
                    'evidence_hash' => $capture->evidence_hash,
                ],
                'expected' => ['option' => $expected],
                'geometry' => [
                    'item_rect' => $itemRect,
                    'crop_rect' => $itemRect,
                    'options' => $options,
                    'aligned_asset' => [
                        'width' => $capture->alignedAsset?->metadata['width'] ?? null,
                        'height' => $capture->alignedAsset?->metadata['height'] ?? null,
                        'sha256' => $capture->aligned_asset_hash ?: $capture->alignedAsset?->sha256,
                    ],
                    'geometry_manifest_hash' => $capture->page_manifest_hash,
                    'evidence_version' => $capture->evidence_version,
                    'page_no' => (int) ($item['page_no'] ?? $capture->page_no),
                ],
                'issues' => [],
            ];
        }
        if ((array) ($evidence['identity'] ?? []) !== []) {
            foreach (OmrTemplateVerificationItemEvidence::query()
                ->where('capture_id', $capture->id)
                ->where('group_type', OmrTemplateVerificationItemEvidence::GROUP_IDENTITY)
                ->orderBy('question_no')
                ->orderBy('item_id')
                ->get() as $identityItem) {
                $rows[] = $this->itemRow($run, $capture, $identityItem, null, null, false);
            }
        }

        $identityWriteIn = $this->resolveIdentityWriteIn($run, $capture);
        $map = $this->sheetMap($rows, (int) $capture->page_no);
        $map['identity_write_in'] = $identityWriteIn;
        $map['document_fields'] = $this->documentFields($run, (int) $capture->page_no, $identityWriteIn, $capture);

        return $map;
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed> */
    private function sheetMap(array $rows, int $pageNo): array
    {
        $items = [];
        $width = null;
        $height = null;
        $assetHash = null;
        foreach ($rows as $row) {
            if ((int) ($row['page_no'] ?? $pageNo) !== $pageNo) {
                continue;
            }
            $geometry = is_array($row['geometry'] ?? null) ? $row['geometry'] : [];
            $asset = is_array($geometry['aligned_asset'] ?? null) ? $geometry['aligned_asset'] : [];
            $width ??= is_numeric($asset['width'] ?? null) ? (int) $asset['width'] : null;
            $height ??= is_numeric($asset['height'] ?? null) ? (int) $asset['height'] : null;
            $assetHash ??= $this->hashOrNull($asset['sha256'] ?? null);
            $items[] = [
                'id' => $row['id'] ?? null,
                'item_id' => $row['item_id'] ?? null,
                'group_type' => $row['group_type'] ?? null,
                'area_key' => $row['area_key'] ?? null,
                'area_position' => $row['area_position'] ?? null,
                'question_no' => $row['question_no'] ?? null,
                'page_no' => $pageNo,
                'machine' => is_array($row['machine'] ?? null) ? $row['machine'] : null,
                'expected' => $row['expected'] ?? null,
                'geometry' => [
                    'item_rect' => $geometry['item_rect'] ?? null,
                    'crop_rect' => $geometry['crop_rect'] ?? null,
                    'options' => is_array($geometry['options'] ?? null) ? $geometry['options'] : [],
                    'page_no' => $pageNo,
                    'geometry_manifest_hash' => $geometry['geometry_manifest_hash'] ?? null,
                    'evidence_version' => $geometry['evidence_version'] ?? null,
                ],
                'issues' => is_array($row['issues'] ?? null) ? $row['issues'] : [],
            ];
        }

        return [
            'page_no' => $pageNo,
            'width' => is_numeric($width) && (int) $width > 0 ? (int) $width : 2060,
            'height' => is_numeric($height) && (int) $height > 0 ? (int) $height : 3144,
            'sha256' => $assetHash,
            'items' => $items,
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function sortRows(array $rows): array
    {
        usort($rows, static function (array $left, array $right): int {
            $leftPage = is_numeric($left['page_no'] ?? null) ? (int) $left['page_no'] : PHP_INT_MAX;
            $rightPage = is_numeric($right['page_no'] ?? null) ? (int) $right['page_no'] : PHP_INT_MAX;
            if ($leftPage !== $rightPage) {
                return $leftPage <=> $rightPage;
            }
            $leftGroup = (string) ($left['group_type'] ?? '');
            $rightGroup = (string) ($right['group_type'] ?? '');
            $groupOrder = ['aptitude_area' => 0, 'identity' => 1, 'calibration' => 2];
            $groupComparison = ($groupOrder[$leftGroup] ?? PHP_INT_MAX) <=> ($groupOrder[$rightGroup] ?? PHP_INT_MAX);
            if ($groupComparison !== 0) {
                return $groupComparison;
            }
            $leftArea = is_numeric($left['area_position'] ?? null) ? (int) $left['area_position'] : PHP_INT_MAX;
            $rightArea = is_numeric($right['area_position'] ?? null) ? (int) $right['area_position'] : PHP_INT_MAX;
            if ($leftArea !== $rightArea) {
                return $leftArea <=> $rightArea;
            }
            $leftQuestion = is_numeric($left['question_no'] ?? null) ? (int) $left['question_no'] : PHP_INT_MAX;
            $rightQuestion = is_numeric($right['question_no'] ?? null) ? (int) $right['question_no'] : PHP_INT_MAX;
            if ($leftQuestion !== $rightQuestion) {
                return $leftQuestion <=> $rightQuestion;
            }

            return strcasecmp((string) ($left['item_id'] ?? ''), (string) ($right['item_id'] ?? ''))
                ?: ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        return array_values($rows);
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function filterRowsByArea(array $rows, string $area): array
    {
        return $this->filterRowsByAreaAndPage($rows, $area, null);
    }

    /** @param array<string,mixed> $row */
    private function isMismatch(array $row): bool
    {
        $expected = is_array($row['expected'] ?? null)
            ? ($row['expected']['option'] ?? null)
            : ($row['expected'] ?? null);
        $machine = is_array($row['machine'] ?? null) ? $row['machine'] : [];
        $state = strtolower((string) ($machine['state'] ?? ($row['state'] ?? '')));
        if (($row['_expected_blank'] ?? false) === true) {
            return $state !== 'blank'
                || in_array('missing_item_geometry', (array) ($row['issues'] ?? []), true)
                || in_array('missing_option_geometry', (array) ($row['issues'] ?? []), true);
        }
        if ($expected === null || trim((string) $expected) === '') {
            return false;
        }
        if (in_array($state, ['ambiguous', 'unreadable', 'blank', 'missing'], true)) {
            return true;
        }
        $selected = $machine['selected_option'] ?? ($row['selected_option'] ?? null);

        return $state === 'marked' && (string) $selected !== (string) $expected;
    }

    private function scannerState(mixed $state, ?string $detected, ?string $expected): string
    {
        $state = strtolower((string) $state);
        if ($state === 'ambiguous' || $state === 'unreadable') {
            return OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS;
        }
        if (in_array($state, ['blank', 'missing'], true) || $detected === null) {
            return OmrTemplateVerificationItemEvidence::STATE_MISSING;
        }
        if ($expected !== null) {
            return hash_equals($expected, $detected)
                ? OmrTemplateVerificationItemEvidence::STATE_MATCH
                : OmrTemplateVerificationItemEvidence::STATE_MISMATCH;
        }

        return in_array($state, ['match', 'mismatch'], true)
            ? $state
            : OmrTemplateVerificationItemEvidence::STATE_MATCH;
    }

    private function scalarString(mixed $value): ?string
    {
        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }

    /** @param list<array<string,mixed>> $rows */
    private function arrayGroupNavigation(array $rows, string $method, int $captureId, int $perPage, string $filter): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groupType = (string) ($row['group_type'] ?? 'aptitude_area');
            $key = $groupType === 'aptitude_area' ? (string) ($row['area_key'] ?? 'unknown') : $groupType;
            $groups[$key]['group_type'] = $groupType;
            $groups[$key]['area_key'] = $row['area_key'] ?? null;
            $groups[$key]['area_position'] = $row['area_position'] ?? null;
            $groups[$key]['total'] = ($groups[$key]['total'] ?? 0) + 1;
            $groups[$key]['mismatch_total'] = ($groups[$key]['mismatch_total'] ?? 0) + ($this->isMismatch($row) ? 1 : 0);
            $groups[$key]['needs_review_total'] = ($groups[$key]['needs_review_total'] ?? 0) + ($this->needsReview($row) ? 1 : 0);
            $groups[$key]['reviewed_total'] = ($groups[$key]['reviewed_total'] ?? 0) + ($this->isReviewed($row) ? 1 : 0);
        }
        uasort($groups, static fn (array $left, array $right): int => ((int) ($left['area_position'] ?? PHP_INT_MAX)) <=> ((int) ($right['area_position'] ?? PHP_INT_MAX)));
        $filteredRows = array_values(array_filter($rows, fn (array $row): bool => $this->matchesFilter($row, $filter)));
        $navigation = [[
            'key' => 'all', 'label' => 'All areas', 'group_type' => 'all', 'area_key' => null,
            'area_position' => null, 'total' => count($rows),
            'mismatch_total' => count(array_filter($rows, fn (array $row): bool => $this->isMismatch($row))),
            'needs_review_total' => count(array_filter($rows, fn (array $row): bool => $this->needsReview($row))),
            'reviewed_total' => count(array_filter($rows, fn (array $row): bool => $this->isReviewed($row))),
            'page_count' => max(1, (int) ceil(count($filteredRows) / $perPage)),
        ]];
        foreach ($groups as $key => $group) {
            $groupRows = array_values(array_filter($rows, static fn (array $row): bool => (
                (($row['group_type'] ?? 'aptitude_area') === $group['group_type'])
                && (($row['area_key'] ?? null) === $group['area_key'])
            )));
            $groupFilteredRows = array_values(array_filter($groupRows, fn (array $row): bool => $this->matchesFilter($row, $filter)));
            $navigation[] = [
                'key' => $key,
                'label' => $group['group_type'] === 'identity' ? 'Identity' : ($group['group_type'] === 'calibration' ? 'Calibration' : $key),
                ...$group,
                'page_count' => max(1, (int) ceil(count($groupFilteredRows) / $perPage)),
            ];
        }

        return $navigation;
    }
}

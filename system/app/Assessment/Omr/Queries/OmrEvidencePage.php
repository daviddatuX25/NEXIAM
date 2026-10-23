<?php

namespace App\Assessment\Omr\Queries;

use InvalidArgumentException;

use function array_map;
use function array_slice;
use function array_values;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strcmp;
use function strtolower;

/**
 * Immutable, bounded evidence-page projection for physical OMR review.
 *
 * The query layer supplies already-authorized rows and lazy private URLs. This
 * DTO never resolves assets, calls a provider, or writes workflow state.
 */
final readonly class OmrEvidencePage
{
    public const FILTER_ALL = 'all';

    public const FILTER_MISMATCH = 'mismatch';

    public const FILTER_NEEDS_REVIEW = 'needs_review';

    public const FILTER_REVIEWED = 'reviewed';

    public const FILTERS = [
        self::FILTER_ALL,
        self::FILTER_MISMATCH,
        self::FILTER_NEEDS_REVIEW,
        self::FILTER_REVIEWED,
    ];

    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 50;

    public const MAX_SHEET_ITEMS = 500;

    public string $method;

    public int $captureId;

    public int $capture;

    public string $area;

    public ?int $areaPosition;

    public int $total;

    public int $filteredTotal;

    public int $mismatchTotal;

    public int $needsReviewTotal;

    public int $reviewedTotal;

    public int $page;

    public int $perPage;

    public int $pageCount;

    public string $filter;

    public string $evidenceVersion;

    public ?string $evidenceHash;

    public int $attemptNo;

    public int $capturePageNo;

    /** @var list<array<string, mixed>> */
    public array $groupNavigation;

    /** @var list<array<string, mixed>> */
    public array $items;

    /** @var array<string, string|null> */
    public array $lazyAssetUrls;

    /** @var array<string, mixed> */
    public array $sheetMap;

    /** @var list<array<string, mixed>> */
    public array $groups;

    /** @var list<array<string, mixed>> */
    public array $rows;

    /** @var array<string, string|null> */
    public array $assetUrls;

    public bool $reviewable;

    public int $parentVersion;

    /** @var list<array{code:string,label:string}> */
    public array $reviewReasons;

    public int $reviewNoteMax;

    public bool $includeReviewMetadata;

    /**
     * @param  list<array<string, mixed>>  $groupNavigation
     * @param  array<string, int|float|string|null>  $totals
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, string|null>  $lazyAssetUrls
     * @param  array<string, mixed>  $sheetMap
     */
    public function __construct(
        string $method,
        int $captureId,
        array $groupNavigation = [],
        array $totals = [],
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        string $filter = self::FILTER_ALL,
        array $items = [],
        array $lazyAssetUrls = [],
        string $area = 'all',
        ?int $areaPosition = null,
        ?int $total = null,
        ?int $mismatchTotal = null,
        ?int $needsReviewTotal = null,
        ?int $reviewedTotal = null,
        ?int $filteredTotal = null,
        ?int $pageCount = null,
        string $evidenceVersion = 'v1',
        ?string $evidenceHash = null,
        int $attemptNo = 0,
        int $capturePageNo = 1,
        array $sheetMap = [],
        bool $reviewable = false,
        int $parentVersion = 0,
        array $reviewReasons = [],
        int $reviewNoteMax = 2000,
        bool $includeReviewMetadata = false,
    ) {
        if (! in_array($method, ['pre_shaded', 'hand_shaded'], true)) {
            throw new InvalidArgumentException('Evidence method must be pre_shaded or hand_shaded.');
        }
        if ($captureId < 1) {
            throw new InvalidArgumentException('Evidence capture id must be positive.');
        }
        if ($page < 1) {
            throw new InvalidArgumentException('Evidence page must be positive.');
        }
        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException('Evidence per-page must be between 1 and 50.');
        }
        if (! in_array($filter, self::FILTERS, true)) {
            throw new InvalidArgumentException('Evidence filter must be all, mismatch, needs_review, or reviewed.');
        }
        if ($areaPosition !== null && $areaPosition < 1) {
            throw new InvalidArgumentException('Evidence area position must be positive when present.');
        }

        $normalizedItems = array_slice($this->stableItems($items), 0, $perPage);
        $resolvedTotal = $total ?? $this->integerTotal($totals['total'] ?? count($normalizedItems));
        $resolvedFilteredTotal = $filteredTotal ?? $this->integerTotal(
            $totals['filtered_total'] ?? count($normalizedItems),
        );
        $resolvedMismatchTotal = $mismatchTotal ?? $this->integerTotal($totals['mismatch_total'] ?? 0);
        $resolvedNeedsReviewTotal = $needsReviewTotal ?? $this->integerTotal($totals['needs_review_total'] ?? 0);
        $resolvedReviewedTotal = $reviewedTotal ?? $this->integerTotal($totals['reviewed_total'] ?? 0);
        $resolvedPageCount = $pageCount ?? $this->integerTotal(
            $totals['page_count'] ?? max(1, (int) ceil($resolvedFilteredTotal / $perPage)),
        );
        if ($resolvedTotal < 0
            || $resolvedFilteredTotal < 0
            || $resolvedMismatchTotal < 0
            || $resolvedNeedsReviewTotal < 0
            || $resolvedReviewedTotal < 0
            || $resolvedPageCount < 0) {
            throw new InvalidArgumentException('Evidence totals cannot be negative.');
        }

        $this->method = $method;
        $this->captureId = $captureId;
        $this->capture = $captureId;
        $this->area = $area;
        $this->areaPosition = $areaPosition;
        $this->total = $resolvedTotal;
        $this->filteredTotal = $resolvedFilteredTotal;
        $this->mismatchTotal = $resolvedMismatchTotal;
        $this->needsReviewTotal = $resolvedNeedsReviewTotal;
        $this->reviewedTotal = $resolvedReviewedTotal;
        $this->page = $page;
        $this->perPage = $perPage;
        $this->pageCount = $resolvedPageCount;
        $this->filter = $filter;
        $this->evidenceVersion = $evidenceVersion !== '' ? $evidenceVersion : 'v1';
        $this->evidenceHash = $evidenceHash;
        $this->attemptNo = max(0, $attemptNo);
        $this->capturePageNo = max(1, $capturePageNo);
        $this->groupNavigation = array_values(array_map(
            fn (mixed $group): array => $this->withoutProviderData(
                is_array($group) ? $group : ['key' => (string) $group],
            ),
            $groupNavigation,
        ));
        $this->items = $normalizedItems;
        $this->lazyAssetUrls = $this->safeAssetUrls($lazyAssetUrls);
        $safeSheetMap = $this->withoutProviderData($sheetMap);
        $safeSheetMap['items'] = array_slice(
            $this->stableItems(is_array($safeSheetMap['items'] ?? null) ? $safeSheetMap['items'] : []),
            0,
            self::MAX_SHEET_ITEMS,
        );
        $this->sheetMap = $safeSheetMap;
        $this->groups = $this->groupNavigation;
        $this->rows = $this->items;
        $this->assetUrls = $this->lazyAssetUrls;
        $this->reviewable = $reviewable;
        $this->parentVersion = max(0, $parentVersion);
        $this->reviewReasons = array_values(array_filter($reviewReasons, static fn (mixed $reason): bool => is_array($reason)
            && is_string($reason['code'] ?? null)
            && is_string($reason['label'] ?? null)));
        $this->reviewNoteMax = max(0, $reviewNoteMax);
        $this->includeReviewMetadata = $includeReviewMetadata;
    }

    public function captureId(): int
    {
        return $this->captureId;
    }

    /** @return list<array<string, mixed>> */
    public function groups(): array
    {
        return $this->groupNavigation;
    }

    /** @return list<array<string, mixed>> */
    public function rows(): array
    {
        return $this->items;
    }

    public function hasMismatches(): bool
    {
        return $this->mismatchTotal > 0;
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    /** @return array{total:int,filtered_total:int,mismatch_total:int,needs_review_total:int,reviewed_total:int,page_count:int} */
    public function totals(): array
    {
        return [
            'total' => $this->total,
            'filtered_total' => $this->filteredTotal,
            'mismatch_total' => $this->mismatchTotal,
            'needs_review_total' => $this->needsReviewTotal,
            'reviewed_total' => $this->reviewedTotal,
            'page_count' => $this->pageCount,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = [
            'method' => $this->method,
            'capture_id' => $this->captureId,
            'area' => $this->area,
            'area_position' => $this->areaPosition,
            'total' => $this->total,
            'filtered_total' => $this->filteredTotal,
            'mismatch_total' => $this->mismatchTotal,
            'needs_review_total' => $this->needsReviewTotal,
            'reviewed_total' => $this->reviewedTotal,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'page_count' => $this->pageCount,
            'filter' => $this->filter,
            'evidence_version' => $this->evidenceVersion,
            'evidence_hash' => $this->evidenceHash,
            'attempt_no' => $this->attemptNo,
            'capture_page_no' => $this->capturePageNo,
            'groups' => $this->groupNavigation,
            'items' => $this->items,
            'lazy_asset_urls' => $this->lazyAssetUrls,
            'sheet_map' => $this->sheetMap,
        ];

        return $this->includeReviewMetadata ? [
            ...$payload,
            'reviewable' => $this->reviewable,
            'parent_version' => $this->parentVersion,
            'review_reasons' => $this->reviewReasons,
            'review_note_max' => $this->reviewNoteMax,
        ] : $payload;
    }

    /** @param list<array<string, mixed>> $items @return list<array<string, mixed>> */
    private function stableItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $normalized[] = $this->withoutProviderData($item);
        }

        usort($normalized, static function (array $left, array $right): int {
            $leftPage = is_numeric($left['page_no'] ?? null) ? (int) $left['page_no'] : PHP_INT_MAX;
            $rightPage = is_numeric($right['page_no'] ?? null) ? (int) $right['page_no'] : PHP_INT_MAX;
            if ($leftPage !== $rightPage) {
                return $leftPage <=> $rightPage;
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
            $itemOrder = strcmp((string) ($left['item_id'] ?? ''), (string) ($right['item_id'] ?? ''));
            if ($itemOrder !== 0) {
                return $itemOrder;
            }

            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });

        return array_values($normalized);
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function withoutProviderData(array $value): array
    {
        $forbidden = [
            'bytes', 'base64', 'base64_bytes', 'provider', 'provider_json',
            'provider_item_evidence', 'applicant', 'applicant_id', 'score',
            'scores', 'answer_key', 'correct_option', 'per_area_scores',
            'note', 'idempotency_key', 'correlation_id', 'command_name',
            'reviewed_by_user_id', 'reviewed_machine_evidence_hash',
            'supersedes_revision_id', 'secret', 'token', 'authorization', 'headers',
        ];
        foreach (array_keys($value) as $key) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $forbidden, true)
                || str_contains($normalizedKey, 'base64')
                || str_contains($normalizedKey, 'bytes')
                || str_starts_with($normalizedKey, 'provider')
                || str_contains($normalizedKey, 'applicant')
                || str_contains($normalizedKey, 'score')
                || str_contains($normalizedKey, 'answer_key')
                || str_contains($normalizedKey, 'correct_')
                || str_contains($normalizedKey, 'secret')
                || str_contains($normalizedKey, 'token')
                || ($this->isUrlKey($normalizedKey) && ! $this->isSafeUrl($value[$key]))) {
                unset($value[$key]);

                continue;
            }
            if (is_array($value[$key])) {
                $value[$key] = $this->withoutProviderData($value[$key]);
            }
        }

        return $value;
    }

    /** @param array<string, mixed> $urls @return array<string, string|null> */
    private function safeAssetUrls(array $urls): array
    {
        $safe = [];
        foreach ($urls as $key => $url) {
            if ($url === null || $this->isSafeUrl($url)) {
                $safe[(string) $key] = $url;
            }
        }

        return $safe;
    }

    private function isUrlKey(string $key): bool
    {
        return $key === 'url'
            || $key === 'href'
            || $key === 'src'
            || str_ends_with($key, '_url')
            || str_ends_with($key, '_href')
            || str_ends_with($key, '_src');
    }

    private function isSafeUrl(mixed $url): bool
    {
        if (! is_string($url)) {
            return false;
        }
        if (str_starts_with($url, '//')) {
            return false;
        }

        return preg_match('#^(?:https?://|/)#i', $url) === 1;
    }

    private function integerTotal(mixed $value): int
    {
        return is_int($value) || is_numeric($value) ? (int) $value : 0;
    }
}

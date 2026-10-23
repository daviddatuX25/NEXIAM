<?php

use App\Assessment\Omr\Queries\OmrEvidencePage;

test('evidence page is immutable, bounded, and keeps stable item order', function (): void {
    $page = new OmrEvidencePage(
        method: 'pre_shaded',
        captureId: 9,
        groupNavigation: [
            ['key' => 'all', 'total' => 3, 'mismatch_total' => 1, 'page_count' => 1],
        ],
        totals: ['total' => 3, 'mismatch_total' => 1, 'page_count' => 1],
        page: 1,
        perPage: 25,
        filter: OmrEvidencePage::FILTER_MISMATCH,
        items: [
            [
                'id' => 2,
                'area_position' => 2,
                'question_no' => 1,
                'item_id' => 'B-1',
                'provider_json' => ['secret' => true],
                'crop' => ['bytes' => 'not allowed'],
            ],
            [
                'id' => 1,
                'area_position' => 1,
                'question_no' => 2,
                'item_id' => 'A-2',
                'score' => 99,
            ],
            [
                'id' => 3,
                'area_position' => 1,
                'question_no' => 1,
                'item_id' => 'A-1',
                'expected' => 'A',
                'detected' => 'A',
            ],
        ],
        lazyAssetUrls: [
            'input' => 'https://example.test/evidence/input?signature=x',
            'aligned' => null,
        ],
    );

    expect($page->method)->toBe('pre_shaded')
        ->and($page->captureId())->toBe(9)
        ->and($page->capture)->toBe(9)
        ->and($page->total)->toBe(3)
        ->and($page->mismatchTotal)->toBe(1)
        ->and($page->perPage)->toBe(25)
        ->and($page->rows())->toHaveCount(3)
        ->and(array_column($page->items, 'item_id'))->toBe(['A-1', 'A-2', 'B-1'])
        ->and($page->items[2])->not->toHaveKey('provider_json')
        ->and($page->items[1])->not->toHaveKey('score')
        ->and($page->items[2]['crop'])->not->toHaveKey('bytes')
        ->and($page->lazyAssetUrls['input'])->toContain('signature=x')
        ->and($page->toArray())->toHaveKeys([
            'method', 'capture_id', 'area', 'area_position', 'total', 'mismatch_total',
            'page', 'per_page', 'page_count', 'filter', 'groups', 'items', 'lazy_asset_urls',
            'filtered_total', 'needs_review_total', 'reviewed_total', 'sheet_map',
        ]);
    expect(fn () => $page->method = 'hand_shaded')->toThrow(Error::class);
    expect(fn () => $page->items[] = ['item_id' => 'mutated'])->toThrow(Error::class);
});

test('evidence page sanitizes group and nested URLs and caps rows to per-page', function (): void {
    $page = new OmrEvidencePage(
        method: 'hand_shaded',
        captureId: 10,
        groupNavigation: [[
            'key' => 'area-1',
            'url' => 'javascript:alert(1)',
            'safe_url' => '/evidence/area-1',
            'nested' => [
                'provider_payload' => ['bytes' => 'secret'],
                'url' => 'data:text/html,pwned',
            ],
        ]],
        page: 1,
        perPage: 3,
        items: array_map(
            static fn (int $id): array => [
                'id' => $id,
                'area_position' => null,
                'question_no' => null,
                'item_id' => "item-{$id}",
                'nested' => [
                    'provider' => 'engine',
                    'bytes' => 'not allowed',
                    'url' => 'javascript:alert(2)',
                ],
            ],
            range(1, 8),
        ),
        lazyAssetUrls: [
            'safe' => '/evidence/input',
            'unsafe' => 'javascript:alert(3)',
        ],
        sheetMap: [
            'page_no' => 1,
            'width' => 1200,
            'height' => 1600,
            'items' => array_map(
                static fn (int $id): array => [
                    'id' => $id,
                    'item_id' => "sheet-item-{$id}",
                    'provider_json' => ['secret' => true],
                ],
                range(1, 502),
            ),
        ],
    );

    expect($page->items)->toHaveCount(3)
        ->and($page->groups()[0])->not->toHaveKey('url')
        ->and($page->groups()[0]['safe_url'])->toBe('/evidence/area-1')
        ->and($page->groups()[0]['nested'])->not->toHaveKey('provider_payload')
        ->and($page->groups()[0]['nested'])->not->toHaveKey('url')
        ->and($page->items[0]['nested'])->not->toHaveKeys(['provider', 'bytes', 'url'])
        ->and($page->sheetMap['items'])->toHaveCount(OmrEvidencePage::MAX_SHEET_ITEMS)
        ->and($page->sheetMap['items'][0])->not->toHaveKey('provider_json')
        ->and($page->lazyAssetUrls)->toBe(['safe' => '/evidence/input']);
});

test('evidence page validates method, filter, pagination, and totals', function (): void {
    expect(fn () => new OmrEvidencePage('invalid', 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new OmrEvidencePage('pre_shaded', 1, page: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new OmrEvidencePage('pre_shaded', 1, perPage: 51))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new OmrEvidencePage('pre_shaded', 1, filter: 'invalid'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new OmrEvidencePage('pre_shaded', 1, filter: 'mismatches'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new OmrEvidencePage('pre_shaded', 1, totals: ['total' => -1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new OmrEvidencePage('pre_shaded', 1, totals: ['needs_review_total' => -1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new OmrEvidencePage('pre_shaded', 1, totals: ['reviewed_total' => -1]))->toThrow(InvalidArgumentException::class);

    expect(OmrEvidencePage::FILTERS)->toBe([
        OmrEvidencePage::FILTER_ALL,
        OmrEvidencePage::FILTER_MISMATCH,
        OmrEvidencePage::FILTER_NEEDS_REVIEW,
        OmrEvidencePage::FILTER_REVIEWED,
    ]);
});

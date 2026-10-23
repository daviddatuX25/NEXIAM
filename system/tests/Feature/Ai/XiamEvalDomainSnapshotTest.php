<?php

declare(strict_types=1);

use App\Ai\Evaluation\XiamEvalDomainSnapshot;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use Illuminate\Support\Str;

it('detects same-count row updates without exposing row data or fingerprints', function (): void {
    seedE2Foundation();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('snapshot-test-key', 2))]);

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'SNAPSHOT-'.Str::upper(Str::random(12)),
        'name' => 'PRIVATE-SNAPSHOT-SENTINEL',
        'capacity' => 1,
        'is_active' => true,
    ]);
    $snapshots = app(XiamEvalDomainSnapshot::class);
    $before = $snapshots->capture();

    $room->update(['name' => 'UPDATED-PRIVATE-SENTINEL']);

    $after = $snapshots->capture();
    $difference = $snapshots->compare($before, $after);
    $output = json_encode($difference, JSON_THROW_ON_ERROR);
    $serializedSnapshots = serialize([$before, $after]);
    $roomTable = $room->getTable();

    expect($difference['changed'])->toBeTrue()
        ->and($difference['changed_tables'])->toBe([
            [
                'table' => $roomTable,
                'before_rows' => $before[$roomTable]['rows'],
                'after_rows' => $after[$roomTable]['rows'],
            ],
        ])
        ->and($before[$roomTable]['rows'])->toBe($after[$roomTable]['rows'])
        ->and($output)->not->toContain('PRIVATE-SNAPSHOT-SENTINEL')
        ->and($output)->not->toContain('UPDATED-PRIVATE-SENTINEL')
        ->and($serializedSnapshots)->not->toContain('PRIVATE-SNAPSHOT-SENTINEL')
        ->and($serializedSnapshots)->not->toContain('UPDATED-PRIVATE-SENTINEL')
        ->and($before)->not->toHaveKeys(['xiam_proposals', 'xiam_runs'])
        ->and($after)->not->toHaveKeys(['xiam_proposals', 'xiam_runs'])
        ->and($output)->not->toContain($before[$roomTable]['fingerprint'])
        ->and($output)->not->toContain($after[$roomTable]['fingerprint']);
});

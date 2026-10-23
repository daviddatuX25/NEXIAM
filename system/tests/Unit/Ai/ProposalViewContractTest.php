<?php

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use InvalidArgumentException;

function validProposalResult(): array
{
    $command = ['count' => 0, 'enabled' => false, 'note' => '', 'targets' => []];
    $fields = [
        ProposalViewContract::field('count', 'Count', ['type' => 'missing'], ['type' => 'integer', 'value' => 0, 'semantic' => 'count', 'unit' => null]),
        ProposalViewContract::field('enabled', 'Enabled', ['type' => 'missing'], ['type' => 'boolean', 'value' => false, 'true_label' => 'Enabled', 'false_label' => 'Disabled']),
        ProposalViewContract::field('note', 'Note', ['type' => 'missing'], ['type' => 'text', 'value' => '', 'multiline' => true]),
        ProposalViewContract::field('targets', 'Targets', ['type' => 'missing'], ['type' => 'list', 'items' => [], 'empty_label' => 'No targets']),
    ];

    return [
        'command_input' => $command,
        'proposal_review' => [
            'version' => 1,
            'operation' => 'bulk',
            'title' => 'Review values',
            'sections' => [ProposalViewContract::fieldsSection('values', 'Values', $fields)],
            'preflight' => ProposalViewContract::preflight('pass', []),
            'coverage' => [
                'count' => ['classification' => 'display', 'field' => 'count'],
                'enabled' => ['classification' => 'display', 'field' => 'enabled'],
                'note' => ['classification' => 'display', 'field' => 'note'],
                'targets' => ['classification' => 'display', 'field' => 'targets'],
            ],
        ],
    ];
}

test('review contract preserves nullish and falsy values plus immutable command', function (): void {
    $result = validProposalResult();
    $review = ProposalReview::fromResult('sample@1', $result);

    expect($review->commandInput)->toBe($result['command_input'])
        ->and($review->review['sections'][0]['fields'][0]['after']['value'])->toBe(0)
        ->and($review->review['sections'][0]['fields'][1]['after']['value'])->toBeFalse()
        ->and($review->review['sections'][0]['fields'][2]['after']['value'])->toBe('')
        ->and($review->review['sections'][0]['fields'][3]['after']['items'])->toBe([]);
});

test('review requires explicit command and review payloads', function (): void {
    expect(fn () => ProposalReview::fromResult('sample@1', []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ProposalReview::fromResult('sample@1', ['command_input' => []]))->toThrow(InvalidArgumentException::class);
});

test('review accepts grouped values and strict offset instants with explicit value states', function (): void {
    $command = [
        'dates' => [
            'day' => '2026-02-28',
            'starts_at' => '2026-02-28T09:30:00+08:00',
            'optional' => null,
        ],
    ];
    $fields = [
        ProposalViewContract::field('day', 'Day', ['type' => 'missing'], ['type' => 'date', 'value' => '2026-02-28']),
        ProposalViewContract::field('starts_at', 'Starts at', ['type' => 'missing'], ['type' => 'instant', 'value' => '2026-02-28T09:30:00+08:00', 'precision' => 'second', 'display_zone' => 'Asia/Manila']),
        ProposalViewContract::field('optional', 'Optional', ['type' => 'missing'], ['type' => 'null']),
        ProposalViewContract::field('note', 'Note', ['type' => 'missing'], ['type' => 'text', 'value' => "First line\nSecond line", 'multiline' => true]),
    ];
    $result = [
        'command_input' => $command,
        'proposal_review' => [
            'version' => 1,
            'operation' => 'update',
            'title' => 'Review grouped values',
            'sections' => [[
                'kind' => 'groups',
                'key' => 'schedule',
                'label' => 'Schedule',
                'groups' => [[
                    'key' => 'primary',
                    'label' => 'Primary',
                    'context' => ['type' => 'record', 'label' => 'Schedule', 'context' => []],
                    'fields' => $fields,
                    'preflight' => ProposalViewContract::preflight('pass', []),
                ]],
            ]],
            'preflight' => ProposalViewContract::preflight('pass', []),
            'coverage' => [
                'dates.day' => ['classification' => 'display', 'field' => 'day'],
                'dates.starts_at' => ['classification' => 'display', 'field' => 'starts_at'],
                'dates.optional' => ['classification' => 'internal', 'reason' => 'No optional value is applied.'],
            ],
        ],
    ];

    expect(fn () => ProposalReview::fromResult('sample@1', $result))->not->toThrow(InvalidArgumentException::class);
});

test('coverage rejects nonleaf and unknown paths even when all command leaves are covered', function (): void {
    $result = validProposalResult();
    $result['proposal_review']['coverage']['extra'] = ['classification' => 'internal', 'reason' => 'Not part of the command.'];

    expect(fn () => ProposalReview::fromResult('sample@1', $result))->toThrow(InvalidArgumentException::class);

    $result = validProposalResult();
    $result['command_input']['nested'] = ['value' => 'x'];
    $result['proposal_review']['coverage']['nested'] = ['classification' => 'internal', 'reason' => 'Container only.'];

    expect(fn () => ProposalReview::fromResult('sample@1', $result))->toThrow(InvalidArgumentException::class);
});

test('review requires explicit array payloads and rejects malformed calendar and instant values', function (): void {
    expect(fn () => ProposalReview::fromResult('sample@1', ['command_input' => null, 'proposal_review' => []]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ProposalReview::fromResult('sample@1', ['command_input' => [], 'proposal_review' => null]))->toThrow(InvalidArgumentException::class);

    foreach ([
        ['type' => 'date', 'value' => '2026-02-30'],
        ['type' => 'instant', 'value' => '2026-02-28T09:30:00', 'precision' => 'second', 'display_zone' => 'Asia/Manila'],
        ['type' => 'instant', 'value' => '2026-02-28T09:30:00.123Z', 'precision' => 'second', 'display_zone' => 'Asia/Manila'],
    ] as $bad) {
        $result = validProposalResult();
        $result['proposal_review']['sections'][0]['fields'][0]['after'] = $bad;

        expect(fn () => ProposalReview::fromResult('sample@1', $result))->toThrow(InvalidArgumentException::class);
    }
});

test('rejects field change labels that do not match the typed before and after values', function (): void {
    $result = validProposalResult();
    $result['proposal_review']['sections'][0]['fields'][0]['change'] = 'unchanged';

    expect(fn () => ProposalReview::fromResult('sample@1', $result))->toThrow(InvalidArgumentException::class);
});

test('coverage must map every normalized command leaf to a displayed field or justified internal reason', function (): void {
    $result = validProposalResult();
    unset($result['proposal_review']['coverage']['enabled']);

    expect(fn () => ProposalReview::fromResult('sample@1', $result))->toThrow(InvalidArgumentException::class);

    $result = validProposalResult();
    $result['proposal_review']['coverage']['enabled'] = ['classification' => 'internal', 'reason' => ''];
    expect(fn () => ProposalReview::fromResult('sample@1', $result))->toThrow(InvalidArgumentException::class);
});

test('rejects unknown shapes and noncanonical temporal values', function (): void {
    $result = validProposalResult();
    $result['proposal_review']['extra'] = true;
    expect(fn () => ProposalReview::fromResult('sample@1', $result))->toThrow(InvalidArgumentException::class);

    $result = validProposalResult();
    $bad = ['type' => 'instant', 'value' => '2026-02-30T12:00:00Z', 'precision' => 'second', 'display_zone' => 'Asia/Manila'];
    $result['proposal_review']['sections'][0]['fields'][0]['after'] = $bad;
    expect(fn () => ProposalReview::fromResult('sample@1', $result))->toThrow(InvalidArgumentException::class);
});

test('classifies identical before and after states including null and missing as unchanged', function (): void {
    $nullField = ProposalViewContract::field('f_null', 'Null field', ['type' => 'null'], ['type' => 'null']);
    expect($nullField['change'])->toBe('unchanged');

    $missingField = ProposalViewContract::field('f_missing', 'Missing field', ['type' => 'missing'], ['type' => 'missing']);
    expect($missingField['change'])->toBe('unchanged');

    $sameTextField = ProposalViewContract::field('f_text', 'Text field', ['type' => 'text', 'value' => 'sample', 'multiline' => false], ['type' => 'text', 'value' => 'sample', 'multiline' => false]);
    expect($sameTextField['change'])->toBe('unchanged');

    $clearFromText = ProposalViewContract::field('f_clear1', 'Clear from text', ['type' => 'text', 'value' => 'old', 'multiline' => false], ['type' => 'null']);
    expect($clearFromText['change'])->toBe('clear');

    $clearToMissing = ProposalViewContract::field('f_clear2', 'Clear to missing', ['type' => 'text', 'value' => 'old', 'multiline' => false], ['type' => 'missing']);
    expect($clearToMissing['change'])->toBe('clear');

    $setFromMissing = ProposalViewContract::field('f_set1', 'Set from missing', ['type' => 'missing'], ['type' => 'text', 'value' => 'new', 'multiline' => false]);
    expect($setFromMissing['change'])->toBe('set');

    $setFromNull = ProposalViewContract::field('f_set2', 'Set from null', ['type' => 'null'], ['type' => 'text', 'value' => 'new', 'multiline' => false]);
    expect($setFromNull['change'])->toBe('set');

    $mismatchedNull = ['key' => 'm1', 'label' => 'M1', 'role' => 'choice', 'change' => 'clear', 'before' => ['type' => 'null'], 'after' => ['type' => 'null']];
    expect(fn () => ProposalViewContract::fieldsSection('s1', 'S1', [$mismatchedNull]))->toThrow(InvalidArgumentException::class);

    $mismatchedMissing = ['key' => 'm2', 'label' => 'M2', 'role' => 'choice', 'change' => 'clear', 'before' => ['type' => 'missing'], 'after' => ['type' => 'missing']];
    expect(fn () => ProposalViewContract::fieldsSection('s2', 'S2', [$mismatchedMissing]))->toThrow(InvalidArgumentException::class);
});

test('enforces non-negative duration within safe integer bounds', function (): void {
    $zeroDuration = ProposalViewContract::field('d0', 'Duration 0', ['type' => 'missing'], ['type' => 'duration', 'value' => 0, 'unit' => 'minute']);
    expect($zeroDuration['after']['value'])->toBe(0);

    $posDuration = ProposalViewContract::field('d60', 'Duration 60', ['type' => 'missing'], ['type' => 'duration', 'value' => 60, 'unit' => 'minute']);
    expect($posDuration['after']['value'])->toBe(60);

    expect(fn () => ProposalViewContract::field('d_neg', 'Negative duration', ['type' => 'missing'], ['type' => 'duration', 'value' => -1, 'unit' => 'minute']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => ProposalViewContract::field('d_overflow', 'Overflow duration', ['type' => 'missing'], ['type' => 'duration', 'value' => 9007199254740992, 'unit' => 'minute']))
        ->toThrow(InvalidArgumentException::class);
});

test('enforces chronological interval ordering across valid RFC3339 instants', function (): void {
    $instantA = ['type' => 'instant', 'value' => '2026-10-08T09:00:00+08:00', 'precision' => 'second', 'display_zone' => 'Asia/Manila'];
    $instantB = ['type' => 'instant', 'value' => '2026-10-08T10:00:00+08:00', 'precision' => 'second', 'display_zone' => 'Asia/Manila'];
    $instantUtc = ['type' => 'instant', 'value' => '2026-10-08T01:00:00Z', 'precision' => 'second', 'display_zone' => 'Asia/Manila'];

    $validInterval = ProposalViewContract::field('i1', 'Valid interval', ['type' => 'missing'], [
        'type' => 'interval',
        'start' => $instantA,
        'end' => $instantB,
    ]);
    expect($validInterval['after']['type'])->toBe('interval');

    $equalInterval = ProposalViewContract::field('i2', 'Equal instant interval', ['type' => 'missing'], [
        'type' => 'interval',
        'start' => $instantA,
        'end' => $instantA,
    ]);
    expect($equalInterval['after']['type'])->toBe('interval');

    $crossZoneEqual = ProposalViewContract::field('i3', 'Cross-zone equal interval', ['type' => 'missing'], [
        'type' => 'interval',
        'start' => $instantUtc,
        'end' => $instantA,
    ]);
    expect($crossZoneEqual['after']['type'])->toBe('interval');

    expect(fn () => ProposalViewContract::field('i_bad', 'Inverted interval', ['type' => 'missing'], [
        'type' => 'interval',
        'start' => $instantB,
        'end' => $instantA,
    ]))->toThrow(InvalidArgumentException::class);

    $instantUtcLater = ['type' => 'instant', 'value' => '2026-10-08T02:00:00Z', 'precision' => 'second', 'display_zone' => 'Asia/Manila'];
    expect(fn () => ProposalViewContract::field('i_bad_zone', 'Inverted cross-zone interval', ['type' => 'missing'], [
        'type' => 'interval',
        'start' => $instantUtcLater,
        'end' => $instantA,
    ]))->toThrow(InvalidArgumentException::class);
});

test('enforces javascript safe integer bounds on integers', function (): void {
    $zero = ProposalViewContract::field('n0', 'Zero', ['type' => 'missing'], ['type' => 'integer', 'value' => 0, 'semantic' => 'count', 'unit' => null]);
    expect($zero['after']['value'])->toBe(0);

    $maxSafe = ProposalViewContract::field('n_max', 'Max safe', ['type' => 'missing'], ['type' => 'integer', 'value' => 9007199254740991, 'semantic' => 'count', 'unit' => null]);
    expect($maxSafe['after']['value'])->toBe(9007199254740991);

    $minSafe = ProposalViewContract::field('n_min', 'Min safe', ['type' => 'missing'], ['type' => 'integer', 'value' => -9007199254740991, 'semantic' => 'count', 'unit' => null]);
    expect($minSafe['after']['value'])->toBe(-9007199254740991);

    expect(fn () => ProposalViewContract::field('n_overflow', 'Overflow int', ['type' => 'missing'], ['type' => 'integer', 'value' => 9007199254740992, 'semantic' => 'count', 'unit' => null]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => ProposalViewContract::field('n_underflow', 'Underflow int', ['type' => 'missing'], ['type' => 'integer', 'value' => -9007199254740992, 'semantic' => 'count', 'unit' => null]))
        ->toThrow(InvalidArgumentException::class);
});

test('requires non-empty and non-whitespace labels for records and enums', function (): void {
    $validEnum = ProposalViewContract::field('e_ok', 'Valid enum', ['type' => 'missing'], ['type' => 'enum', 'code' => 'draft', 'label' => 'Draft only']);
    expect($validEnum['after']['label'])->toBe('Draft only');

    $validRecord = ProposalViewContract::field('r_ok', 'Valid record', ['type' => 'missing'], ['type' => 'record', 'label' => 'Session 1', 'context' => []]);
    expect($validRecord['after']['label'])->toBe('Session 1');

    expect(fn () => ProposalViewContract::field('e_empty', 'Empty enum label', ['type' => 'missing'], ['type' => 'enum', 'code' => 'draft', 'label' => '']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => ProposalViewContract::field('e_space', 'Whitespace enum label', ['type' => 'missing'], ['type' => 'enum', 'code' => 'draft', 'label' => "   \t\n"]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => ProposalViewContract::field('e_code', 'Empty enum code', ['type' => 'missing'], ['type' => 'enum', 'code' => '', 'label' => 'Draft']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => ProposalViewContract::field('r_empty', 'Empty record label', ['type' => 'missing'], ['type' => 'record', 'label' => '', 'context' => []]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => ProposalViewContract::field('r_space', 'Whitespace record label', ['type' => 'missing'], ['type' => 'record', 'label' => '   ', 'context' => []]))
        ->toThrow(InvalidArgumentException::class);
});

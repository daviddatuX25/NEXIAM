<?php

use App\Assessment\Omr\Operational\OmrExecutionKind;
use App\Assessment\Omr\Operational\OmrWorkbenchProjection;

it('retains camera target identities and Next Set grants without exposing examination keys', function (): void {
    $targetKey = '["production:7:9","capture-1",1,4,null,null]';
    $projection = new OmrWorkbenchProjection(
        workbenchId: 'production:7:9', executionKind: OmrExecutionKind::Production,
        revision: 4, phase: OmrWorkbenchProjection::PHASE_CAPTURE,
        context: [
            'capture_target' => ['target_key' => $targetKey, 'targetKey' => $targetKey],
            'capture_receipt' => ['target_key' => $targetKey, 'target' => ['targetKey' => $targetKey]],
            'capture_actions' => ['next_set' => ['authorizationId' => 'grant-1', 'targetKey' => 'next-target', 'acceptedTargetKey' => $targetKey,
                'answer_key' => ['A'], 'correct_option' => 'A']],
            'answer_key' => ['B'], 'expected_vector' => ['B'], 'declared_set_code' => 'B',
            'roster' => ['target_key' => 'must-not-bypass-redaction-outside-the-protocol'],
        ],
    );
    $safe = $projection->toSafeArray()['context'];
    expect($safe['capture_target']['target_key'])->toBe($targetKey)
        ->and($safe['capture_receipt']['target']['targetKey'])->toBe($targetKey)
        ->and($safe['capture_actions']['next_set']['acceptedTargetKey'])->toBe($targetKey)
        ->and($safe['capture_actions']['next_set'])->not->toHaveKeys(['answer_key', 'correct_option'])
        ->and($safe)->not->toHaveKeys(['answer_key', 'expected_vector', 'declared_set_code'])
        ->and($safe['roster'])->not->toHaveKey('target_key');
});

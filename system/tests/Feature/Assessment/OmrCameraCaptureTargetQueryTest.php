<?php

declare(strict_types=1);

use App\Assessment\Actions\Omr\Camera\OmrAcceptedCaptureReceipt;
use App\Assessment\Actions\Omr\Camera\OmrCameraProtocol;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Actions\Omr\UploadOmrCaptureSheet;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrQrPayload;
use App\Assessment\Omr\Operational\OmrDiagnosticRunStatusSnapshot;
use App\Assessment\Omr\Operational\OmrExecutionKind;
use App\Assessment\Omr\Operational\OmrProductionWorkbenchProjector;
use App\Assessment\Omr\Operational\OmrWorkbenchProjection;
use App\Assessment\Omr\Queries\OmrCameraCaptureTargetQuery;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../../Support/OmrCameraCaptureFixture.php';

test('signed production target is server-derived, material-pinned, and stable across status revisions', function (): void {
    $targetFixture = cameraCaptureTargetProductionFixture($this);
    $actor = $targetFixture['fixture']['guidance'];
    $projection = $targetFixture['projection'];
    $query = app(OmrCameraCaptureTargetQuery::class);

    $target = $query->forProjection($projection, $actor);
    $revised = OmrWorkbenchProjection::fromSafeArray([
        ...$projection->toSafeArray(),
        'revision' => $projection->revision + 1,
    ]);
    $revisedTarget = $query->forProjection($revised, $actor);

    expect($target)
        ->not->toBeNull()
        ->and($target['clientCaptureId'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and($target['pageNo'])->toBe(1)
        ->and($target['batch_id'])->toBe($targetFixture['batch']->id)
        ->and($target['template_version_id'])->toBe($targetFixture['pair']->template_version_id)
        ->and($target['booklet_version_id'])->toBe($targetFixture['pair']->booklet_version_id)
        ->and($target['targetKey'])->toBe($revisedTarget['targetKey'])
        ->and($target['clientCaptureId'])->toBe($revisedTarget['clientCaptureId'])
        ->and($target['captureRevision'])->toBe($revisedTarget['captureRevision'])
        ->and(json_decode($target['targetKey'], true))->toBe([
            $projection->workbenchId,
            $target['clientCaptureId'],
            1,
            $target['captureRevision'],
            null,
            null,
        ]);
});

test('target assertion rejects a forged page or material identity', function (): void {
    $targetFixture = cameraCaptureTargetProductionFixture($this);
    $query = app(OmrCameraCaptureTargetQuery::class);
    $actor = $targetFixture['fixture']['guidance'];
    $target = $query->forProjection($targetFixture['projection'], $actor);

    expect(fn () => $query->assertCurrentTarget($targetFixture['projection'], $actor, [
        ...$target,
        'pageNo' => 2,
    ]))->toThrow(RuntimeException::class, 'omr_camera_capture_target_mismatch');

    expect(fn () => $query->assertCurrentTarget($targetFixture['projection'], $actor, [
        ...$target,
        'template_version_id' => 999999,
    ]))->toThrow(RuntimeException::class, 'omr_camera_capture_material_mismatch');
});

test('production target query refuses a foreign actor and a non-open batch', function (): void {
    $targetFixture = cameraCaptureTargetProductionFixture($this);
    $projection = $targetFixture['projection'];
    $query = app(OmrCameraCaptureTargetQuery::class);
    $foreignActor = User::factory()->create();

    $targetFixture['batch']->forceFill(['status' => OmrCaptureBatch::STATUS_CONFIRMED])->save();

    expect($query->forProjection($projection, $foreignActor))->toBeNull()
        ->and($query->forProjection($projection, $targetFixture['fixture']['guidance']))->toBeNull();
});

test('diagnostic target normalization preserves the server-projected mock instance and capture revision', function (): void {
    $actor = User::factory()->create();
    $projection = new OmrWorkbenchProjection(
        workbenchId: 'diagnostic:camera-target',
        executionKind: OmrExecutionKind::Diagnostic,
        revision: 9,
        phase: OmrWorkbenchProjection::PHASE_CAPTURE,
        context: [
            'actor_id' => $actor->id,
            'run_id' => 44,
            'template_version_id' => 11,
            'booklet_version_id' => 12,
            'mock_manifest_sha256' => str_repeat('a', 64),
            'capture_target' => [
                'client_capture_id' => 'mock-instance-group',
                'page_no' => 2,
                'target_revision' => 7,
                'instance_id' => 'mock-instance-3',
                'page_count' => 2,
                'instance_no' => 3,
                'instance_count' => 3,
                'pending_capture_id' => 'f87ea4b0-bca3-4fa7-9c35-4c22f1c53171',
                'pending_capture_revision' => 3,
            ],
        ],
    );

    $target = app(OmrCameraCaptureTargetQuery::class)->forProjection($projection, $actor);

    expect($target['clientCaptureId'])->toBe('mock-instance-group')
        ->and($target['pageNo'])->toBe(2)
        ->and($target['captureRevision'])->toBe(7)
        ->and($target['instanceId'])->toBe('mock-instance-3')
        ->and($target['material_identity']['mock_manifest_sha256'])->toBe(str_repeat('a', 64))
        ->and($target['pending_capture_id'])->toBe('f87ea4b0-bca3-4fa7-9c35-4c22f1c53171')
        ->and($target['pending_capture_revision'])->toBe(3)
        ->and($target['instance_no'])->toBe(3)
        ->and($target['instance_count'])->toBe(3);
});
test('a completed signed group remains held until a next-set grant exists', function (): void {
    $targetFixture = cameraCaptureTargetProductionFixture($this);
    $batch = $targetFixture['batch'];
    $actor = $targetFixture['fixture']['guidance'];
    $version = $targetFixture['pair']->templateVersion;
    $batch->sheets()->create([
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'expected_page_count' => (int) $version->page_count,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 1,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => 'omr/test/camera-target-complete.png',
        'asset_hmac' => hash('sha256', 'camera-target-complete'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    expect(app(OmrCameraCaptureTargetQuery::class)->forProjection($targetFixture['projection'], $actor))->toBeNull();
});

test('next-page grant requires the exact accepted page and same next physical target', function (): void {
    $actor = User::factory()->create();
    $workbenchId = 'diagnostic:camera-next-page';
    $materialIdentity = [
        'template_version_id' => 31,
        'booklet_version_id' => 41,
        'mock_manifest_sha256' => null,
    ];
    $acceptedTarget = [
        'workbenchId' => $workbenchId,
        'clientCaptureId' => 'mock-group',
        'pageNo' => 1,
        'captureRevision' => 5,
        'instanceId' => 'mock-instance',
        'sheetId' => null,
        'run_id' => 44,
        'material_identity' => $materialIdentity,
    ];
    $acceptedTarget['targetKey'] = json_encode([
        $workbenchId, 'mock-group', 1, 5, 'mock-instance', null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $accepted = new OmrAcceptedCaptureReceipt(
        operationId: (string) Str::uuid(),
        target: $acceptedTarget,
        targetKey: $acceptedTarget['targetKey'],
        pageCount: 2,
        captureId: 12,
        pageId: 21,
        sheetId: null,
    );
    $lastPageReceipt = new OmrAcceptedCaptureReceipt(
        operationId: (string) Str::uuid(),
        target: $acceptedTarget,
        targetKey: $acceptedTarget['targetKey'],
        pageCount: 1,
        captureId: 12,
        pageId: 21,
        sheetId: null,
    );
    $projection = new OmrWorkbenchProjection(
        workbenchId: $workbenchId,
        executionKind: OmrExecutionKind::Diagnostic,
        revision: 8,
        phase: OmrWorkbenchProjection::PHASE_CAPTURE,
        context: [
            'actor_id' => $actor->id,
            'run_id' => 44,
            'template_version_id' => 31,
            'booklet_version_id' => 41,
            'capture_target' => [
                'client_capture_id' => 'mock-group',
                'page_no' => 2,
                'target_revision' => 5,
                'instance_id' => 'mock-instance',
            ],
        ],
    );

    $actions = app(OmrCameraCaptureTargetQuery::class)->projectAvailableActions(
        $projection,
        $actor,
        $accepted,
        ['next_page' => '/capture-actions'],
    );
    $wrongTarget = new OmrWorkbenchProjection(
        workbenchId: $workbenchId,
        executionKind: OmrExecutionKind::Diagnostic,
        revision: 8,
        phase: OmrWorkbenchProjection::PHASE_CAPTURE,
        context: [
            'actor_id' => $actor->id,
            'run_id' => 44,
            'template_version_id' => 31,
            'booklet_version_id' => 41,
            'capture_target' => [
                'client_capture_id' => 'another-group',
                'page_no' => 2,
                'target_revision' => 5,
                'instance_id' => 'mock-instance',
            ],
        ],
    );

    expect($actions)->toHaveKeys(['next_page'])
        ->and($actions['next_page']['acceptedTargetKey'])->toBe($accepted->targetKey)
        ->and($actions['next_page']['targetKey'])->not->toBe($accepted->targetKey)
        ->and($actions['next_page']['url'])->toBe('/capture-actions')
        ->and(app(OmrCameraCaptureTargetQuery::class)->projectAvailableActions(
            $wrongTarget,
            $actor,
            $accepted,
            ['next_page' => '/capture-actions'],
        ))->toBe([]);

    expect(app(OmrCameraCaptureTargetQuery::class)->projectAvailableActions(
        $projection,
        $actor,
        $lastPageReceipt,
        ['next_page' => '/capture-actions'],
    ))->toBe([]);
});

test('pending set target separates set identity from its stable per-page evidence identity', function (): void {
    $actor = User::factory()->create();
    $setId = (string) Str::uuid();
    $projection = new OmrWorkbenchProjection(
        workbenchId: 'diagnostic:pending-page-target',
        executionKind: OmrExecutionKind::Diagnostic,
        revision: 2,
        phase: OmrWorkbenchProjection::PHASE_CAPTURE,
        context: [
            'actor_id' => $actor->id,
            'run_id' => 17,
            'template_version_id' => 31,
            'booklet_version_id' => 41,
            'pending_capture' => [
                'id' => $setId,
                'revision' => 3,
                'status' => 'pending',
                'active_page_no' => 1,
                'page_count' => 2,
                'pages' => [
                    ['page_no' => 1, 'active' => true, 'capture_count' => 0, 'capture_id' => null],
                    ['page_no' => 2, 'active' => false, 'capture_count' => 0, 'capture_id' => null],
                ],
            ],
        ],
    );
    $query = app(OmrCameraCaptureTargetQuery::class);
    $firstPage = $query->forProjection($projection, $actor);
    $laterPageProjection = OmrWorkbenchProjection::fromSafeArray([
        ...$projection->toSafeArray(),
        'context' => [
            ...$projection->context,
            'pending_capture' => [
                ...$projection->context['pending_capture'],
                'active_page_no' => 2,
                'pages' => [
                    ['page_no' => 1, 'active' => false, 'capture_count' => 1, 'capture_id' => 13],
                    ['page_no' => 2, 'active' => true, 'capture_count' => 0, 'capture_id' => null],
                ],
            ],
        ],
    ]);
    $secondPage = $query->forProjection($laterPageProjection, $actor);

    expect($firstPage['pending_capture_id'])->toBe($setId)
        ->and($firstPage['clientCaptureId'])->not->toBe($setId)
        ->and($firstPage['clientCaptureId'])->not->toBe($secondPage['clientCaptureId'])
        ->and($firstPage['targetKey'])->not->toBe($secondPage['targetKey']);
});

test('authorized next-set and redo targets receive distinct action grants', function (): void {
    $actor = User::factory()->create();
    $workbenchId = 'diagnostic:camera-action-grants';
    $materialIdentity = [
        'template_version_id' => 31,
        'booklet_version_id' => 41,
        'mock_manifest_sha256' => null,
    ];
    $makeTarget = static function (string $clientCaptureId, int $pageNo, string $instanceId) use ($workbenchId, $materialIdentity): array {
        $target = [
            'workbenchId' => $workbenchId,
            'clientCaptureId' => $clientCaptureId,
            'pageNo' => $pageNo,
            'captureRevision' => 8,
            'instanceId' => $instanceId,
            'sheetId' => null,
            'run_id' => 44,
            'material_identity' => $materialIdentity,
        ];
        $target['targetKey'] = json_encode([
            $workbenchId, $clientCaptureId, $pageNo, 8, $instanceId, null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $target;
    };
    $acceptedTarget = $makeTarget('mock-group-2', 2, 'mock-instance-2');
    $accepted = new OmrAcceptedCaptureReceipt(
        operationId: (string) Str::uuid(),
        target: $acceptedTarget,
        targetKey: $acceptedTarget['targetKey'],
        pageCount: 2,
        captureId: 12,
        pageId: 22,
        sheetId: null,
    );
    $projection = new OmrWorkbenchProjection(
        workbenchId: $workbenchId,
        executionKind: OmrExecutionKind::Diagnostic,
        revision: 9,
        phase: OmrWorkbenchProjection::PHASE_CAPTURE,
        context: [
            'actor_id' => $actor->id,
            'run_id' => 44,
            'template_version_id' => 31,
            'booklet_version_id' => 41,
            'capture_target' => [
                'client_capture_id' => 'mock-group-2',
                'page_no' => 2,
                'target_revision' => 8,
                'instance_id' => 'mock-instance-2',
            ],
        ],
    );
    $grants = app(OmrCameraCaptureTargetQuery::class)->projectAvailableActions(
        $projection,
        $actor,
        $accepted,
        [
            'next_set' => '/capture-actions',
            'redo_last' => '/capture-actions',
        ],
        [
            'next_set' => $makeTarget('mock-group-3', 1, 'mock-instance-3'),
            'redo_last' => $makeTarget('mock-group-2', 2, 'mock-instance-2'),
        ],
    );

    expect($grants)->toHaveKeys(['next_set', 'redo_last'])
        ->and($grants['next_set']['targetKey'])->not->toBe($accepted->targetKey)
        ->and($grants['redo_last']['targetKey'])->toBe($accepted->targetKey)
        ->and($grants['next_set']['acceptedTargetKey'])->toBe($accepted->targetKey)
        ->and($grants['redo_last']['authorizationId'])->not->toBe($grants['next_set']['authorizationId']);
});

test('pending redo grant projects only while its exact accepted evidence page remains current', function (): void {
    $actor = User::factory()->create();
    $workbenchId = 'diagnostic:pending-redo-target';
    $setId = (string) Str::uuid();
    $redoCaptureId = (string) Str::uuid();
    $redoOperationId = (string) Str::uuid();
    $materialIdentity = [
        'template_version_id' => 31,
        'booklet_version_id' => 41,
        'mock_manifest_sha256' => null,
    ];
    $target = [
        'workbenchId' => $workbenchId,
        'clientCaptureId' => $redoCaptureId,
        'pageNo' => 1,
        'captureRevision' => 3,
        'instanceId' => null,
        'sheetId' => null,
        'execution_kind' => 'diagnostic',
        'run_id' => 17,
        'template_version_id' => 31,
        'booklet_version_id' => 41,
        'pending_capture_id' => $setId,
        'pending_capture_revision' => 3,
        'page_count' => 2,
        'supplier_stock_allocation_id' => null,
        'identity_mode' => null,
        'material_identity' => $materialIdentity,
        'redo_authorization_id' => (string) Str::uuid(),
        'redo_reason' => 'Page was damaged after capture.',
        'redo_operation_id' => $redoOperationId,
        'redo_source_page_id' => 13,
        'redo_source_page_revision' => 3,
        'redo_source_evidence_id' => 13,
        'redo_accepted_target_key' => json_encode([$workbenchId, 'source-capture', 1, 3, null, null], JSON_THROW_ON_ERROR),
    ];
    $target['targetKey'] = json_encode([
        $workbenchId, $redoCaptureId, 1, 3, null, null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    app(OmrCameraProtocol::class)->persistTargetGrant($actor, $workbenchId, $target);
    $pending = [
        'id' => $setId,
        'revision' => 3,
        'status' => 'pending',
        'active_page_no' => 2,
        'page_count' => 2,
        'pages' => [
            ['page_no' => 1, 'active' => false, 'capture_count' => 1, 'capture_id' => 13],
            ['page_no' => 2, 'active' => true, 'capture_count' => 0, 'capture_id' => null],
        ],
    ];
    $projection = new OmrWorkbenchProjection(
        workbenchId: $workbenchId,
        executionKind: OmrExecutionKind::Diagnostic,
        revision: 4,
        phase: OmrWorkbenchProjection::PHASE_CAPTURE,
        context: [
            'actor_id' => $actor->id,
            'run_id' => 17,
            'template_version_id' => 31,
            'booklet_version_id' => 41,
            'pending_capture' => $pending,
        ],
    );
    $query = app(OmrCameraCaptureTargetQuery::class);
    $redoTarget = $query->forProjection($projection, $actor);
    $staleProjection = OmrWorkbenchProjection::fromSafeArray([
        ...$projection->toSafeArray(),
        'context' => [
            ...$projection->context,
            'pending_capture' => [
                ...$pending,
                'pages' => [
                    ['page_no' => 1, 'active' => false, 'capture_count' => 1, 'capture_id' => 14],
                    ['page_no' => 2, 'active' => true, 'capture_count' => 0, 'capture_id' => null],
                ],
            ],
        ],
    ]);

    expect($redoTarget['redo_authorization_id'])->toBe($target['redo_authorization_id'])
        ->and($redoTarget['redo_reason'])->toBe($target['redo_reason'])
        ->and($redoTarget['redo_operation_id'])->toBe($redoOperationId)
        ->and($redoTarget['redo_source_page_id'])->toBe(13)
        ->and($redoTarget['redo_source_page_revision'])->toBe(3)
        ->and($redoTarget['redo_source_evidence_id'])->toBe(13)
        ->and(fn () => $query->assertCurrentTarget($projection, $actor, [
            ...$redoTarget,
            'redo_source_page_revision' => 4,
        ]))->toThrow(RuntimeException::class, 'omr_camera_capture_material_mismatch')
        ->and($query->forProjection($staleProjection, $actor)['pageNo'])->toBe(2);
});

test('observation target preserves the exact latest accepted capture while no capture target is available', function (): void {
    $targetFixture = cameraCaptureTargetProductionFixture($this);
    $actor = $targetFixture['fixture']['guidance'];
    $batch = $targetFixture['batch'];
    $target = app(OmrCameraCaptureTargetQuery::class)->forProjection($targetFixture['projection'], $actor);
    $sheet = $batch->sheets()->create([
        'admission_attempt_id' => null,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'expected_page_count' => (int) $targetFixture['pair']->templateVersion->page_count,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 1,
        'client_capture_id' => $target['clientCaptureId'],
        'asset_path' => 'omr/test/camera-observation-target.png',
        'asset_hmac' => hash('sha256', 'camera-observation-target'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);
    $workbenchKey = hash('sha256', $targetFixture['projection']->workbenchId);
    OmrCameraCommand::query()->create([
        'pairing_id' => null,
        'operation_id' => (string) Str::uuid(),
        'command_id' => (string) Str::uuid(),
        'issuer_role' => 'controller',
        'sequence' => null,
        'kind' => 'CAPTURE_RECEIPT',
        'workbench_key' => $workbenchKey,
        'actor_id' => $actor->id,
        'receipt_key' => hash('sha256', 'observation-receipt-'.$sheet->id),
        'payload_json' => ['workbench_id' => $targetFixture['projection']->workbenchId],
        'payload_fingerprint' => hash('sha256', 'observation-receipt-fingerprint-'.$sheet->id),
        'target_json' => $target,
        'issued_at' => now(),
        'domain_outcome' => 'accepted',
        'domain_receipt_json' => ['status' => 'accepted', 'capture_id' => $sheet->id],
        'completed_at' => now(),
    ]);

    $query = app(OmrCameraCaptureTargetQuery::class);

    expect($query->forProjection($targetFixture['projection'], $actor))->toBeNull()
        ->and($query->forObservationTarget($targetFixture['projection'], $actor)['targetKey'])->toBe($target['targetKey']);
});

test('a later Mock instance is held until its exact durable Next Set target grant exists', function (): void {
    $actor = User::factory()->create();
    $workbenchId = 'diagnostic:mock-instance-transition';
    $materialIdentity = [
        'template_version_id' => 31,
        'booklet_version_id' => 41,
        'mock_manifest_sha256' => str_repeat('b', 64),
    ];
    $previousTarget = [
        'workbenchId' => $workbenchId,
        'clientCaptureId' => (string) Str::uuid(),
        'pageNo' => 1,
        'captureRevision' => 5,
        'instanceId' => 'instance-001',
        'sheetId' => null,
        'execution_kind' => 'diagnostic',
        'run_id' => 44,
        'page_count' => 1,
        'instance_no' => 1,
        'instance_count' => 2,
        'template_version_id' => 31,
        'booklet_version_id' => 41,
        'material_identity' => $materialIdentity,
    ];
    $previousTarget['targetKey'] = json_encode([
        $workbenchId, $previousTarget['clientCaptureId'], 1, 5, 'instance-001', null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $currentTarget = [
        ...$previousTarget,
        'clientCaptureId' => (string) Str::uuid(),
        'captureRevision' => 6,
        'instanceId' => 'instance-002',
        'instance_no' => 2,
    ];
    $currentTarget['targetKey'] = json_encode([
        $workbenchId, $currentTarget['clientCaptureId'], 1, 6, 'instance-002', null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $projection = new OmrWorkbenchProjection(
        workbenchId: $workbenchId,
        executionKind: OmrExecutionKind::Diagnostic,
        revision: 6,
        phase: OmrWorkbenchProjection::PHASE_CAPTURE,
        context: [
            'actor_id' => $actor->id,
            'run_id' => 44,
            'status' => 'open',
            'diagnostic_purpose' => 'mock_rehearsal',
            'template_version_id' => 31,
            'booklet_version_id' => 41,
            'mock_manifest_sha256' => str_repeat('b', 64),
            'capture_target' => [
                'client_capture_id' => $currentTarget['clientCaptureId'],
                'page_no' => 1,
                'target_revision' => 6,
                'page_count' => 1,
                'instance_id' => 'instance-002',
                'instance_no' => 2,
                'instance_count' => 2,
            ],
        ],
    );
    OmrCameraCommand::query()->create([
        'pairing_id' => null,
        'operation_id' => (string) Str::uuid(),
        'command_id' => (string) Str::uuid(),
        'issuer_role' => 'controller',
        'sequence' => null,
        'kind' => 'CAPTURE_RECEIPT',
        'workbench_key' => hash('sha256', $workbenchId),
        'actor_id' => $actor->id,
        'receipt_key' => hash('sha256', 'mock-instance-accepted-receipt'),
        'payload_json' => ['workbench_id' => $workbenchId],
        'payload_fingerprint' => hash('sha256', 'mock-instance-accepted-fingerprint'),
        'target_json' => $previousTarget,
        'issued_at' => now(),
        'domain_outcome' => 'accepted',
        'domain_receipt_json' => ['status' => 'accepted', 'capture_id' => 12],
        'completed_at' => now(),
    ]);

    $query = app(OmrCameraCaptureTargetQuery::class);
    expect($query->forProjection($projection, $actor))->toBeNull();

    $currentTarget['target_key'] = $currentTarget['targetKey'];
    app(OmrCameraProtocol::class)->persistTargetGrant($actor, $workbenchId, $currentTarget);

    expect($query->forProjection($projection, $actor)['targetKey'])->toBe($currentTarget['targetKey']);
});

test('signed redo grant carries and asserts the source page revision independently', function (): void {
    Storage::fake('sensitive');
    $targetFixture = cameraCaptureTargetProductionFixture($this);
    $actor = $targetFixture['fixture']['guidance'];
    $batch = $targetFixture['batch'];
    $query = app(OmrCameraCaptureTargetQuery::class);
    $initialTarget = $query->forProjection($targetFixture['projection'], $actor);
    $instance = app(OmrBlankInstanceService::class)->createPrintedSet($targetFixture['pair']->templateVersion, $actor, 1, [hash('sha256', 'p1')]);
    config(['omr.fake.qr_payload' => app(OmrQrPayload::class)->sign(
        $instance->instance_code,
        1,
        1,
    )]);
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    app(UploadOmrCaptureSheet::class)->handle(
        $batch,
        (string) $bytes,
        'image/png',
        OmrCaptureSheet::MODE_AUTO,
        $initialTarget['clientCaptureId'],
        $actor,
        null,
        1,
    );
    $sheet = OmrCaptureSheet::query()->where('batch_id', $batch->id)->firstOrFail();
    $page = $sheet->pages()->where('page_no', 1)->firstOrFail();
    $operationId = (string) Str::uuid();
    OmrCameraCommand::query()->create([
        'pairing_id' => null,
        'operation_id' => $operationId,
        'command_id' => (string) Str::uuid(),
        'issuer_role' => 'controller',
        'sequence' => null,
        'kind' => 'CAPTURE_RECEIPT',
        'workbench_key' => hash('sha256', $targetFixture['projection']->workbenchId),
        'actor_id' => $actor->id,
        'receipt_key' => hash('sha256', 'signed-redo-source-'.$page->id),
        'payload_json' => ['workbench_id' => $targetFixture['projection']->workbenchId],
        'payload_fingerprint' => hash('sha256', 'signed-redo-source-fingerprint-'.$page->id),
        'target_json' => $initialTarget,
        'issued_at' => now(),
        'domain_outcome' => 'accepted',
        'domain_receipt_json' => [
            'status' => 'accepted',
            'page_id' => $page->id,
            'sheet_id' => $sheet->id,
            'page_no' => 1,
        ],
        'completed_at' => now(),
    ]);

    $redoTarget = [
        ...$initialTarget,
        'sheetId' => (int) $sheet->id,
        'captureRevision' => (int) $page->current_revision + 1,
        'redo_authorization_id' => (string) Str::uuid(),
        'redo_reason' => 'Page was damaged after capture.',
        'redo_operation_id' => $operationId,
        'redo_source_page_id' => (int) $page->id,
        'redo_source_page_revision' => (int) $page->current_revision,
        'redo_source_evidence_id' => (int) $page->id,
        'redo_accepted_target_key' => $initialTarget['targetKey'],
    ];
    $redoTarget['targetKey'] = json_encode([
        $redoTarget['workbenchId'],
        $redoTarget['clientCaptureId'],
        $redoTarget['pageNo'],
        $redoTarget['captureRevision'],
        $redoTarget['instanceId'],
        $redoTarget['sheetId'],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    app(OmrCameraProtocol::class)->persistTargetGrant($actor, $targetFixture['projection']->workbenchId, $redoTarget);
    $currentProjection = app(OmrProductionWorkbenchProjector::class)->project(
        $targetFixture['projection']->workbenchId,
        $batch->fresh(),
    );
    $target = $query->forProjection($currentProjection, $actor);

    expect($target['redo_source_page_revision'])->toBe((int) $page->current_revision)
        ->and($target['sheetId'])->toBe((int) $sheet->id)
        ->and($query->assertCurrentTarget($currentProjection, $actor, $target)['targetKey'])->toBe($redoTarget['targetKey'])
        ->and(fn () => $query->assertCurrentTarget($currentProjection, $actor, [
            ...$target,
            'redo_source_page_revision' => (int) $page->current_revision + 1,
        ]))->toThrow(RuntimeException::class, 'omr_camera_capture_material_mismatch');
});

test('signed production Next Set candidate is server-stable and requires its durable grant', function (): void {
    Storage::fake('sensitive');
    $targetFixture = cameraCaptureTargetProductionFixture($this);
    $actor = $targetFixture['fixture']['guidance'];
    $batch = $targetFixture['batch'];
    $pageCount = (int) $batch->templateVersion->page_count;
    $query = app(OmrCameraCaptureTargetQuery::class);
    $instance = app(OmrBlankInstanceService::class)->createPrintedSet($targetFixture['pair']->templateVersion, $actor, 1, [hash('sha256', 'p-next-set')]);
    config(['omr.fake.qr_payload' => app(OmrQrPayload::class)->sign(
        $instance->instance_code,
        1,
        1,
    )]);
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $sourceTarget = $query->forProjection($targetFixture['projection'], $actor);
        expect($sourceTarget['pageNo'])->toBe($pageNo);
        app(UploadOmrCaptureSheet::class)->handle(
            $batch,
            (string) $bytes,
            'image/png',
            OmrCaptureSheet::MODE_AUTO,
            $sourceTarget['clientCaptureId'],
            $actor,
            null,
            $pageNo,
        );
    }

    $sheet = OmrCaptureSheet::query()->where('batch_id', $batch->id)->firstOrFail();
    $lastPage = $sheet->pages()->where('page_no', $pageCount)->firstOrFail();
    $operationId = (string) Str::uuid();
    OmrCameraCommand::query()->create([
        'pairing_id' => null,
        'operation_id' => $operationId,
        'command_id' => (string) Str::uuid(),
        'issuer_role' => 'controller',
        'sequence' => null,
        'kind' => 'CAPTURE_RECEIPT',
        'workbench_key' => hash('sha256', $targetFixture['projection']->workbenchId),
        'actor_id' => $actor->id,
        'receipt_key' => hash('sha256', 'signed-next-set-source-'.$lastPage->id),
        'payload_json' => ['workbench_id' => $targetFixture['projection']->workbenchId],
        'payload_fingerprint' => hash('sha256', 'signed-next-set-source-fingerprint-'.$lastPage->id),
        'target_json' => $sourceTarget,
        'issued_at' => now(),
        'domain_outcome' => 'accepted',
        'domain_receipt_json' => [
            'status' => 'accepted',
            'page_id' => $lastPage->id,
            'sheet_id' => $sheet->id,
            'page_no' => $pageCount,
        ],
        'completed_at' => now(),
    ]);

    $candidate = $query->nextSetCandidate($targetFixture['projection'], $actor);
    expect($candidate)
        ->not->toBeNull()
        ->and($candidate['transition_kind'])->toBe('signed_new_group')
        ->and($candidate['pageNo'])->toBe(1)
        ->and($candidate['clientCaptureId'])->not->toBe($sourceTarget['clientCaptureId'])
        ->and($query->forProjection($targetFixture['projection'], $actor))->toBeNull();

    app(OmrCameraProtocol::class)->persistTargetGrant($actor, $targetFixture['projection']->workbenchId, $candidate);

    expect($query->forProjection($targetFixture['projection'], $actor)['targetKey'])->toBe($candidate['targetKey']);
});

test('non-Mock diagnostics project selected pinned pages without a client-owned page cursor', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    $pair = canonicalGradingBooklet($actor);
    $this->actingAs($actor)
        ->postJson(route('setup.exam-materials.omr-test.runs.start'), [
            'template_version_id' => $pair->template_version_id,
            'booklet_version_id' => $pair->booklet_version_id,
            'declared_set_code' => 'A',
        ])
        ->assertOk();
    $run = OmrDiagnosticRun::query()->latest('id')->firstOrFail();
    $projection = OmrWorkbenchProjection::fromSafeArray(
        app(OmrDiagnosticRunStatusSnapshot::class)->forRun($run, $actor),
    );
    $query = app(OmrCameraCaptureTargetQuery::class);
    $pageCount = (int) $run->templateVersion->page_count;
    $firstTarget = $query->forProjection($projection, $actor);
    $firstAgain = $query->forProjection($projection, $actor);
    $requestedPage = $pageCount > 1 ? 2 : 1;
    $selectedTarget = $query->forProjection($projection, $actor, null, $requestedPage);

    expect($firstTarget)
        ->not->toBeNull()
        ->and($firstTarget['pageNo'])->toBe(1)
        ->and($firstTarget['run_revision'])->toBe((int) $run->revision)
        ->and($firstTarget['clientCaptureId'])->toBe($firstAgain['clientCaptureId'])
        ->and($selectedTarget['pageNo'])->toBe($requestedPage)
        ->and($query->forProjection($projection, $actor, null, $pageCount + 1))->toBeNull()
        ->and($query->assertCurrentTarget($projection, $actor, $selectedTarget)['targetKey'])->toBe($selectedTarget['targetKey']);
});

test('Mock redo remains projected at a set boundary when the forward cursor is on another instance', function (): void {
    Storage::fake('sensitive');
    require_once base_path('tests/Support/TestKitHelpers.php');
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    $kit = testKitAcceptanceFixture($actor, Institution::current());
    $guide = $this->actingAs($actor)->getJson(route('setup.exam-materials.omr-test.runs.mock-guide', [
        'test_kit_version_id' => $kit['version']->id,
        'expected_revision' => $kit['version']->revision,
        'instance_count' => 2,
        'pattern_mode' => 'perfect_set_a',
    ]))->assertOk();
    $start = $this->actingAs($actor)->postJson(route('setup.exam-materials.omr-test.runs.start'), [
        'diagnostic_purpose' => 'mock_rehearsal',
        'test_kit_version_id' => $kit['version']->id,
        'expected_revision' => $kit['version']->revision,
        'instance_count' => 2,
        'pattern_mode' => 'perfect_set_a',
        'expected_mock_manifest_sha256' => $guide->json('manifest_sha256'),
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();
    $run = OmrDiagnosticRun::query()->where('public_id', $start->json('context.public_id'))->firstOrFail();
    $set = app(StartOmrPendingCaptureSet::class)
        ->forDiagnostic($run, $actor, (string) Str::uuid(), 'instance-001');
    $sourceCaptureId = (string) Str::uuid();
    $evidence = OmrPendingCapturePageEvidence::query()->create([
        'set_id' => $set->id,
        'page_no' => 1,
        'client_capture_id' => $sourceCaptureId,
        'input_hash' => hash('sha256', 'mock-redo-source'),
        'asset_path' => 'omr/pending/mock-redo-source.bin',
        'mime_type' => 'image/png',
        'byte_count' => 16,
        'captured_by_user_id' => $actor->id,
        'replacement_reason' => null,
        'supersedes_id' => null,
        'crop_policy' => 'auto_document_quad',
    ]);
    $workbenchId = 'diagnostic:'.$run->public_id;
    $materialIdentity = [
        'template_version_id' => (int) $run->template_version_id,
        'booklet_version_id' => (int) $run->booklet_version_id,
        'mock_manifest_sha256' => (string) $run->mock_manifest_sha256,
    ];
    $acceptedTarget = [
        'workbenchId' => $workbenchId,
        'clientCaptureId' => $sourceCaptureId,
        'pageNo' => 1,
        'captureRevision' => (int) $run->revision,
        'instanceId' => 'instance-001',
        'sheetId' => null,
        'execution_kind' => 'diagnostic',
        'run_id' => (int) $run->id,
        'diagnostic_purpose' => 'mock_rehearsal',
        'run_revision' => (int) $run->revision,
        'template_version_id' => (int) $run->template_version_id,
        'booklet_version_id' => (int) $run->booklet_version_id,
        'pending_capture_id' => (string) $set->public_id,
        'pending_capture_revision' => (int) $set->revision,
        'page_count' => (int) $set->page_count,
        'instance_no' => 1,
        'instance_count' => 2,
        'identity_mode' => 'shaded_grid',
        'material_identity' => $materialIdentity,
    ];
    $acceptedTarget['targetKey'] = json_encode([
        $workbenchId, $sourceCaptureId, 1, (int) $run->revision, 'instance-001', null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $acceptedOperationId = (string) Str::uuid();
    OmrCameraCommand::query()->create([
        'pairing_id' => null,
        'operation_id' => $acceptedOperationId,
        'command_id' => (string) Str::uuid(),
        'issuer_role' => 'controller',
        'sequence' => null,
        'kind' => 'CAPTURE_RECEIPT',
        'workbench_key' => hash('sha256', $workbenchId),
        'actor_id' => $actor->id,
        'receipt_key' => hash('sha256', 'mock-boundary-accepted-'.$evidence->id),
        'payload_json' => ['workbench_id' => $workbenchId],
        'payload_fingerprint' => hash('sha256', 'mock-boundary-accepted-fingerprint-'.$evidence->id),
        'target_json' => $acceptedTarget,
        'issued_at' => now(),
        'domain_outcome' => 'accepted',
        'domain_receipt_json' => [
            'status' => 'accepted',
            'page_id' => $evidence->id,
            'pending_capture_id' => $set->public_id,
            'operation_id' => $acceptedOperationId,
        ],
        'completed_at' => now(),
    ]);

    $forwardTarget = [
        'workbenchId' => $workbenchId,
        'clientCaptureId' => (string) Str::uuid(),
        'pageNo' => 1,
        'captureRevision' => (int) $run->revision,
        'instanceId' => 'instance-002',
        'sheetId' => null,
        'execution_kind' => 'diagnostic',
        'run_id' => (int) $run->id,
        'diagnostic_purpose' => 'mock_rehearsal',
        'run_revision' => (int) $run->revision,
        'template_version_id' => (int) $run->template_version_id,
        'booklet_version_id' => (int) $run->booklet_version_id,
        'pending_capture_id' => null,
        'pending_capture_revision' => null,
        'page_count' => (int) $set->page_count,
        'instance_no' => 2,
        'instance_count' => 2,
        'material_identity' => $materialIdentity,
    ];
    $forwardTarget['targetKey'] = json_encode([
        $workbenchId, $forwardTarget['clientCaptureId'], 1, (int) $run->revision, 'instance-002', null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $redoTarget = [
        ...$acceptedTarget,
        'clientCaptureId' => (string) Str::uuid(),
        'captureRevision' => (int) $run->revision + 1,
        'redo_authorization_id' => (string) Str::uuid(),
        'redo_reason' => 'Retake the accepted first instance page.',
        'redo_operation_id' => $acceptedOperationId,
        'redo_source_page_id' => (int) $evidence->id,
        'redo_source_evidence_id' => (int) $evidence->id,
        'redo_source_page_revision' => (int) $set->revision,
        'redo_accepted_target_key' => $acceptedTarget['targetKey'],
    ];
    $redoTarget['targetKey'] = json_encode([
        $workbenchId, $redoTarget['clientCaptureId'], 1, $redoTarget['captureRevision'], 'instance-001', null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    app(OmrCameraProtocol::class)->persistTargetGrant($actor, $workbenchId, $redoTarget);

    $projection = new OmrWorkbenchProjection(
        workbenchId: $workbenchId,
        executionKind: OmrExecutionKind::Diagnostic,
        revision: (int) $run->revision,
        phase: OmrWorkbenchProjection::PHASE_CAPTURE,
        context: [
            'actor_id' => $actor->id,
            'run_id' => (int) $run->id,
            'public_id' => (string) $run->public_id,
            'status' => OmrDiagnosticRun::STATUS_OPEN,
            'diagnostic_purpose' => OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL,
            'mock_manifest_sha256' => $run->mock_manifest_sha256,
            'template_version_id' => (int) $run->template_version_id,
            'booklet_version_id' => (int) $run->booklet_version_id,
            'capture_target' => $forwardTarget,
            'pending_capture' => null,
        ],
    );
    $result = app(OmrCameraCaptureTargetQuery::class)->forProjection($projection, $actor);
    expect($result)->not->toBeNull();
    expect($result['targetKey'])->toBe($redoTarget['targetKey']);
});

test('assisted signed phone capture finalizes through HTTP and authorizes the next set', function (): void {
    Storage::fake('sensitive');
    Queue::fake();
    $targetFixture = cameraCaptureTargetProductionFixture($this);
    $actor = $targetFixture['fixture']['guidance'];
    $batch = $targetFixture['batch'];
    $this->actingAs($actor);
    $routeParameters = ['examSession' => $targetFixture['fixture']['grading']->exam_session_id, 'batch' => $batch->id];
    config(['omr-camera.remote_enabled' => false]);
    $localOnly = $this->getJson(route('scoring.sessions.omr.batches.workbench', $routeParameters))->assertOk()
        ->assertJsonPath('context.camera.available', true)
        ->assertJsonPath('context.camera.pairing_available', false);
    expect($localOnly->json('context.capture_target.target_key'))->toBeString();
    $this->postJson(route('grading.omr.sensor.pair.create'), [
        'workbench_id' => $targetFixture['projection']->workbenchId,
    ])->assertServiceUnavailable();
    config(['omr-camera.remote_enabled' => true]);
    $instance = app(OmrBlankInstanceService::class)
        ->createPrintedSet($targetFixture['pair']->templateVersion, $actor, 1, [hash('sha256', 'phone-page')]);
    config(['omr.fake.qr_payload' => app(OmrQrPayload::class)->sign($instance->instance_code, 1, 1)]);
    $pair = $this->postJson(route('grading.omr.sensor.pair.create'), [
        'workbench_id' => $targetFixture['projection']->workbenchId,
    ])->assertCreated();
    $joined = $this->postJson(route('grading.omr.sensor.pair.join'), [
        'pair_code' => $pair->json('pair_code'),
    ])->assertOk();
    $controllerHeaders = [
        'X-Omr-Camera-Role' => 'controller',
        'X-Omr-Camera-Token' => $pair->json('controller_token'),
        'X-Omr-Camera-Generation' => (string) $pair->json('controller_generation'),
    ];
    $nodeHeaders = [
        'X-Omr-Camera-Role' => 'node',
        'X-Omr-Camera-Token' => $joined->json('node_token'),
        'X-Omr-Camera-Generation' => (string) $joined->json('node_generation'),
    ];
    $target = array_intersect_key($pair->json('target'), array_flip([
        'workbenchId', 'clientCaptureId', 'pageNo', 'captureRevision', 'instanceId', 'sheetId', 'targetKey',
    ]));
    $sourceGeneration = $joined->json('source_generation');
    $configRevision = $joined->json('config_revision');
    $this->withHeaders($controllerHeaders)->postJson(route('grading.omr.sensor.pair.heartbeat', $pair->json('pairing_id')), [
        'protocol_version' => config('omr-camera.protocol_version'),
        'ready' => true, 'sequence' => 1, 'source_generation' => $sourceGeneration, 'target_key' => $target['targetKey'],
    ])->assertOk();
    $commandId = (string) Str::uuid();
    $operationId = (string) Str::uuid();
    $this->withHeaders($controllerHeaders)->postJson(route('grading.omr.sensor.pair.command', $pair->json('pairing_id')), [
        'protocol_version' => config('omr-camera.protocol_version'),
        'command' => 'CAPTURE', 'command_id' => $commandId, 'operation_id' => $operationId,
        'source_generation' => $sourceGeneration, 'config_revision' => $configRevision, 'target' => $target, 'trigger' => 'shutter',
    ])->assertAccepted();
    $original = UploadedFile::fake()->image('phone-original.png', 100, 100);
    $bytes = (string) $original->get();
    $acquired = $this->withHeaders($nodeHeaders)->postJson(route('grading.omr.sensor.pair.ack', $pair->json('pairing_id')), [
        'protocol_version' => config('omr-camera.protocol_version'), 'command_id' => $commandId, 'status' => 'acquired',
        'input_hash' => hash('sha256', $bytes), 'mime' => 'image/png', 'byte_count' => strlen($bytes),
        'width' => 100, 'height' => 100, 'orientation' => 1, 'target_key' => $target['targetKey'],
        'source_generation' => $sourceGeneration, 'config_revision' => $configRevision,
    ])->assertOk();
    $this->withHeaders([...$nodeHeaders, 'Accept' => 'application/json'])->post(
        route('grading.omr.sensor.pair.frames.store', $pair->json('pairing_id')),
        ['command_id' => $commandId, 'frame_id' => $acquired->json('frame_id'), 'target_key' => $target['targetKey'], 'frame' => $original],
    )->assertCreated();
    $routeParameters = ['examSession' => $targetFixture['fixture']['grading']->exam_session_id, 'batch' => $batch->id];
    $show = $this->withHeaders($controllerHeaders)->getJson(route('scoring.sessions.omr.batches.workbench', $routeParameters))->assertOk();
    $payload = [
        'expected_revision' => $show->json('revision'),
        'operation_id' => $operationId, 'idempotency_key' => (string) Str::uuid(),
        'client_capture_id' => $target['clientCaptureId'], 'page_no' => 1, 'target_revision' => $target['captureRevision'],
        'target_key' => $target['targetKey'], 'capture_mode' => 'manual',
        'source_generation' => $sourceGeneration, 'config_revision' => $configRevision,
        'pairing_id' => $pair->json('pairing_id'), 'camera_command_id' => $commandId, 'staged_frame_id' => $acquired->json('frame_id'),
    ];
    $captureUrl = route('scoring.sessions.omr.batches.workbench.capture_start', $routeParameters);
    $accepted = $this->withHeaders($controllerHeaders)->postJson($captureUrl, $payload)->assertOk()
        ->assertJsonPath('context.capture_receipt.status', 'accepted')
        ->assertJsonPath('context.capture_receipt.operation_id', $operationId);
    $sheet = OmrCaptureSheet::query()->where('batch_id', $batch->id)->sole();
    expect($accepted->json('context.capture_receipt.sheet_id'))->toBe($sheet->id)
        ->and($accepted->json('context.capture_actions.next_set'))->toBeArray()
        ->and($accepted->json('context.capture_actions.next_page'))->toBeNull()
        ->and(app(SensitiveAssetStore::class)->read($sheet->asset_path))->toBe($bytes);
    $this->withHeaders($controllerHeaders)->postJson($captureUrl, $payload)->assertOk()
        ->assertJsonPath('context.capture_receipt.capture_id', $sheet->id);
    expect(OmrCaptureSheet::query()->where('batch_id', $batch->id)->count())->toBe(1);
});

test('assisted target grant replay ignores presentation aliases but rejects changed authority', function (): void {
    $fixture = cameraCaptureTargetProductionFixture($this);
    $actor = $fixture['fixture']['guidance'];
    $target = app(OmrCameraCaptureTargetQuery::class)->forProjection($fixture['projection'], $actor);
    $protocol = app(OmrCameraProtocol::class);
    $grant = $protocol->persistTargetGrant($actor, $fixture['projection']->workbenchId, $target);
    $equivalent = array_reverse($target, true);
    unset($equivalent['target_key'], $equivalent['page_no'], $equivalent['capture_revision'], $equivalent['client_capture_id'], $equivalent['sheet_id'], $equivalent['instance_id']);
    $equivalent['source_page_id'] = 999;
    expect($protocol->persistTargetGrant($actor, $fixture['projection']->workbenchId, $equivalent)->id)->toBe($grant->id);
    $changed = $equivalent;
    $changed['material_identity']['template_version_id']++;
    expect(fn () => $protocol->persistTargetGrant($actor, $fixture['projection']->workbenchId, $changed))
        ->toThrow(HttpException::class);
});

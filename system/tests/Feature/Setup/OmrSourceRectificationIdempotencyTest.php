<?php

use App\Assessment\Actions\Omr\Canonical\RectifyOmrSourcePage;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('local');
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);
    Http::preventStrayRequests();
});

function omrSourceRectificationPayload(OmrTemplateAuthoringDraft $draft, UploadedFile $source, int $lockVersion, array $points, int $pageNo = 1): array
{
    return [
        'draft_id' => $draft->id,
        'page_no' => $pageNo,
        'source_sha256' => hash('sha256', (string) $source->get()),
        'points' => $points,
        'target_width' => 600,
        'target_height' => 850,
        'expected_lock_version' => $lockVersion,
    ];
}
function omrSourceRectificationActor(): User
{
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    return $actor;
}

function omrSourceRectificationFixture(User $actor, string $code, int $requestedPageCount = 1): OmrTemplateAuthoringDraft
{
    return app(OmrAuthoringDraftService::class)->create(
        actor: $actor,
        code: $code,
        name: 'Rectification '.$code,
        sourceKind: 'image',
        authoring: [],
        registrationMode: OmrRegistrationMode::DocumentBound,
        qrMode: OmrQrMode::Disabled,
        requestedPageCount: $requestedPageCount,
    );
}
function omrSourceRectificationFakeWorker(): void
{
    Http::fake(function (Request $request) {
        $runId = (string) ($request->header('X-Nexiam-Run-Id')[0] ?? '');
        $rectified = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $result = json_encode(['run_id' => $runId, 'quality' => ['status' => 'pass']], JSON_THROW_ON_ERROR);
        $resultHash = hash('sha256', $result);
        $rectifiedHash = hash('sha256', $rectified);
        $timestamp = (string) time();
        $signature = hash_hmac(
            'sha256',
            implode("\n", [$timestamp, $runId, $resultHash, $rectifiedHash]),
            (string) config('omr.omrchecker.signing_secret'),
        );
        $boundary = 'omr-source-rectification-test';
        $body = '--'.$boundary."\r\nContent-Disposition: form-data; name=\"result\"\r\n\r\n".$result
            ."\r\n--".$boundary."\r\nContent-Disposition: form-data; name=\"rectified_image\"; filename=\"rectified.png\"\r\nContent-Type: image/png\r\n\r\n".$rectified
            ."\r\n--".$boundary."--\r\n";

        return Http::response($body, 200, [
            'Content-Type' => 'multipart/form-data; boundary='.$boundary,
            'X-Nexiam-Result-SHA256' => $resultHash,
            'X-Nexiam-Rectified-SHA256' => $rectifiedHash,
            'X-Nexiam-Timestamp' => $timestamp,
            'X-Nexiam-Run-Id' => $runId,
            'X-Nexiam-Signature' => $signature,
        ]);
    });
}

test('completed source rectification replays by matching payload before compiled-stage and page gates', function (): void {
    $actor = omrSourceRectificationActor();
    $draft = omrSourceRectificationFixture($actor, 'REPLAY-RECTIFICATION');
    $draft->forceFill(['requested_page_count' => 2])->save();
    $draft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
        'requested_page_count' => 1,
        'lock_version' => 1,
    ])->save();
    $source = UploadedFile::fake()->createWithContent(
        'page.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
    );
    $points = [[40, 40], [560, 40], [560, 810], [40, 810]];
    $payload = omrSourceRectificationPayload($draft, $source, 0, $points, 2);
    $operation = Operation::query()->create([
        'idempotency_key' => $actor->id.':omr.canonical.source_rectify:'.$draft->id.':rectify-once',
        'type' => 'omr.canonical.source_rectify',
        'status' => Operation::STATUS_SUCCEEDED,
        'attempts' => 0,
        'payload' => ['request_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), ...$payload],
        'finished_at' => now(),
    ]);

    $replayed = app(RectifyOmrSourcePage::class)->handle(
        $draft,
        $actor,
        $source,
        $points,
        600,
        850,
        0,
        'rectify-once',
        2,
    );

    expect($replayed->is($operation))->toBeTrue()
        ->and(OmrTemplateAsset::query()->where('authoring_draft_id', $draft->id)->count())->toBe(0);
});

test('reusing a completed rectification key with a changed payload conflicts without invoking the worker', function (): void {
    $actor = omrSourceRectificationActor();
    $draft = omrSourceRectificationFixture($actor, 'CONFLICT-RECTIFICATION');
    $source = UploadedFile::fake()->createWithContent(
        'page.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
    );
    $points = [[40, 40], [560, 40], [560, 810], [40, 810]];
    $payload = omrSourceRectificationPayload($draft, $source, 0, $points);
    Operation::query()->create([
        'idempotency_key' => $actor->id.':omr.canonical.source_rectify:'.$draft->id.':rectify-once',
        'type' => 'omr.canonical.source_rectify',
        'status' => Operation::STATUS_SUCCEEDED,
        'attempts' => 0,
        'payload' => ['request_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), ...$payload],
        'finished_at' => now(),
    ]);

    expect(fn () => app(RectifyOmrSourcePage::class)->handle(
        $draft,
        $actor,
        $source,
        [[45, 45], [555, 45], [555, 805], [45, 805]],
        600,
        850,
        0,
        'rectify-once',
    ))->toThrow(OmrAuthoringDraftException::class, 'idempotency_conflict');
});

test('stale draft lock version is rejected before source rectification worker dispatch', function (): void {
    $actor = omrSourceRectificationActor();
    $draft = omrSourceRectificationFixture($actor, 'STALE-RECTIFICATION');
    $draft->forceFill(['lock_version' => 2])->save();
    $source = UploadedFile::fake()->createWithContent(
        'page.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
    );

    expect(fn () => app(RectifyOmrSourcePage::class)->handle(
        $draft,
        $actor,
        $source,
        [[40, 40], [560, 40], [560, 810], [40, 810]],
        600,
        850,
        1,
        'stale-rectify',
    ))->toThrow(OmrAuthoringDraftException::class, 'concurrency_conflict');
});
test('controller replay for a removed page never returns another page preview', function (): void {
    $actor = omrSourceRectificationActor();
    $draft = omrSourceRectificationFixture($actor, 'REMOVED-PAGE-REPLAY', 2);
    $sessionVersion = (int) StaffAuthenticationState::query()->where('user_id', $actor->id)->value('session_version');
    $staffSession = [
        'auth.surface' => 'staff',
        'auth.session_version' => $sessionVersion,
    ];
    $points = [[40, 40], [560, 40], [560, 810], [40, 810]];
    $sourceBytes = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $url = route('setup.omr.templates.canonical.drafts.source.rectify', $draft);
    omrSourceRectificationFakeWorker();

    $pageOneResponse = $this->actingAs($actor)->withSession($staffSession)->post($url, [
        'source' => UploadedFile::fake()->createWithContent('page.png', $sourceBytes),
        'points' => $points,
        'target_width' => 600,
        'target_height' => 850,
        'lock_version' => 0,
        'idempotency_key' => 'removed-page-replay-p1',
        'page_no' => 1,
    ]);
    $pageOneResponse->assertAccepted()->assertJsonPath('page_no', 1);

    $pageTwoPayload = [
        'source' => UploadedFile::fake()->createWithContent('page.png', $sourceBytes),
        'points' => $points,
        'target_width' => 600,
        'target_height' => 850,
        'lock_version' => $pageOneResponse->json('lock_version'),
        'idempotency_key' => 'removed-page-replay-p2',
        'page_no' => 2,
    ];
    $pageTwoResponse = $this->actingAs($actor)->withSession($staffSession)->post($url, $pageTwoPayload);
    $pageTwoResponse->assertAccepted()
        ->assertJsonPath('page_no', 2)
        ->assertJsonPath('preview_url', route('setup.omr.templates.canonical.drafts.assets.show', [
            $draft,
            collect($draft->fresh()->authoring_json['pages'])->firstWhere('page_no', 2)['asset_id'],
        ]));

    $this->actingAs($actor)->withSession($staffSession)
        ->putJson(route('setup.omr.templates.canonical.drafts.pages.update', $draft), [
            'operation' => 'remove',
            'page_no' => 2,
            'lock_version' => $pageTwoResponse->json('lock_version'),
        ])
        ->assertOk()
        ->assertJsonPath('requested_page_count', 1);

    $replay = $this->actingAs($actor)->withSession($staffSession)->post($url, [
        ...$pageTwoPayload,
        'source' => UploadedFile::fake()->createWithContent('page.png', $sourceBytes),
    ]);

    $replay->assertAccepted()
        ->assertJsonPath('status', 'succeeded')
        ->assertJsonPath('operation_id', $pageTwoResponse->json('operation_id'))
        ->assertJsonPath('page_no', 2)
        ->assertJsonPath('preview_url', null)
        ->assertJsonPath('pages_rectified', 1)
        ->assertJsonPath('pages.0.page_no', 1);

    $operationKey = $actor->id.':omr.canonical.source_rectify:'.$draft->id.':removed-page-replay-p2';
    expect(Operation::query()->where('idempotency_key', $operationKey)->count())->toBe(1);
    Http::assertSentCount(2);
});

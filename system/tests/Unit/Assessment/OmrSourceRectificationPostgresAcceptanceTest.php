<?php

use App\Assessment\Actions\Omr\Canonical\RectifyOmrSourcePage;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function omrSourceRectificationPgAssertSafeEnvironment(object $test): void
{
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $test->markTestSkipped('Rectification concurrency acceptance requires PostgreSQL.');
    }
    if (! function_exists('pcntl_fork')) {
        $test->markTestSkipped('Rectification concurrency acceptance requires process fork support.');
    }
    if (! preg_match('/^nexiam_omr_acceptance_[a-z0-9_]+$/', (string) DB::connection()->getDatabaseName())) {
        $test->fail('Refusing concurrent rectification acceptance outside a disposable nexiam_omr_acceptance_* database.');
    }
}

function omrSourceRectificationPgChild(
    int $draftId,
    int $actorId,
    string $sourcePath,
    string $workerCallsPath,
    string $resultPath,
): void {
    try {
        DB::purge();
        DB::reconnect();
        config(['cache.default' => 'database']);
        Cache::forgetDriver('database');
        Http::fake(function (Request $request) use ($workerCallsPath) {
            file_put_contents($workerCallsPath, 'worker:'.DB::transactionLevel()."\n", FILE_APPEND | LOCK_EX);
            usleep(700_000);
            $runId = (string) ($request->header('X-Nexiam-Run-Id')[0] ?? '');
            $secret = (string) config('omr.omrchecker.signing_secret');
            $rectified = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
            $result = json_encode(['run_id' => $runId, 'quality' => ['status' => 'pass']], JSON_THROW_ON_ERROR);
            $resultHash = hash('sha256', $result);
            $rectifiedHash = hash('sha256', (string) $rectified);
            $timestamp = (string) time();
            $signature = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $resultHash, $rectifiedHash]), $secret);
            $boundary = 'omr-source-rectify-acceptance';
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
        $actor = User::query()->findOrFail($actorId);
        $draft = OmrTemplateAuthoringDraft::query()->findOrFail($draftId);
        $source = new UploadedFile($sourcePath, 'rectification.png', 'image/png', null, true);
        app(RectifyOmrSourcePage::class)->handle(
            $draft,
            $actor,
            $source,
            [[40, 40], [560, 40], [560, 810], [40, 810]],
            600,
            850,
            0,
            'postgres-concurrent-rectification',
        );
        file_put_contents($resultPath, 'succeeded');
    } catch (Throwable $exception) {
        file_put_contents($resultPath, 'failed:'.$exception::class.':'.$exception->getMessage());
    }
}

test('concurrent PostgreSQL source rectification dispatches one worker and commits one page receipt', function (): void {
    omrSourceRectificationPgAssertSafeEnvironment($this);
    config([
        'cache.default' => 'database',
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        'omr.omrchecker.endpoint' => 'http://127.0.0.1:8100',
        'omr.canonical.limits.conversion_timeout_seconds_per_page' => 5,
    ]);
    Cache::forgetDriver('database');
    $storageRoot = sys_get_temp_dir().'/nexiam-omr-rectification-'.bin2hex(random_bytes(8));
    if (! mkdir($storageRoot, 0700, true)) {
        $this->fail('Could not create isolated PostgreSQL OMR asset storage.');
    }
    config(['filesystems.disks.local.root' => $storageRoot]);
    Storage::forgetDisk('local');
    Storage::disk('local');
    $temporaryFiles = [];
    $children = [];
    $reapedChildren = [];
    try {

        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
        $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
        $draft = app(OmrAuthoringDraftService::class)->create(
            actor: $actor,
            code: 'PG-RECTIFY-'.strtoupper(bin2hex(random_bytes(4))),
            name: 'PostgreSQL concurrent rectification',
            sourceKind: 'image',
            authoring: [],
            registrationMode: OmrRegistrationMode::DocumentBound,
            qrMode: OmrQrMode::Disabled,
            requestedPageCount: 1,
        );
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $sourcePath = tempnam(sys_get_temp_dir(), 'omr-rectification-pg-');
        $workerCallsPath = tempnam(sys_get_temp_dir(), 'omr-rectification-worker-');
        $resultPaths = [tempnam(sys_get_temp_dir(), 'omr-rectification-result-'), tempnam(sys_get_temp_dir(), 'omr-rectification-result-')];
        $temporaryFiles = array_values(array_filter([$sourcePath, $workerCallsPath, ...$resultPaths], 'is_string'));
        if ($sourcePath === false || $workerCallsPath === false || in_array(false, $resultPaths, true)) {
            $this->fail('Could not create PostgreSQL rectification acceptance files.');
        }
        file_put_contents($sourcePath, (string) $png);
        file_put_contents($workerCallsPath, '');
        Cache::forgetDriver('database');
        DB::purge();

        foreach ($resultPaths as $index => $resultPath) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Could not fork a PostgreSQL rectification request.');
            }
            if ($pid === 0) {
                omrSourceRectificationPgChild((int) $draft->id, (int) $actor->id, $sourcePath, $workerCallsPath, $resultPath);
                exit(0);
            }
            $children[] = $pid;
            if ($index === 0) {
                usleep(100_000);
            }
        }

        $childExitCodes = [];
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $reapedChildren[] = $pid;
            $childExitCodes[] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
        }
        DB::reconnect();

        $results = array_map(static fn (string $path): string => (string) file_get_contents($path), $resultPaths);
        $workerDispatches = file($workerCallsPath, FILE_IGNORE_NEW_LINES) ?: [];
        $operationKey = $actor->id.':omr.canonical.source_rectify:'.$draft->id.':postgres-concurrent-rectification';
        $saved = $draft->fresh();
        $auditCount = AuditEvent::query()
            ->where('event_type', 'omr.canonical.source_rectified')
            ->where('payload->draft_id', (string) $draft->id)
            ->count();

        expect($childExitCodes)->toBe([0, 0])
            ->and($results)->toBe(['succeeded', 'succeeded'])
            ->and($workerDispatches)->toBe(['worker:0'])
            ->and(Operation::query()->where('idempotency_key', $operationKey)->count())->toBe(1)
            ->and(OmrTemplateAsset::query()->where('authoring_draft_id', $draft->id)->count())->toBe(2)
            ->and($saved->authoring_json['pages'])->toHaveCount(1)
            ->and($saved->authoring_json['pages'][0]['page_no'])->toBe(1)
            ->and($auditCount)->toBe(1);
    } finally {
        foreach (array_diff($children, $reapedChildren) as $pid) {
            pcntl_waitpid($pid, $status);
        }
        foreach ($temporaryFiles as $path) {
            @unlink($path);
        }
        File::deleteDirectory($storageRoot);
    }
});

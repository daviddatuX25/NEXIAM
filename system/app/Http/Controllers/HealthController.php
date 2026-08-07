<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseStatus(),
            'private_storage' => $this->privateStorageStatus(),
            'mail' => $this->mailStatus(),
            'queue' => $this->queueStatus(),
        ];

        $coreFailed = in_array('unavailable', [$checks['database'], $checks['private_storage']], true);
        $status = $coreFailed ? 'degraded' : 'ok';

        return response()->json([
            'status' => $status,
            ...$checks,
        ], $coreFailed ? 503 : 200);
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('select 1');

            return 'ok';
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function privateStorageStatus(): string
    {
        try {
            $path = 'foundation/health.txt';
            Storage::disk('local')->put($path, 'ok');
            $ok = Storage::disk('local')->get($path) === 'ok';

            return $ok ? 'ok' : 'unavailable';
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function mailStatus(): string
    {
        $host = (string) config('mail.mailers.smtp.host');

        if ($host === '') {
            return 'unconfigured';
        }

        // Mailpit reachability is proven separately in evidence; readiness only
        // confirms the test mailer is configured for the local seam.
        return config('mail.default') === 'smtp' ? 'configured' : 'unconfigured';
    }

    private function queueStatus(): string
    {
        return config('queue.default') === 'database'
            && (bool) config('queue.connections.database.after_commit')
            ? 'configured_after_commit'
            : 'misconfigured';
    }
}

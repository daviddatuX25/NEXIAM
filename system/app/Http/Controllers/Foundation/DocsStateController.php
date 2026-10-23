<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\StoreDocsStateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Dev-only docs workspace state (client brief decisions, task-done flags).
 * Backed by the separate SQLite `docs` connection so the system database
 * is never touched. Routes are registered only in the local environment.
 */
final class DocsStateController extends Controller
{
    private const PAGES = ['client-brief'];

    public function index(Request $request): JsonResponse
    {
        $page = $request->validate([
            'page' => ['sometimes', 'string', Rule::in(self::PAGES)],
        ])['page'] ?? 'client-brief';

        $state = DB::connection('docs')
            ->table('docs_state')
            ->where('page', $page)
            ->pluck('value', 'key')
            ->map(fn (?string $value) => $value)
            ->all();

        return response()->json(['page' => $page, 'state' => $state]);
    }

    public function store(StoreDocsStateRequest $request): JsonResponse
    {
        $page = $request->validated('page');
        $now = now();

        DB::connection('docs')->transaction(function () use ($request, $page, $now) {
            foreach ($request->validated('entries') as $entry) {
                if ($entry['value'] === null || $entry['value'] === '') {
                    DB::connection('docs')
                        ->table('docs_state')
                        ->where('page', $page)
                        ->where('key', $entry['key'])
                        ->delete();

                    continue;
                }

                DB::connection('docs')
                    ->table('docs_state')
                    ->upsert(
                        [
                            'page' => $page,
                            'key' => $entry['key'],
                            'value' => $entry['value'],
                            'updated_at' => $now,
                        ],
                        ['page', 'key'],
                        ['value', 'updated_at'],
                    );
            }
        });

        return response()->json(['saved' => count($request->validated('entries'))]);
    }
}

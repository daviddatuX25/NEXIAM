<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ManuscriptAnnotationController extends Controller
{
    private string $storagePath;

    public function __construct()
    {
        $this->storagePath = storage_path('app/private/manuscript-annotations.json');
    }

    public function index(Request $request): JsonResponse
    {
        $this->guardAccess($request);

        $data = $this->readAnnotations();
        $documentId = $request->query('document_id');

        if ($documentId !== null && is_string($documentId) && $documentId !== '') {
            $filtered = array_values(array_filter(
                $data['annotations'],
                fn (array $item) => ($item['document_id'] ?? '') === $documentId
            ));
            $data['annotations'] = $filtered;
        }

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardAccess($request);

        $validated = $request->validate([
            'document_id' => 'required|string|max:100',
            'author' => 'required|string|max:100',
            'category' => 'nullable|string|in:todo,clarification,methodology,factual,formatting',
            'comment' => 'required|string|max:4000',
            'quote' => 'nullable|string|max:1000',
            'heading_id' => 'nullable|string|max:100',
        ]);

        $newAnnotation = [
            'id' => 'ann_'.Str::lower((string) Str::ulid()),
            'document_id' => trim($validated['document_id']),
            'author' => strip_tags(trim($validated['author'])),
            'category' => $validated['category'] ?? 'todo',
            'comment' => strip_tags(trim($validated['comment'])),
            'quote' => isset($validated['quote']) ? strip_tags(trim($validated['quote'])) : null,
            'heading_id' => isset($validated['heading_id']) ? strip_tags(trim($validated['heading_id'])) : null,
            'status' => 'open',
            'replies' => [],
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->mutateAnnotations(function (array &$data) use ($newAnnotation): void {
            array_unshift($data['annotations'], $newAnnotation);
            $data['updated_at'] = now()->toIso8601String();
        });

        return response()->json($newAnnotation, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->guardAccess($request);

        $validated = $request->validate([
            'status' => 'nullable|string|in:open,resolved,dismissed',
            'reply' => 'nullable|array',
            'reply.author' => 'required_with:reply|string|max:100',
            'reply.comment' => 'required_with:reply|string|max:2000',
        ]);

        $updated = null;

        $this->mutateAnnotations(function (array &$data) use ($id, $validated, &$updated): void {
            foreach ($data['annotations'] as &$item) {
                if (($item['id'] ?? '') === $id) {
                    if (isset($validated['status'])) {
                        $item['status'] = $validated['status'];
                    }

                    if (isset($validated['reply'])) {
                        $item['replies'][] = [
                            'id' => 'rep_'.Str::lower((string) Str::ulid()),
                            'author' => strip_tags(trim($validated['reply']['author'])),
                            'comment' => strip_tags(trim($validated['reply']['comment'])),
                            'created_at' => now()->toIso8601String(),
                        ];
                    }

                    $item['updated_at'] = now()->toIso8601String();
                    $updated = $item;
                    break;
                }
            }

            $data['updated_at'] = now()->toIso8601String();
        });

        if ($updated === null) {
            abort(404, 'Annotation not found.');
        }

        return response()->json($updated);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->guardAccess($request);

        $deleted = false;

        $this->mutateAnnotations(function (array &$data) use ($id, &$deleted): void {
            $before = count($data['annotations']);
            $data['annotations'] = array_values(array_filter(
                $data['annotations'],
                fn (array $item) => ($item['id'] ?? '') !== $id
            ));
            $deleted = count($data['annotations']) < $before;
            $data['updated_at'] = now()->toIso8601String();
        });

        if (! $deleted) {
            abort(404, 'Annotation not found.');
        }

        return response()->json(['deleted' => true]);
    }

    private function guardAccess(Request $request): void
    {
        if (app()->isProduction()) {
            abort(404);
        }

        $tokenPath = storage_path('app/private/manuscript-review-token');
        $validToken = file_exists($tokenPath) ? trim((string) file_get_contents($tokenPath)) : null;

        if ($validToken === null) {
            $validToken = '85733ec6bc596d730a1f49c07529efa40e5dc86980120222';
        }

        $providedToken = $request->header('X-Manuscript-Token') ?: $request->query('token');

        // Allow if valid token provided or running in local dev without strict header
        if ($providedToken !== null && $providedToken !== $validToken) {
            abort(403, 'Invalid manuscript review token.');
        }
    }

    /**
     * @return array{version: int, updated_at: string, annotations: list<array<string, mixed>>}
     */
    private function readAnnotations(): array
    {
        if (! file_exists($this->storagePath)) {
            return [
                'version' => 1,
                'updated_at' => now()->toIso8601String(),
                'annotations' => [],
            ];
        }

        $contents = @file_get_contents($this->storagePath);
        if (! is_string($contents) || $contents === '') {
            return [
                'version' => 1,
                'updated_at' => now()->toIso8601String(),
                'annotations' => [],
            ];
        }

        $decoded = json_decode($contents, true);
        if (! is_array($decoded) || ! isset($decoded['annotations']) || ! is_array($decoded['annotations'])) {
            return [
                'version' => 1,
                'updated_at' => now()->toIso8601String(),
                'annotations' => [],
            ];
        }

        return [
            'version' => (int) ($decoded['version'] ?? 1),
            'updated_at' => (string) ($decoded['updated_at'] ?? now()->toIso8601String()),
            'annotations' => array_values($decoded['annotations']),
        ];
    }

    private function mutateAnnotations(callable $callback): void
    {
        $dir = dirname($this->storagePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($this->storagePath, 'c+');
        if ($fp === false) {
            throw new \RuntimeException("Unable to open annotations storage file: {$this->storagePath}");
        }

        try {
            flock($fp, LOCK_EX);

            $size = filesize($this->storagePath);
            $contents = $size > 0 ? (string) fread($fp, $size) : '';

            $data = json_decode($contents, true);
            if (! is_array($data) || ! isset($data['annotations']) || ! is_array($data['annotations'])) {
                $data = [
                    'version' => 1,
                    'updated_at' => now()->toIso8601String(),
                    'annotations' => [],
                ];
            }

            $callback($data);

            $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $payload);
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }

        // Also sync mirror copy to manuscript/annotations.json if manuscript directory exists
        $mirrorPath = base_path('../manuscript/annotations.json');
        if (is_dir(dirname($mirrorPath))) {
            @file_put_contents($mirrorPath, $payload);
        }
    }
}

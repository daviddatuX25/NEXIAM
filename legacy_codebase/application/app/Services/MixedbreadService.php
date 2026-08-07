<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mixedbread AI embeddings and semantic search service.
 *
 * Wraps the mixedbread API for document upload, deletion, search, and file status operations.
 */
class MixedbreadService
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.mixedbread.api_key') ?? '';
        $this->baseUrl = rtrim((string) (config('services.mixedbread.base_url') ?? 'https://api.mixedbread.com/v1'), '/');
    }

    /**
     * Upload a document to a mixedbread store.
     *
     * @param  string  $storeId  The target store identifier.
     * @param  string  $content  The document content.
     * @param  string  $title  The document title / external ID.
     * @param  array<string, mixed>  $metadata  Optional key-value metadata.
     * @return array<string, mixed> The API response decoded as an array.
     */
    public function uploadDocument(string $storeId, string $content, string $title, array $metadata = []): array
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'mxb_').'.txt';
        file_put_contents($tmpPath, $content);

        try {
            // Use the /files/upload endpoint which handles both file upload and store addition in one call
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->attach('file', file_get_contents($tmpPath), "{$title}.txt")
                ->post("{$this->baseUrl}/stores/{$storeId}/files/upload", [
                    'params' => json_encode(array_filter([
                        'external_id' => $title,
                        'metadata' => ! empty($metadata) ? $metadata : null,
                    ])),
                ]);
        } finally {
            @unlink($tmpPath);
        }

        if (! $response->successful()) {
            throw new \RuntimeException("Mixedbread upload failed: HTTP {$response->status()} — {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Delete a document from a mixedbread store.
     *
     * @param  string  $storeId  The target store identifier.
     * @param  string  $fileId  The file identifier to delete.
     */
    public function deleteDocument(string $storeId, string $fileId): void
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->delete("{$this->baseUrl}/stores/{$storeId}/files/{$fileId}");

        if ($response->status() === 404) {
            Log::debug("Mixedbread deleteDocument: file {$fileId} not found in store {$storeId}, skipping.");

            return;
        }

        if (! $response->successful()) {
            throw new \RuntimeException("Mixedbread delete failed: HTTP {$response->status()} — {$response->body()}");
        }
    }

    /**
     * Search a mixedbread store for documents matching a query.
     *
     * @param  string  $storeId  The target store identifier.
     * @param  string  $query  The search query string.
     * @param  array<string, mixed>  $filters  Optional filter conditions.
     * @param  int  $topK  Maximum number of results to return (default 3).
     * @return array<string, mixed> The array of matching results.
     */
    public function search(string $storeId, string $query, array $filters = [], int $topK = 3): array
    {
        $payload = [
            'query' => $query,
            'store_identifiers' => [$storeId],
            'top_k' => $topK,
        ];

        if (! empty($filters)) {
            $payload['filters'] = $filters;
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/stores/search", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException("Mixedbread search failed: HTTP {$response->status()} — {$response->body()}");
        }

        return $response->json('results', []);
    }

    /**
     * Get the processing status of a document in a mixedbread store.
     *
     * @param  string  $storeId  The target store identifier.
     * @param  string  $fileId  The file identifier to check.
     * @return string The file status string.
     */
    public function getFileStatus(string $storeId, string $fileId): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->get("{$this->baseUrl}/stores/{$storeId}/files/{$fileId}");

        if (! $response->successful()) {
            throw new \RuntimeException("Mixedbread getFileStatus failed: HTTP {$response->status()} — {$response->body()}");
        }

        return (string) $response->json('status', 'unknown');
    }
}

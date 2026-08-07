<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCsvImportRequest;
use App\Http\Requests\StoreKnowledgeDocumentRequest;
use App\Http\Requests\UpdateKnowledgeDocumentRequest;
use App\Models\KnowledgeDocument;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\CsvToNarrativeService;
use App\Services\MixedbreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeDocumentController extends Controller
{
    public function __construct(
        private readonly MixedbreadService $mixedbread,
    ) {}

    private function syncToMixedbread(KnowledgeDocument $doc): void
    {
        // Use env() directly to bypass config cache issues - test both paths
        $storeId = config('services.mixedbread.store_id') ?: env('MIXEDBREAD_STORE_ID', '');
        $apiKey = config('services.mixedbread.api_key') ?: env('MIXEDBREAD_API_KEY', '');

        // Debug: Log ALL configs for diagnosis
        Log::debug('Mixedbread sync attempt', [
            'doc_id' => $doc->id,
            'store_id' => $storeId,
            'api_key_first_10' => $apiKey ? substr($apiKey, 0, 10) : 'EMPTY',
        ]);

        if (empty($storeId)) {
            Log::warning('Mixedbread sync skipped: store_id config is empty', [
                'doc_id' => $doc->id,
                'api_key_set' => ! empty($apiKey),
            ]);

            return;
        }

        // Set initial pending status so we can track attempts
        $doc->update(['mxb_sync_status' => KnowledgeDocument::SYNC_PENDING]);
        try {
            $result = $this->mixedbread->uploadDocument(
                $storeId,
                $doc->content,
                $doc->title,
                array_filter((array) $doc->metadata),
            );
            $doc->update([
                'mxb_file_id' => $result['id'] ?? null,
                'mxb_sync_status' => KnowledgeDocument::SYNC_INDEXED,
                'mxb_synced_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Mixedbread sync failed', ['doc_id' => $doc->id, 'error' => $e->getMessage()]);
            $doc->update(['mxb_sync_status' => KnowledgeDocument::SYNC_FAILED]);
        }
    }

    private function deleteFromMixedbread(KnowledgeDocument $doc): void
    {
        if (empty($doc->mxb_file_id)) {
            return;
        }
        $storeId = config('services.mixedbread.store_id', '');
        if (empty($storeId)) {
            return;
        }
        try {
            $this->mixedbread->deleteDocument($storeId, $doc->mxb_file_id);
        } catch (\Throwable $e) {
            Log::warning('Mixedbread delete failed', ['doc_id' => $doc->id, 'error' => $e->getMessage()]);
        }
    }

    public function index(Request $request): Response
    {
        if (! SystemSetting::aiCompanionEnabled()) {
            abort(403, 'AI Companion is disabled.');
        }

        $this->authorize('viewAny', KnowledgeDocument::class);

        $query = KnowledgeDocument::query()->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $term = '%'.$request->get('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('content', 'like', $term);
            });
        }

        $documents = $query->paginate(15)->withQueryString()->through(function (KnowledgeDocument $doc) {
            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'content' => Str::limit($doc->content, 120),
                'metadata' => $doc->metadata,
                'metadata_summary' => $doc->metadata_summary,
                'source' => $doc->source,
                'is_active' => $doc->is_active,
                'mxb_sync_status' => $doc->mxb_sync_status,
                'mxb_file_id' => $doc->mxb_file_id,
                'created_at' => $doc->created_at?->toIso8601String(),
                'updated_at' => $doc->updated_at?->toIso8601String(),
            ];
        });

        return Inertia::render('Admin/KnowledgeDocuments/Index', [
            'documents' => $documents,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', KnowledgeDocument::class);

        return Inertia::render('Admin/KnowledgeDocuments/Create');
    }

    public function store(StoreKnowledgeDocumentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $doc = KnowledgeDocument::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'metadata' => $validated['metadata'] ?? [],
            'source' => $validated['source'] ?? KnowledgeDocument::SOURCE_MANUAL,
            'is_active' => true,
        ]);

        $this->syncToMixedbread($doc);

        app(AuditService::class)->log('knowledge.created', KnowledgeDocument::class, $doc->id, [], ['title' => $doc->title]);

        return redirect()->route('admin.knowledge-documents.index')->with('success', 'Knowledge document created.');
    }

    public function edit(KnowledgeDocument $knowledgeDocument): Response
    {
        $this->authorize('update', $knowledgeDocument);

        return Inertia::render('Admin/KnowledgeDocuments/Edit', [
            'document' => [
                'id' => $knowledgeDocument->id,
                'title' => $knowledgeDocument->title,
                'content' => $knowledgeDocument->content,
                'metadata' => $knowledgeDocument->metadata ?? [],
                'source' => $knowledgeDocument->source,
                'is_active' => $knowledgeDocument->is_active,
                'created_at' => $knowledgeDocument->created_at?->toIso8601String(),
                'updated_at' => $knowledgeDocument->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(UpdateKnowledgeDocumentRequest $request, KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        $validated = $request->validated();

        $this->deleteFromMixedbread($knowledgeDocument);

        $knowledgeDocument->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'metadata' => $validated['metadata'] ?? $knowledgeDocument->metadata,
            'is_active' => array_key_exists('is_active', $validated) ? $validated['is_active'] : $knowledgeDocument->is_active,
        ]);

        $knowledgeDocument->refresh();
        $this->syncToMixedbread($knowledgeDocument);

        app(AuditService::class)->log('knowledge.updated', KnowledgeDocument::class, $knowledgeDocument->id, [], ['title' => $knowledgeDocument->title]);

        return redirect()->route('admin.knowledge-documents.index')->with('success', 'Knowledge document updated.');
    }

    public function retrySync(KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        $this->authorize('update', $knowledgeDocument);
        $this->syncToMixedbread($knowledgeDocument);
        $knowledgeDocument->refresh();

        if ($knowledgeDocument->mxb_sync_status === KnowledgeDocument::SYNC_INDEXED) {
            return redirect()->route('admin.knowledge-documents.index')
                ->with('success', 'Sync retried for: '.$knowledgeDocument->title);
        }

        return redirect()->route('admin.knowledge-documents.index')
            ->with('error', 'Sync still failing for: '.$knowledgeDocument->title);
    }

    public function destroy(KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        $this->authorize('delete', $knowledgeDocument);

        app(AuditService::class)->log('knowledge.deleted', KnowledgeDocument::class, $knowledgeDocument->id, [], ['title' => $knowledgeDocument->title]);

        $this->deleteFromMixedbread($knowledgeDocument);
        $knowledgeDocument->delete();

        return redirect()->route('admin.knowledge-documents.index')->with('success', 'Knowledge document deleted.');
    }

    /**
     * T6: Show CSV import form.
     */
    public function importForm(): Response
    {
        $this->authorize('create', KnowledgeDocument::class);

        return Inertia::render('Admin/KnowledgeDocuments/Import');
    }

    /**
     * T6: Import CSV and create knowledge doc with admin-provided metadata.
     */
    public function import(StoreCsvImportRequest $request, CsvToNarrativeService $csvService): RedirectResponse
    {
        $this->authorize('create', KnowledgeDocument::class);

        try {
            $csvService->validateFile($request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        try {
            $content = file_get_contents($request->file('file')->getRealPath());
            $result = $csvService->convert($content);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        $metadata = $request->validated('metadata') ?? [];
        $metadata = array_filter($metadata, fn ($v) => $v !== null && $v !== '');

        $doc = KnowledgeDocument::create([
            'title' => $request->validated('title'),
            'content' => $result['content'],
            'metadata' => $metadata,
            'source' => KnowledgeDocument::SOURCE_CSV_IMPORT,
            'is_active' => true,
        ]);

        $this->syncToMixedbread($doc);

        app(AuditService::class)->log('knowledge.imported', null, null, [], ['title' => $doc->title], 'Imported knowledge document from CSV');

        return redirect()->route('admin.knowledge-documents.index')
            ->with('success', "CSV imported: {$result['row_count']} rows converted to narrative.");
    }
}

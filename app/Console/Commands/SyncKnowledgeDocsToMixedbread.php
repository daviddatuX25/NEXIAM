<?php

namespace App\Console\Commands;

use App\Models\KnowledgeDocument;
use App\Services\MixedbreadService;
use Illuminate\Console\Command;

class SyncKnowledgeDocsToMixedbread extends Command
{
    protected $signature = 'ai:sync-knowledge-docs {--force : Re-sync all docs, including already-indexed}';
    protected $description = 'Sync pending/failed knowledge documents to Mixedbread Stores for semantic search.';

    public function handle(MixedbreadService $mixedbread): int
    {
        $storeId = config('services.mixedbread.store_id', '');
        if (empty($storeId)) {
            $this->error('MIXEDBREAD_STORE_ID is not configured. Aborting.');
            return Command::FAILURE;
        }

        $query = KnowledgeDocument::query()->active();
        if (!$this->option('force')) {
            $query->whereIn('mxb_sync_status', [KnowledgeDocument::SYNC_PENDING, KnowledgeDocument::SYNC_FAILED]);
        }
        $docs  = $query->get();
        $total = $docs->count();

        if ($total === 0) {
            $this->info('No documents to sync.');
            return Command::SUCCESS;
        }

        $this->info("Syncing {$total} document(s) to Mixedbread store: {$storeId}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $failed = 0;

        foreach ($docs as $doc) {
            try {
                $result = $mixedbread->uploadDocument($storeId, $doc->content, $doc->title, array_filter((array) $doc->metadata));
                $doc->update([
                    'mxb_file_id'     => $result['id'] ?? null,
                    'mxb_sync_status' => KnowledgeDocument::SYNC_INDEXED,
                    'mxb_synced_at'   => now(),
                ]);
                $synced++;
            } catch (\Throwable $e) {
                $doc->update(['mxb_sync_status' => KnowledgeDocument::SYNC_FAILED]);
                $this->newLine();
                $this->warn("  Failed doc ID {$doc->id}: {$e->getMessage()}");
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Synced: {$synced}, Failed: {$failed}.");

        return Command::SUCCESS;
    }
}

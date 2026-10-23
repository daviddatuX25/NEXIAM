<?php

namespace App\Console\Commands;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

use function preg_match;

/**
 * Sweeps abandoned staged bundle artifacts: durable omr-bundles/<code>/v<N>
 * directories with no referencing version row. Staging is immutable and
 * failure cleanup is best-effort, so orphaned objects can accumulate after
 * duplicate refusals, derivation failures, or transaction rollbacks.
 */
class PruneStagedBundles extends Command
{
    protected $signature = 'omr:prune-staged-bundles {--dry-run : report without deleting}';

    protected $description = 'Delete staged OMR bundle artifacts not referenced by any template version';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $directories = $disk->allDirectories('omr-bundles');
        $removed = 0;

        foreach ($directories as $directory) {
            if (preg_match('#^omr-bundles/(.+)/v(\d+)$#', $directory, $matches) !== 1) {
                continue;
            }

            [, $code, $version] = $matches;
            $referenced = OmrAnswerSheetTemplateVersion::query()
                ->where('version', (int) $version)
                ->whereHas('template', fn ($query) => $query->where('code', $code))
                ->exists();

            if ($referenced) {
                continue;
            }

            $this->line(sprintf('%s: orphaned, %s.', $directory, $this->option('dry-run') ? 'would delete' : 'deleting'));
            if (! $this->option('dry-run')) {
                $disk->deleteDirectory($directory);
            }
            $removed++;
        }

        $this->info(sprintf('%d orphaned staged bundle%s %s.', $removed, $removed === 1 ? '' : 's', $this->option('dry-run') ? 'found' : 'removed'));

        return self::SUCCESS;
    }
}

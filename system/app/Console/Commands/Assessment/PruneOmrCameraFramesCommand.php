<?php

declare(strict_types=1);

namespace App\Console\Commands\Assessment;

use App\Assessment\Actions\Omr\Camera\CleanupOmrCameraFrames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class PruneOmrCameraFramesCommand extends Command
{
    protected $signature = 'omr:prune-camera-frames {--limit=100}';

    protected $description = 'Delete expired, unconsumed OMR camera originals after receipt reconciliation.';

    public function handle(CleanupOmrCameraFrames $cleanup): int
    {
        if (! Schema::hasTable('omr_camera_frames')
            || ! Schema::hasTable('omr_camera_commands')
            || ! Schema::hasTable('omr_camera_pairings')) {
            $this->line('Camera protocol tables are not installed; nothing was pruned.');

            return self::SUCCESS;
        }
        $result = $cleanup->handle((int) $this->option('limit'));
        $this->info(sprintf(
            'Camera staging cleanup: examined=%d deleted=%d failed=%d protected=%d.',
            $result['examined'],
            $result['deleted'],
            $result['failed'],
            $result['protected'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

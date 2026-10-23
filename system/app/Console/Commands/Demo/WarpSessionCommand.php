<?php

namespace App\Console\Commands\Demo;

use App\Testing\Actions\WarpDemoSessionTimeline;
use Illuminate\Console\Command;

class WarpSessionCommand extends Command
{
    protected $signature = 'demo:warp {session? : Exact owned exam session ID, never a code heuristic} {--operator= : Existing authorized operator user ID} {--set= : Owned active practice set UUID} {--stage= : Fixed timeline preset} {--purpose=demo : demo or research} {--scenario= : Selected set scenario} {--confirm= : Exact previously previewed timing operation UUID}';

    protected $description = 'Preview or confirm timing for one selected practice session without sending reminders';

    public const STAGES = WarpDemoSessionTimeline::STAGES;

    public function handle(): int
    {
        if ($this->argument('session') === null && $this->option('confirm') === null) {
            $this->line('Select a session ID with --operator, --set, --scenario and --stage to preview. Confirm only the returned operation UUID. Presets: '.implode(', ', self::STAGES));

            return self::SUCCESS;
        }

        return StageCommand::executeOperation($this, [
            'purpose' => $this->option('purpose'), 'scenario' => $this->option('scenario'), 'action' => 'time',
            'set_id' => $this->option('set'), 'session_id' => $this->argument('session'),
            'preset' => match ($this->option('stage')) {
                'tomorrow' => '24h-reminder', 'in-progress' => 'live', default => $this->option('stage'),
            },
        ]);
    }
}

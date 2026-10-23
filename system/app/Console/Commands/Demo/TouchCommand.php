<?php

namespace App\Console\Commands\Demo;

use Illuminate\Console\Command;

class TouchCommand extends Command
{
    protected $signature = 'demo:touch {--operator= : Existing authorized operator user ID} {--set= : Owned active practice set UUID} {--session= : Exact owned exam session ID} {--preset= : Fixed timeline preset} {--purpose=demo : demo or research} {--scenario= : Selected set scenario} {--confirm= : Exact previously previewed timing operation UUID}';

    protected $description = 'Preview or confirm one owned practice session timeline; never touch code-selected global rows';

    public function handle(): int
    {
        if ($this->option('set') === null && $this->option('confirm') === null) {
            $this->line('Select --operator, --set, --session, --scenario and --preset to preview one timing change. Confirm the returned UUID separately. No reminder is dispatched.');

            return self::SUCCESS;
        }

        return StageCommand::executeOperation($this, [
            'purpose' => $this->option('purpose'), 'scenario' => $this->option('scenario'), 'action' => 'time',
            'set_id' => $this->option('set'), 'session_id' => $this->option('session'),
            'preset' => match ($this->option('preset')) {
                'tomorrow' => '24h-reminder', 'in-progress' => 'live', default => $this->option('preset'),
            },
        ]);
    }
}

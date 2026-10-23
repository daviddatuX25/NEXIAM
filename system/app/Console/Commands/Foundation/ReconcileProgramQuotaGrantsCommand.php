<?php

namespace App\Console\Commands\Foundation;

use App\Authorization\Actions\ReconcileProgramQuotaGrants;
use Illuminate\Console\Command;

final class ReconcileProgramQuotaGrantsCommand extends Command
{
    protected $signature = 'capabilities:reconcile-quota-grants';

    protected $description = 'Perform targeted audited reconciliation of the D-087 program.quota.manage capability for active Program Heads';

    public function handle(ReconcileProgramQuotaGrants $action): int
    {
        $this->info('Starting targeted Program Quota grant reconciliation...');
        $result = $action->handle();

        $this->info(sprintf(
            'Reconciliation complete: %d granted, %d preserved direct grants.',
            $result['granted_count'],
            $result['preserved_count'],
        ));

        return self::SUCCESS;
    }
}

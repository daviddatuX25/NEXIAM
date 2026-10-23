<?php

namespace App\Console\Commands;

use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('omr:reconcile-physical-runs')]
#[Description('Re-derive eligible historical physical OMR runs failed by child-only capture outcomes')]
final class ReconcileOmrPhysicalRuns extends Command
{
    public function handle(OmrPhysicalAssuranceCoordinator $coordinator): int
    {
        $reconciled = 0;
        OmrTemplateVerificationRun::query()
            ->where('status', OmrTemplateVerificationRun::STATUS_FAILED)
            ->whereIn('mode', [
                OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
                OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            ])
            ->orderBy('id')
            ->eachById(function (OmrTemplateVerificationRun $run) use ($coordinator, &$reconciled): void {
                if ($coordinator->reconcileHistoricalFailure($run)) {
                    $reconciled++;
                }
            }, 100);

        $this->components->info("Reconciled {$reconciled} physical verification run(s).");

        return self::SUCCESS;
    }
}

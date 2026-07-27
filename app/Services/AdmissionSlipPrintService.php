<?php

namespace App\Services;

use App\Models\Application;

class AdmissionSlipPrintService
{
    public function markSlipPrinted(array $applicationIds, bool $printed): void
    {
        $ts = $printed ? now() : null;
        Application::whereIn('id', $applicationIds)->update(['admission_slip_printed_at' => $ts]);

        // Pipeline hook: advance each printed application to the 'printed' milestone
        if ($printed) {
            $pipeline = app(ApplicationPipelineService::class);
            Application::whereIn('id', $applicationIds)->get()->each(
                fn (Application $app) => $pipeline->transition($app, 'printed', [
                    'printed_at' => now()->toIso8601String(),
                ])
            );
        }
    }
}

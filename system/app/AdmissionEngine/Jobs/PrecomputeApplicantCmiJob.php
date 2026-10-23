<?php

namespace App\AdmissionEngine\Jobs;

use App\AdmissionEngine\Actions\PrecomputeAdmissionSupportSnapshot;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrecomputeApplicantCmiJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public int $attemptId) {}

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    public function handle(PrecomputeAdmissionSupportSnapshot $precompute): void
    {
        $attempt = AdmissionAttempt::query()->findOrFail($this->attemptId);
        $precompute->handle($attempt);
    }
}

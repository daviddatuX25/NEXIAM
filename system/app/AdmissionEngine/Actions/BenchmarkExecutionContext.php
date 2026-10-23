<?php

namespace App\AdmissionEngine\Actions;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;

final readonly class BenchmarkExecutionContext
{
    private function __construct(
        public ?User $actor,
        public string $purpose,
        public bool $trusted,
        public ?int $institutionId,
        public ?int $academicCycleId,
    ) {}

    public static function human(User $actor): self
    {
        return new self($actor, 'human', false, null, null);
    }

    public static function reviewedPublication(User $actor, Institution $institution, AcademicCycle $cycle): self
    {
        if ((int) $cycle->institution_id !== (int) $institution->getKey()) {
            throw new InvalidArgumentException('Reviewed publication context requires matching institution and cycle scope.');
        }

        return new self($actor, 'reviewed-publication', false, (int) $institution->getKey(), (int) $cycle->getKey());
    }

    public static function trustedSeed(Institution $institution, string $purpose): self
    {
        if (! App::environment('local', 'testing') || app()->runningInConsole() === false || $purpose !== 'seed') {
            throw new InvalidArgumentException('Trusted benchmark seed context is available only to local/testing console seed execution.');
        }

        return new self(null, $purpose, true, (int) $institution->getKey(), null);
    }

    public function assertInstitution(Institution $institution): void
    {
        if (($this->trusted || $this->purpose === 'reviewed-publication') && $this->institutionId !== (int) $institution->getKey()) {
            throw new InvalidArgumentException('Benchmark execution context scope does not match the requested institution.');
        }

        if (! $this->trusted && $this->actor === null) {
            throw new InvalidArgumentException('Benchmark command requires an explicit actor context.');
        }
    }

    public function isReviewedPublication(Institution $institution, AcademicCycle $cycle): bool
    {
        return $this->purpose === 'reviewed-publication'
            && $this->actor !== null
            && $this->institutionId === (int) $institution->getKey()
            && $this->academicCycleId === (int) $cycle->getKey();
    }
}

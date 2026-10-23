<?php

namespace App\Assessment\Omr;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/** Ordered, safe diagnostics for all four quarter-turn candidates. */
final readonly class OmrQuarterTurnDiagnostics implements IteratorAggregate
{
    /**
     * @param  list<OmrQuarterTurnCandidate>  $candidates
     * @param  list<array<string, mixed>>  $failures
     * @param  array<string, float|null>  $timings
     * @param  list<array<string, mixed>>  $results
     */
    public function __construct(
        public array $candidates,
        public array $failures = [],
        public array $timings = [],
        public array $results = [],
    ) {}

    /** @return list<OmrQuarterTurnCandidate> */
    public function all(): array
    {
        return $this->candidates;
    }

    /** @return list<int> */
    public function orientations(): array
    {
        return array_map(static fn (OmrQuarterTurnCandidate $candidate): int => $candidate->orientation, $this->candidates);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->candidates);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'candidates' => array_map(
                static fn (OmrQuarterTurnCandidate $candidate): array => $candidate->toArray(),
                $this->candidates,
            ),
            'failures' => $this->failures,
            'timings' => $this->timings,
            'results' => $this->results,
        ];
    }
}

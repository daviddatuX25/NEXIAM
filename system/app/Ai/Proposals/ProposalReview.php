<?php

declare(strict_types=1);

namespace App\Ai\Proposals;

use InvalidArgumentException;

/** Immutable canonical command and its separate human-review projection. */
final readonly class ProposalReview
{
    /** @param array<string, mixed> $commandInput
     * @param  array<string, mixed>  $review
     */
    private function __construct(
        public string $capabilityId,
        public array $commandInput,
        public array $review,
    ) {}

    /** @param array<string, mixed> $result */
    public static function fromResult(string $capabilityId, array $result): self
    {
        if (! isset($result['command_input']) || ! is_array($result['command_input']) || ! isset($result['proposal_review']) || ! is_array($result['proposal_review'])) {
            throw new InvalidArgumentException('proposal_review_required');
        }

        ProposalViewContract::validate($result['proposal_review'], $result['command_input']);

        return new self($capabilityId, $result['command_input'], $result['proposal_review']);
    }
}

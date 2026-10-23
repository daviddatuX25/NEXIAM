<?php

namespace App\Application\Capabilities;

use App\Ai\Context\InvocationContext;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Untrusted invocation envelope. Caller-supplied pack/surface are hints only.
 */
final readonly class CapabilityInvocationRequest
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public string $key,
        public int $version,
        public array $input,
        public ?Authenticatable $actor,
        public string $channel = 'http_page',
        public ?string $surfaceKey = null,
        public ?string $pagePackKey = null,
        public ?string $correlationId = null,
        public ?InvocationContext $context = null,
        public bool $serverConfirmed = false,
    ) {}
}

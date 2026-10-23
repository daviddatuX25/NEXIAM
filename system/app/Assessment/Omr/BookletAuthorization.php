<?php

namespace App\Assessment\Omr;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\User;
use RuntimeException;

final class BookletAuthorization
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    public function assertManage(User $actor): void
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE)) {
            throw new RuntimeException('You are not authorized to manage Booklets.');
        }
    }

    public function assertQuestionContent(User $actor): void
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT)) {
            throw new RuntimeException('Question content access is not authorized.');
        }
    }

    public function canQuestionContent(User $actor): bool
    {
        return $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT);
    }
}

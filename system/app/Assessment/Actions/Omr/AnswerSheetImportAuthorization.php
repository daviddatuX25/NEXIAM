<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Support\MaterialOwnerAccess;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\AnswerSheetImportOperation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class AnswerSheetImportAuthorization
{
    public function __construct(private readonly MaterialOwnerAccess $materialOwnerAccess) {}

    public function assertOwner(User $actor, AnswerSheetImportOperation $operation): int
    {
        $institutionId = $this->materialOwnerAccess->institutionId(
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );

        if ((int) $operation->owner_user_id !== (int) $actor->getKey()
            || (int) $operation->institution_id !== $institutionId) {
            throw new AuthorizationException('Answer Sheet import operation is unavailable.');
        }

        return $institutionId;
    }

    public function freshOwner(AnswerSheetImportOperation $operation): User
    {
        $actor = User::query()->find((int) $operation->owner_user_id);

        if ($actor === null) {
            throw new AuthorizationException('Answer Sheet import operation is unavailable.');
        }

        $this->assertOwner($actor, $operation);

        return $actor;
    }
}

<?php

declare(strict_types=1);

namespace App\Communications;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ProgramPublicProfileAuthorizer
{
    public function __construct(private readonly CapabilityAuthorizer $capabilities) {}

    public function assertCanManage(User $actor, Program $program): User
    {
        return $this->assertCapability($actor, $program, CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE);
    }

    public function assertCanPublish(User $actor, Program $program): User
    {
        return $this->assertCapability($actor, $program, CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_PUBLISH);
    }

    public function assertCanReadPrivateMedia(User $actor, Program $program): User
    {
        try {
            return $this->assertCanManage($actor, $program);
        } catch (AuthorizationException) {
            return $this->assertCanPublish($actor, $program);
        }
    }

    public function canPublish(User $actor, Program $program): bool
    {
        try {
            $this->assertCanPublish($actor, $program);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    private function assertCapability(User $actor, Program $program, string $capability): User
    {
        $programQuery = Program::query()->whereKey($program->getKey());
        if (DB::transactionLevel() > 0) {
            $programQuery->lockForUpdate();
        }
        $freshProgram = $programQuery->first();
        $freshActor = User::query()->find($actor->getKey());

        if ($freshProgram === null || $freshActor === null) {
            throw new AuthorizationException('The program is no longer available to this account.');
        }

        if ($freshProgram->department_id !== null && DB::transactionLevel() > 0) {
            Department::query()->whereKey($freshProgram->department_id)->lockForUpdate()->first();
        }

        $profileQuery = StaffProfile::query()->where('user_id', $freshActor->getKey());
        if (DB::transactionLevel() > 0) {
            $profileQuery->lockForUpdate();
        }
        $staffProfile = $profileQuery->first();
        $scope = $freshActor->actorScope();
        $institutionId = (int) ($staffProfile?->institution_id ?? 0);

        if ($staffProfile === null
            || ! $staffProfile->isActive()
            || $staffProfile->office !== 'program'
            || (int) $staffProfile->institution_id !== $institutionId
            || ! $freshProgram->is_active
            || (int) $freshProgram->institution_id !== $institutionId
            || $scope->institutionId !== $institutionId
            || ! in_array($freshProgram->code, $scope->programCodes, true)
            || ! $this->capabilities->decideFresh($freshActor, $capability, [
                'institution_id' => $institutionId,
                'program' => $freshProgram->code,
            ])->allowed) {
            throw new AuthorizationException('You are not authorized to manage public details for this active program.');
        }

        return $freshActor;
    }
}

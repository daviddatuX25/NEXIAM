<?php

namespace App\Setup\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetDocumentRequirementActive
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        User $actor,
        IntakeRequirement $requirement,
        bool $isActive,
        ?int $expectedVersion = null,
    ): IntakeRequirement {
        $institutionId = Institution::current()->id;
        $actorScope = $actor->actorScope();
        $scope = [];
        if ($actorScope->institutionId !== null && $institutionId !== null) {
            $scope['institution_id'] = (int) $institutionId;
        }

        $registrarScope = $scope;
        if ($actorScope->office !== null) {
            $registrarScope['office'] = 'registrar';
        }

        return DB::transaction(function () use ($actor, $requirement, $isActive, $expectedVersion, $institutionId, $scope, $registrarScope): IntakeRequirement {
            abort_unless(
                $this->authorizer->decideFresh($actor, CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE, $registrarScope)->allowed
                    || $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, $scope)->allowed,
                403,
            );
            abort_unless($institutionId !== null, 403);
            $locked = IntakeRequirement::query()
                ->where('institution_id', $institutionId)
                ->whereKey($requirement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedVersion !== null && (int) $locked->version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'expected_version' => 'This document requirement was updated by another staff member. Reload and review the latest changes.',
                ]);
            }

            if ($locked->is_active === $isActive) {
                return $locked;
            }

            $oldIsActive = $locked->is_active;
            $locked->is_active = $isActive;
            $locked->version = (int) $locked->version + 1;
            $locked->save();

            $this->recordAuditEvent->handle('setup.intake_requirement_upserted', [
                'requirement_id' => $locked->id,
                'institution_id' => (int) $institutionId,
                'code' => $locked->code,
                'old_is_active' => $oldIsActive,
                'is_active' => $locked->is_active,
                'version' => $locked->version,
            ], (string) $actor->id);

            return $locked;
        });
    }
}

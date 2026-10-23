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

final class UpdateDocumentRequirement
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  array{label: string, applicant_track: string, item_kind: string, is_mandatory: bool, is_active?: bool, sort_order: int, expected_version?: int}  $attributes
     */
    public function handle(User $actor, IntakeRequirement $requirement, array $attributes): IntakeRequirement
    {
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

        return DB::transaction(function () use ($actor, $requirement, $attributes, $institutionId, $scope, $registrarScope): IntakeRequirement {
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

            if (isset($attributes['expected_version']) && (int) $locked->version !== (int) $attributes['expected_version']) {
                throw ValidationException::withMessages([
                    'expected_version' => 'This document requirement was updated by another staff member. Reload and review the latest changes.',
                ]);
            }

            $oldIsActive = $locked->is_active;
            $changes = array_diff_key($attributes, ['expected_version' => true]);
            $locked->fill($changes);
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

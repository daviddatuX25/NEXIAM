<?php

namespace App\Setup\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateDocumentRequirement
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  array{code: string, label: string, applicant_track: string, item_kind: string, is_mandatory?: bool, is_active?: bool, sort_order?: int}  $attributes
     */
    public function handle(User $actor, array $attributes): IntakeRequirement
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

        return DB::transaction(function () use ($actor, $attributes, $institutionId, $scope, $registrarScope): IntakeRequirement {
            abort_unless(
                $this->authorizer->decideFresh($actor, CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE, $registrarScope)->allowed
                    || $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, $scope)->allowed,
                403,
            );
            abort_unless($institutionId !== null, 403);
            $requirement = IntakeRequirement::query()->create([
                ...$attributes,
                'institution_id' => (int) $institutionId,
                'sort_order' => $attributes['sort_order'] ?? 100,
                'is_mandatory' => $attributes['is_mandatory'] ?? true,
                'is_active' => $attributes['is_active'] ?? true,
                'version' => 1,
            ]);

            $this->recordAuditEvent->handle('setup.intake_requirement_upserted', [
                'requirement_id' => $requirement->id,
                'institution_id' => (int) $institutionId,
                'code' => $requirement->code,
                'is_active' => $requirement->is_active,
            ], (string) $actor->id);

            return $requirement;
        });
    }
}

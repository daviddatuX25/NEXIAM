<?php

declare(strict_types=1);

namespace App\Consultation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class ManageConsultationPolicyAction
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly RecordAuditEvent $audit,
        private readonly DispatchPendingConsultationMediaCloses $mediaCloses,
    ) {}

    /**
     * @param  array{normal_method: string, allow_online_exceptions: bool, expected_version: int}  $input
     */
    public function handle(AcademicCycle $cycle, User $actor, array $input): ConsultationPolicy
    {
        if (! $this->authorizer->allows(
            $actor,
            CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
            ['institution_id' => (int) $cycle->institution_id, 'office' => 'guidance'],
        )) {
            throw new AuthorizationException;
        }

        $affectedEncounterIds = [];
        $policy = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $cycle->id,
            function () use ($cycle, $actor, $input, &$affectedEncounterIds): ConsultationPolicy {
                $lockedCycle = AcademicCycle::query()->lockForUpdate()->findOrFail($cycle->id);
                $normalMethod = $input['normal_method'] ?? null;
                $allowOnlineExceptions = $input['allow_online_exceptions'] ?? null;
                $expectedVersion = $input['expected_version'] ?? null;

                if (! in_array($normalMethod, [ConsultationPolicy::METHOD_F2F, ConsultationPolicy::METHOD_ONLINE], true)) {
                    throw ValidationException::withMessages(['normal_method' => 'Choose Online or F2F.']);
                }
                if (! is_bool($allowOnlineExceptions)) {
                    throw ValidationException::withMessages(['allow_online_exceptions' => 'Choose a valid online exception setting.']);
                }
                if (! is_int($expectedVersion) || $expectedVersion < 0) {
                    throw ValidationException::withMessages(['expected_version' => 'A current consultation policy version is required.']);
                }
                $allowOnlineExceptions = $normalMethod === ConsultationPolicy::METHOD_F2F && $allowOnlineExceptions;

                $policy = ConsultationPolicy::query()
                    ->where('institution_id', $lockedCycle->institution_id)
                    ->where('academic_cycle_id', $lockedCycle->id)
                    ->lockForUpdate()
                    ->first();
                $wasOnlineAllowed = $policy?->allowsOnlineConsultation() ?? true;

                if ($policy !== null && $policy->version !== $expectedVersion) {
                    throw ValidationException::withMessages(['version' => 'Consultation policy changed. Reload and review it.']);
                }

                if ($policy === null && $expectedVersion !== 0) {
                    throw ValidationException::withMessages(['version' => 'Consultation policy changed. Reload and review it.']);
                }

                $policy ??= new ConsultationPolicy([
                    'institution_id' => $lockedCycle->institution_id,
                    'academic_cycle_id' => $lockedCycle->id,
                    'normal_method' => ConsultationPolicy::METHOD_ONLINE,
                    'allow_online_exceptions' => false,
                    'version' => 0,
                ]);
                $policy->fill([
                    'normal_method' => $normalMethod,
                    'allow_online_exceptions' => $allowOnlineExceptions,
                    'configured_by_user_id' => $actor->id,
                    'version' => $policy->version + 1,
                ]);
                if ($wasOnlineAllowed && ! $policy->allowsOnlineConsultation()) {
                    ConsultationEncounter::query()
                        ->where('institution_id', $lockedCycle->institution_id)
                        ->whereHas('admissionAttempt', fn ($query) => $query->where('academic_cycle_id', $lockedCycle->id))
                        ->where('medium', ConsultationEncounter::MEDIUM_ONLINE)
                        ->whereIn('status', [
                            ConsultationEncounter::STATUS_INVITED,
                            ConsultationEncounter::STATUS_WAITING,
                            ConsultationEncounter::STATUS_IN_PROGRESS,
                            ConsultationEncounter::STATUS_OUTCOME_RECORDED,
                        ])
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->each(function (ConsultationEncounter $encounter) use (&$affectedEncounterIds): void {
                            $changes = [
                                'schedule_generation' => $encounter->schedule_generation + 1,
                                'media_close_status' => ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING,
                            ];
                            if ($encounter->status !== ConsultationEncounter::STATUS_OUTCOME_RECORDED) {
                                $changes['status'] = ConsultationEncounter::STATUS_CLOSED_WITHOUT_OUTCOME;
                                $changes['closed_at'] = now();
                                $changes['close_reason'] = 'Online consultation disabled for academic cycle';
                            }
                            $encounter->update($changes);
                            $affectedEncounterIds[] = (int) $encounter->getKey();
                        });
                }
                $policy->save();

                $this->audit->handle('setup.consultation_policy_updated', [
                    'academic_cycle_id' => $lockedCycle->id,
                    'normal_method' => $policy->normal_method,
                    'allow_online_exceptions' => $policy->allow_online_exceptions,
                    'version' => $policy->version,
                ], (string) $actor->id);

                return $policy->fresh();
            },
        );

        $this->mediaCloses->handle($affectedEncounterIds);

        return $policy;
    }
}

<?php

namespace App\Setup\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateAdmissionNotificationPolicy
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{expected_version: int, applicant_status_email_enabled: bool, registrar_new_submission_alert_enabled: bool}  $input
     */
    public function handle(AcademicCycle $cycle, User $actor, array $input): AcademicCycle
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
            'institution_id' => (int) $cycle->institution_id,
        ]) && ! $this->authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, [
            'institution_id' => (int) $cycle->institution_id,
            'office' => 'registrar',
        ])) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($cycle, $actor, $input): AcademicCycle {
            $lockedCycle = AcademicCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $current = AdmissionNotificationPolicy::fromStorage($lockedCycle->admission_notification_policy);

            if ($current->version !== (int) $input['expected_version']) {
                throw ValidationException::withMessages(['version' => 'Admission notification policy changed. Reload and review it.']);
            }

            $next = $current->next([
                'applicant_status_email_enabled' => $input['applicant_status_email_enabled'],
                'registrar_new_submission_alert_enabled' => $input['registrar_new_submission_alert_enabled'],
            ]);
            $lockedCycle->update(['admission_notification_policy' => $next->toArray()]);

            $this->audit->handle('setup.admission_notification_policy_updated', [
                'academic_cycle_id' => $lockedCycle->id,
                'version' => $next->version,
            ], (string) $actor->id);

            return $lockedCycle->fresh();
        });
    }
}

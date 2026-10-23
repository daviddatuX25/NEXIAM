<?php

namespace App\Setup\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Notifications\Policies\ExamNotificationPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateExamNotificationPolicy
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{expected_version: int, publication_email_enabled: bool, reminder_offsets_minutes: list<int>, reminder_email_enabled: bool}  $input
     */
    public function handle(AcademicCycle $cycle, User $actor, array $input): AcademicCycle
    {
        if (! $this->authorizer->allows(
            $actor,
            CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            ['institution_id' => (int) $cycle->institution_id, 'office' => 'guidance'],
        )) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($cycle, $actor, $input): AcademicCycle {
            $lockedCycle = AcademicCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $current = ExamNotificationPolicy::fromStorage($lockedCycle->exam_notification_policy);

            if ($current->version !== (int) $input['expected_version']) {
                throw ValidationException::withMessages(['version' => 'Examination notification policy changed. Reload and review it.']);
            }

            $next = $current->next([
                'publication_email_enabled' => $input['publication_email_enabled'],
                'reminder_offsets_minutes' => $input['reminder_offsets_minutes'],
                'reminder_email_enabled' => $input['reminder_email_enabled'],
            ]);
            $lockedCycle->update(['exam_notification_policy' => $next->toArray()]);

            $this->audit->handle('setup.exam_notification_policy_updated', [
                'academic_cycle_id' => $lockedCycle->id,
                'version' => $next->version,
            ], (string) $actor->id);

            return $lockedCycle->fresh();
        });
    }
}

<?php

namespace App\Console\Commands\Demo;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplateState;
use Database\Seeders\NoticeTemplateBridgeDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PrepareNoticeBridgeReminderCommand extends Command
{
    protected $signature = 'demo:prepare-notice-bridge-reminder {--template-version= : Exact reviewed published reminder version to use for the proof}';

    protected $description = 'Arm only the seeded notice bridge fixture for a genuine 24-hour reminder proof after reviewed publication';

    public function handle(CommunicationTemplateMutation $mutation, RecordAuditEvent $audit): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Notice bridge preparation is restricted to local/testing environments.');

            return self::FAILURE;
        }
        $expectedVersion = filter_var($this->option('template-version'), FILTER_VALIDATE_INT);
        if ($expectedVersion === false || $expectedVersion < 2) {
            $this->error('Provide --template-version with the exact reviewed published reminder version, at least 2.');

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($expectedVersion, $mutation, $audit): array {
                $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
                $cycle = AcademicCycle::query()->where('institution_id', $institution->id)
                    ->where('code', NoticeTemplateBridgeDemoSeeder::CYCLE_CODE)->lockForUpdate()->firstOrFail();
                $session = ExamSession::query()->where('academic_cycle_id', $cycle->id)
                    ->where('code', NoticeTemplateBridgeDemoSeeder::SESSION_CODE)->lockForUpdate()->firstOrFail();
                if ($cycle->is_active || $session->status !== ExamSession::STATUS_PUBLISHED
                    || $session->room?->code !== NoticeTemplateBridgeDemoSeeder::ROOM_CODE
                    || (int) $session->room->institution_id !== (int) $institution->id) {
                    throw new RuntimeException('The dedicated synthetic notice bridge fixture is no longer in its expected state.');
                }
                $assignments = $session->assignments()->with('admissionAttempt.identity')->lockForUpdate()->get();
                if ($assignments->count() !== 2) {
                    throw new RuntimeException('The dedicated reminder proof requires exactly two seeded assignments.');
                }
                $references = [];
                foreach ($assignments as $assignment) {
                    $attempt = $assignment->admissionAttempt;
                    if ($assignment->status !== ExamAssignment::STATUS_ASSIGNED || $attempt === null
                        || $attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
                        || (int) $attempt->institution_id !== (int) $institution->id
                        || (int) $attempt->academic_cycle_id !== (int) $cycle->id
                        || (int) $attempt->identity?->institution_id !== (int) $institution->id
                        || ($attempt->payload['synthetic_fixture'] ?? null) !== 'notice-template-studio-document-bridge') {
                        throw new RuntimeException('The dedicated applicants no longer satisfy the scoped synthetic reminder prerequisites.');
                    }
                    $references[] = $attempt->reference_code;
                }
                sort($references);
                if ($references !== array_column(NoticeTemplateBridgeDemoSeeder::APPLICANTS, 'reference')) {
                    throw new RuntimeException('The reminder proof may only use the two dedicated synthetic participants.');
                }

                $family = CommunicationTemplate::query()->where('institution_id', $institution->id)
                    ->where('domain', 'examination')->where('slug', 'reminder')->lockForUpdate()->firstOrFail();
                $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
                $mutation->authorize($family, $guidance);
                $version = $family->activeVersion();
                $provenance = $version?->import_provenance;
                $ack = $provenance['review_acknowledgment'] ?? null;
                if ($family->active_version !== $expectedVersion || $version === null
                    || $version->content_schema_version !== 'notice-content-2' || $version->document_mode !== 'none'
                    || ! is_array($version->email_blueprint) || ! is_array($provenance) || ! is_array($ack)
                    || empty($ack['actor_id']) || empty($ack['acknowledged_at'])
                    || ! hash_equals((string) ($provenance['report_sha256'] ?? ''), (string) ($ack['report_sha256'] ?? ''))
                    || ! hash_equals(CommunicationTemplateState::emailDigest($version->email_blueprint), (string) ($ack['email_blueprint_sha256'] ?? ''))
                    || $family->draft()->exists()
                    || ! $family->receipts()->where('operation', 'publish')->where('response_payload->version->id', $version->id)->exists()) {
                    throw new RuntimeException('Publish the reviewed Word-import reminder through the real studio first, then provide its exact active version.');
                }
                $policy = ExamNotificationPolicy::fromSnapshot($session->notification_policy_snapshot);
                if ($policy === null || $policy->reminderOffsetsMinutes !== [1440]
                    || ! $policy->reminderEmailEnabled || $policy->publicationEmailEnabled
                    || ($session->notification_policy_snapshot['session_version'] ?? null) !== $session->version) {
                    throw new RuntimeException('The dedicated fixture policy or publication generation changed; nothing was rearmed.');
                }
                if ($session->version > 1) {
                    if ($session->version !== 2 || ! AuditEvent::query()->where('event_type', 'demo.notice_bridge.reminder_prepared')
                        ->where('payload->session_id', $session->id)->where('payload->session_version', 2)
                        ->where('payload->template_version_id', $version->id)
                        ->where('payload->starts_at', $session->starts_at->toIso8601String())->exists()) {
                        throw new RuntimeException('The fixture generation or reviewed template changed after preparation; prior proof is preserved.');
                    }

                    return ['session' => $session, 'replayed' => true];
                }
                if (NotificationDelivery::query()->where('event_type', 'exam.reminder.v1')
                    ->where(function (Builder $deliveries) use ($session): void {
                        $deliveries->where(function (Builder $current) use ($session): void {
                            $current->where('payload->snapshot_schema_version', 'notice-delivery-2')
                                ->where('payload->event_resource->id', $session->id);
                        })->orWhere(function (Builder $historical) use ($session): void {
                            $historical->where('payload->session_id', $session->id)
                                ->where(function (Builder $schema): void {
                                    $schema->whereNull('payload->snapshot_schema_version')
                                        ->orWhere('payload->snapshot_schema_version', '!=', 'notice-delivery-2');
                                });
                        });
                    })->exists()) {
                    throw new RuntimeException('This fixture already has reminder evidence. Preserve it; preparation will not rewrite or delete prior occurrences.');
                }

                $startsAt = now()->addHours(24);
                $session->update([
                    'starts_at' => $startsAt,
                    'effective_end_at' => $startsAt->copy()->addHours(2),
                    'version' => 2,
                    'notification_policy_snapshot' => $policy->snapshot(2),
                ]);
                $audit->handle('demo.notice_bridge.reminder_prepared', [
                    'session_id' => $session->id, 'institution_id' => $institution->id,
                    'session_version' => 2, 'template_version_id' => $version->id,
                    'template_version' => $version->version, 'starts_at' => $session->starts_at->toIso8601String(),
                    'synthetic_fixture' => true, 'authorization_user_id' => $guidance->id,
                ], 'notice-bridge-demo-command');

                return ['session' => $session->fresh(), 'replayed' => false];
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result['replayed'] ? 'Fixture already prepared; schedule and generation preserved.' : 'Synthetic fixture armed for the genuine 24-hour reminder producer; no reminder emitted by this command.');
        $this->line('Session: '.$result['session']->code.'; generation: '.$result['session']->version);
        $this->line('Starts at: '.$result['session']->starts_at->toIso8601String());

        return self::SUCCESS;
    }
}

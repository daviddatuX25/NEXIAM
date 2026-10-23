<?php

namespace App\Console\Commands\Foundation;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AuditEvent;
use App\Models\StaffProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReconcileLegacyAuditScopeCommand extends Command
{
    protected $signature = 'audit:reconcile-legacy-scope {--chunk=500 : Chunk size for processing}';

    protected $description = 'Deterministically associate legacy un-scoped audit events with their owning institution (Task 2.1b)';

    public function handle(): int
    {
        $this->info('Scanning audit events with missing institution_id in payload...');

        $chunkSize = max(100, (int) $this->option('chunk'));
        $resolvedCount = 0;
        $unresolvedCount = 0;

        $unscopedQuery = AuditEvent::query()
            ->whereNull('payload->institution_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('audit_event_scope_associations')
                    ->whereColumn('audit_event_scope_associations.audit_event_id', 'audit_events.id');
            })
            ->orderBy('id');

        $totalUnscoped = (clone $unscopedQuery)->count();
        $this->info("Found {$totalUnscoped} un-associated audit events.");

        if ($totalUnscoped === 0) {
            $this->info('All audit events are already associated.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalUnscoped);
        $bar->start();

        $lastId = null;

        while (true) {
            $batchQuery = AuditEvent::query()
                ->whereNull('payload->institution_id')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('audit_event_scope_associations')
                        ->whereColumn('audit_event_scope_associations.audit_event_id', 'audit_events.id');
                });

            if ($lastId !== null) {
                $batchQuery->where('id', '>', $lastId);
            }

            $events = $batchQuery->orderBy('id')->limit($chunkSize)->get();

            if ($events->isEmpty()) {
                break;
            }

            $associations = [];
            $now = now();

            foreach ($events as $event) {
                $lastId = (string) $event->id;
                $payload = is_array($event->payload) ? $event->payload : [];
                $resolved = $this->resolveScope($event, $payload);

                if ($resolved !== null) {
                    $associations[] = [
                        'audit_event_id' => $event->id,
                        'institution_id' => $resolved['institution_id'],
                        'office' => $resolved['office'] ?? null,
                        'program_id' => $resolved['program_id'] ?? null,
                        'resolution_evidence' => $resolved['evidence'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $resolvedCount++;
                } else {
                    $unresolvedCount++;
                }

                $bar->advance();
            }

            if ($associations !== []) {
                DB::table('audit_event_scope_associations')->insert($associations);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Association complete: {$resolvedCount} resolved and indexed, {$unresolvedCount} unresolvable.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{institution_id: int, office: ?string, program_id: ?int, evidence: string}|null
     */
    private function resolveScope(AuditEvent $event, array $payload): ?array
    {
        // 1. Direct attempt reference
        $attemptId = $payload['attempt_id'] ?? $payload['admission_attempt_id'] ?? null;
        if ($attemptId !== null && is_numeric($attemptId)) {
            $attempt = AdmissionAttempt::query()->select(['id', 'institution_id'])->find($attemptId);
            if ($attempt !== null && $attempt->institution_id !== null) {
                return [
                    'institution_id' => (int) $attempt->institution_id,
                    'office' => null,
                    'program_id' => null,
                    'evidence' => 'attempt_id',
                ];
            }
        }

        // 2. Exam session reference
        $examSessionId = $payload['exam_session_id'] ?? $payload['target_exam_session_id'] ?? $payload['session_id'] ?? null;
        if ($examSessionId !== null && is_numeric($examSessionId)) {
            $session = ExamSession::query()->with('academicCycle:id,institution_id')->find($examSessionId);
            if ($session?->academicCycle?->institution_id !== null) {
                return [
                    'institution_id' => (int) $session->academicCycle->institution_id,
                    'office' => 'guidance',
                    'program_id' => null,
                    'evidence' => 'exam_session_id',
                ];
            }
        }

        // 3. Staff user reference
        $userId = $payload['user_id'] ?? $payload['actor_id'] ?? (is_numeric($event->actor) ? $event->actor : null);
        if ($userId !== null && is_numeric($userId)) {
            $profile = StaffProfile::query()->where('user_id', $userId)->first(['institution_id', 'office']);
            if ($profile !== null && $profile->institution_id !== null) {
                return [
                    'institution_id' => (int) $profile->institution_id,
                    'office' => $payload['office'] ?? $profile->office,
                    'program_id' => null,
                    'evidence' => 'staff_profile',
                ];
            }
        }

        // 4. Notification delivery reference
        $deliveryId = $payload['delivery_id'] ?? null;
        if ($deliveryId !== null && is_string($deliveryId)) {
            $delivery = DB::table('notification_deliveries')->where('id', $deliveryId)->first(['payload']);
            if ($delivery !== null) {
                $dPayload = json_decode((string) $delivery->payload, true);
                if (is_array($dPayload) && isset($dPayload['institution_id']) && is_numeric($dPayload['institution_id'])) {
                    return [
                        'institution_id' => (int) $dPayload['institution_id'],
                        'office' => null,
                        'program_id' => null,
                        'evidence' => 'notification_delivery',
                    ];
                }
            }
        }

        // 5. Office inquiry reference
        $inquiryId = $payload['inquiry_id'] ?? null;
        if ($inquiryId !== null && is_numeric($inquiryId)) {
            $instId = DB::table('applicant_office_inquiries')->where('id', $inquiryId)->value('institution_id');
            if ($instId !== null) {
                return [
                    'institution_id' => (int) $instId,
                    'office' => $payload['office'] ?? null,
                    'program_id' => null,
                    'evidence' => 'office_inquiry',
                ];
            }
        }

        // 6. Applicant identity reference
        $identityId = $payload['identity_id'] ?? null;
        if ($identityId !== null && is_numeric($identityId)) {
            $instId = DB::table('applicant_identities')->where('id', $identityId)->value('institution_id');
            if ($instId !== null) {
                return [
                    'institution_id' => (int) $instId,
                    'office' => null,
                    'program_id' => null,
                    'evidence' => 'applicant_identity',
                ];
            }
        }

        // 7. Applicant account reference
        $accountId = $payload['account_id'] ?? null;
        if ($accountId !== null && is_numeric($accountId)) {
            $identityFk = DB::table('applicant_accounts')->where('id', $accountId)->value('applicant_identity_id');
            if ($identityFk !== null) {
                $instId = DB::table('applicant_identities')->where('id', $identityFk)->value('institution_id');
                if ($instId !== null) {
                    return [
                        'institution_id' => (int) $instId,
                        'office' => null,
                        'program_id' => null,
                        'evidence' => 'applicant_account',
                    ];
                }
            }
        }

        // 8. Record thread reference
        $threadId = $payload['thread_id'] ?? null;
        if ($threadId !== null && is_numeric($threadId)) {
            $attemptId = DB::table('record_threads')->where('id', $threadId)->value('admission_attempt_id');
            if ($attemptId !== null) {
                $attempt = AdmissionAttempt::query()->select(['id', 'institution_id'])->find($attemptId);
                if ($attempt?->institution_id !== null) {
                    return [
                        'institution_id' => (int) $attempt->institution_id,
                        'office' => null,
                        'program_id' => null,
                        'evidence' => 'record_thread',
                    ];
                }
            }
        }

        // 9. Fallback: single-institution academic cycle lookup if academic_cycle_id is in payload
        $cycleId = $payload['academic_cycle_id'] ?? null;
        if ($cycleId !== null && is_numeric($cycleId)) {
            $institutionId = DB::table('academic_cycles')->where('id', $cycleId)->value('institution_id');
            if ($institutionId !== null) {
                return [
                    'institution_id' => (int) $institutionId,
                    'office' => null,
                    'program_id' => null,
                    'evidence' => 'academic_cycle_id',
                ];
            }
        }

        return null;
    }
}

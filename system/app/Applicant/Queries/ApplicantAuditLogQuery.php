<?php

namespace App\Applicant\Queries;

use App\Models\Admission\ApplicantIdentity;
use App\Models\Foundation\AuditEvent;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class ApplicantAuditLogQuery
{
    private const CATEGORIES = [
        'application' => [
            'admission.attempt_submitted',
            'admission.applicant_amended_pending',
            'admission.correction_requested',
            'admission.attempt_accepted',
            'admission.attempt_dismissed',
            'admission.attempt_corrected',
            'admission.attempt_imported',
            'admission.attempt_archived',
            'admission.accepted_amendment_authorized',
        ],
        'examination' => [
            'exam.slot_reserved',
            'exam.slot_switched',
            'exam.attendance_recorded',
            'exam.assignment_created',
            'exam.direct_assessment_assigned',
        ],
        'results_release' => [
            'grading.score_recorded',
            'release.document_prepared',
            'release.result_released',
            'consultation.decision_recorded',
            'consultation.slot_switched',
            'program.selection_acknowledged',
            'program.selection_declined',
            'program.selection_cancelled',
        ],
        'clearance' => [
            'registrar.eligibility_lookup',
            'admission.clearance.confirmed',
            'admission.clearance.revoked',
            'admission.enrollment_sheet.issued',
            'admission.document_verified',
            'admission.requirement_set_initialized',
        ],
        'security' => [
            'admission.applicant_login',
            'admission.applicant_voucher_login',
            'admission.access_key_reissued',
            'auth.applicant_logout',
            'program.head_record_viewed',
        ],
    ];

    private const LABELS = [
        'admission.attempt_submitted' => 'Application submitted',
        'admission.applicant_amended_pending' => 'Application details updated',
        'admission.correction_requested' => 'Application correction requested',
        'admission.attempt_accepted' => 'Application accepted',
        'admission.attempt_dismissed' => 'Application dismissed',
        'admission.attempt_corrected' => 'Application corrected',
        'admission.attempt_imported' => 'Application imported',
        'admission.attempt_archived' => 'Application archived',
        'admission.accepted_amendment_authorized' => 'Application amendment authorized',
        'exam.slot_reserved' => 'Examination schedule reserved',
        'exam.slot_switched' => 'Examination schedule changed',
        'exam.assignment_created' => 'Examination session assigned',
        'exam.attendance_recorded' => 'Examination attendance recorded',
        'exam.direct_assessment_assigned' => 'Direct assessment session assigned',
        'grading.score_recorded' => 'Examination scores recorded',
        'release.document_prepared' => 'Results document prepared',
        'release.result_released' => 'Results released',
        'consultation.decision_recorded' => 'Release of Results decision recorded',
        'consultation.slot_switched' => 'Release appointment changed',
        'program.selection_acknowledged' => 'Program choice acknowledged',
        'program.selection_declined' => 'Program choice declined',
        'program.selection_cancelled' => 'Program choice cancelled',
        'registrar.eligibility_lookup' => 'Registrar checked enrollment eligibility',
        'admission.clearance.confirmed' => 'Admission clearance confirmed',
        'admission.clearance.revoked' => 'Admission clearance revoked',
        'admission.enrollment_sheet.issued' => 'Enrollment sheet issued',
        'admission.document_verified' => 'Document verification recorded',
        'admission.requirement_set_initialized' => 'Clearance requirements initialized',
        'admission.applicant_login' => 'Signed in to the applicant portal',
        'admission.applicant_voucher_login' => 'Signed in using an access voucher',
        'admission.access_key_reissued' => 'Access key reissued',
        'auth.applicant_logout' => 'Signed out of the applicant portal',
        'program.head_record_viewed' => 'Program Head viewed your record',
    ];

    /**
     * @param  list<int|string>  $attemptIds
     * @param  array{category: string, actor_office: string, search: string, sort: string, page: int}  $filters
     * @return array{events: LengthAwarePaginator, category_counts: array<string, int>}
     */
    public function handle(User $user, ?ApplicantIdentity $identity, array $attemptIds, array $filters): array
    {
        $attemptIds = array_map('strval', $attemptIds);
        $filters['actor_office'] = $filters['actor_office'] === 'program_head' ? 'program' : $filters['actor_office'];
        $actorTokens = array_values(array_filter([(string) $user->getKey(), $identity?->email]));

        $assignmentIdText = match (AuditEvent::query()->getConnection()->getDriverName()) {
            'pgsql', 'sqlite' => 'CAST(exam_assignments.id AS TEXT)',
            default => 'CAST(exam_assignments.id AS CHAR)',
        };
        $base = AuditEvent::query()
            ->whereIn('event_type', array_merge(...array_values(self::CATEGORIES)))
            ->where(function (Builder $query) use ($actorTokens, $attemptIds, $assignmentIdText): void {
                if ($attemptIds !== []) {
                    $query->where(function (Builder $targetQuery) use ($attemptIds, $assignmentIdText): void {
                        foreach ($attemptIds as $attemptId) {
                            $targetQuery->orWhere('payload->attempt_id', $attemptId);
                        }
                        $targetQuery->orWhere(function (Builder $cohortQuery) use ($attemptIds): void {
                            $cohortQuery->where('event_type', 'exam.direct_assessment_assigned')
                                ->where(function (Builder $targets) use ($attemptIds): void {
                                    foreach ($attemptIds as $attemptId) {
                                        $targets->orWhereJsonContains('payload->attempt_ids', $attemptId);
                                        if (ctype_digit($attemptId)) {
                                            $targets->orWhereJsonContains('payload->attempt_ids', (int) $attemptId);
                                        }
                                    }
                                });
                        });
                        $targetQuery->orWhere(function (Builder $attendanceQuery) use ($attemptIds, $assignmentIdText): void {
                            $attendanceQuery->where('event_type', 'exam.attendance_recorded')
                                ->whereExists(function ($assignmentQuery) use ($attemptIds, $assignmentIdText): void {
                                    $assignmentQuery->selectRaw('1')
                                        ->from('exam_assignments')
                                        ->whereRaw($assignmentIdText." = audit_events.payload->>'assignment_id'")
                                        ->whereIn('exam_assignments.admission_attempt_id', $attemptIds);
                                });
                        });
                    });
                }

                if ($actorTokens !== []) {
                    $method = $attemptIds === [] ? 'where' : 'orWhere';
                    $query->{$method}(function (Builder $actorQuery) use ($actorTokens): void {
                        $actorQuery->whereIn('actor', $actorTokens)
                            ->whereIn('event_type', ['admission.applicant_login', 'admission.applicant_voucher_login', 'auth.applicant_logout']);
                    });
                }
            });
        if ($identity === null || $attemptIds === []) {
            $base->whereRaw('1 = 0');
        }

        $scopedBase = clone $base;
        if ($filters['actor_office'] === 'self') {
            $scopedBase->where(function (Builder $query) use ($actorTokens, $attemptIds): void {
                $query->where(function (Builder $ownTarget) use ($attemptIds, $actorTokens): void {
                    $ownTarget->whereIn('payload->attempt_id', $attemptIds)
                        ->whereIn('actor', $actorTokens);
                })->orWhere(function (Builder $ownCohort) use ($attemptIds, $actorTokens): void {
                    $ownCohort->where('event_type', 'exam.direct_assessment_assigned')
                        ->whereIn('actor', $actorTokens)
                        ->where(function (Builder $targets) use ($attemptIds): void {
                            foreach ($attemptIds as $attemptId) {
                                $targets->orWhereJsonContains('payload->attempt_ids', $attemptId);
                                if (ctype_digit($attemptId)) {
                                    $targets->orWhereJsonContains('payload->attempt_ids', (int) $attemptId);
                                }
                            }
                        });
                })->orWhere(function (Builder $ownLogin) use ($actorTokens): void {
                    $ownLogin->whereIn('actor', $actorTokens)
                        ->whereIn('event_type', ['admission.applicant_login', 'admission.applicant_voucher_login', 'auth.applicant_logout']);
                });
            });
        } elseif ($filters['actor_office'] === 'system') {
            $scopedBase->where('actor', 'system');
        } elseif (in_array($filters['actor_office'], ['registrar', 'guidance', 'program'], true)) {
            $userIds = StaffProfile::query()
                ->where('office', $filters['actor_office'])
                ->pluck('user_id')
                ->map(static fn ($id): string => (string) $id)
                ->all();
            $scopedBase->whereIn('actor', $userIds);
        }

        $search = trim($filters['search']);
        if ($search !== '') {
            $matchingTypes = [];
            foreach (self::LABELS as $eventType => $label) {
                if (Str::contains(Str::lower($label), Str::lower($search))) {
                    $matchingTypes[] = $eventType;
                }
            }

            $officeLabels = [
                'registrar' => 'Registrar Desk',
                'guidance' => 'Guidance Office',
                'program' => 'Program Head',
            ];
            $matchingOffices = array_keys(array_filter(
                $officeLabels,
                static fn (string $label, string $office): bool => Str::contains(Str::lower($label.' '.$office), Str::lower($search)),
                ARRAY_FILTER_USE_BOTH,
            ));
            $officeIds = StaffProfile::query()
                ->whereIn('office', $matchingOffices)
                ->pluck('user_id')
                ->map(static fn ($id): string => (string) $id)
                ->all();
            $officeTokens = $officeIds;
            if (Str::contains('assessment system', Str::lower($search))) {
                $officeTokens[] = 'system';
            }
            if (Str::contains('you', Str::lower($search))) {
                $officeTokens = array_merge($officeTokens, $actorTokens);
            }

            if ($matchingTypes === [] && $officeTokens === []) {
                $scopedBase->whereRaw('1 = 0');
            } else {
                $scopedBase->where(function (Builder $query) use ($matchingTypes, $officeTokens): void {
                    if ($matchingTypes !== []) {
                        $query->whereIn('event_type', $matchingTypes);
                    }

                    if ($officeTokens !== []) {
                        $query->orWhereIn('actor', $officeTokens);
                    }
                });
            }
        }

        $categoryCounts = [];
        foreach (self::CATEGORIES as $category => $types) {
            $categoryCounts[$category] = (clone $scopedBase)->whereIn('event_type', $types)->count();
        }

        $filtered = clone $scopedBase;
        if ($filters['category'] !== 'all') {
            $filtered->whereIn('event_type', self::CATEGORIES[$filters['category']]);
        }

        $filtered->orderBy('created_at', $filters['sort'] === 'oldest' ? 'asc' : 'desc')
            ->orderBy('id', $filters['sort'] === 'oldest' ? 'asc' : 'desc');

        $paginator = $filtered->paginate(25, ['id', 'event_type', 'actor', 'created_at'], 'page', $filters['page'])->withQueryString();
        $actors = $paginator->getCollection()
            ->pluck('actor')
            ->filter(static fn ($actor): bool => ctype_digit((string) $actor))
            ->unique()
            ->values();
        $actorOffices = StaffProfile::query()
            ->whereIn('user_id', $actors)
            ->pluck('office', 'user_id');
        $rows = $paginator->getCollection()->map(function (AuditEvent $event) use ($user, $identity, $actorOffices): array {
            $actor = (string) $event->actor;
            $selfTokens = [(string) $user->getKey(), $identity?->email];
            $isSelf = in_array($actor, $selfTokens, true);
            $office = match ($actorOffices->get((int) $actor)) {
                'registrar' => 'Registrar Desk',
                'guidance' => 'Guidance Office',
                'program' => 'Program Head',
                default => null,
            };

            return [
                'id' => (string) $event->getKey(),
                'event_type' => (string) $event->event_type,
                'label' => self::LABELS[$event->event_type] ?? Str::headline(str_replace('.', ' ', (string) $event->event_type)),
                'category' => $this->categoryFor((string) $event->event_type),
                'actor_display' => $isSelf ? 'You' : ($office ?? 'Assessment System'),
                'direction' => $isSelf ? 'by_you' : 'on_your_record',
                'occurred_at' => $event->created_at?->toISOString(),
            ];
        });
        $paginator->setCollection($rows);

        return ['events' => $paginator, 'category_counts' => $categoryCounts];
    }

    private function categoryFor(string $eventType): string
    {
        foreach (self::CATEGORIES as $category => $types) {
            if (in_array($eventType, $types, true)) {
                return $category;
            }
        }

        return 'security';
    }
}

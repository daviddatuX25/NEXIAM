<?php

namespace App\Notifications\Queries;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class NotificationOccurrenceQuery
{
    private const PER_PAGE = 15;

    private const EVENT_DOMAINS = [
        'admission.' => 'admissions',
        'application.opening_alert.' => 'admissions',
        'exam.' => 'examination',
        'result.' => 'results',
        'consultation.' => 'results',
    ];

    private const DELIVERY_AUDIT_EVENTS = [
        'notification.delivery_processing',
        'notification.delivery_delivered',
        'notification.delivery_retrying',
        'notification.delivery_failed',
        'notification.delivery_outcome_unknown',
        'notification.delivery_superseded',
        'notification.delivery_manually_retried',
    ];

    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /** @return array<string, bool> */
    public function accessibleDomains(User $actor): array
    {
        $scope = $actor->actorScope();
        if ($scope->institutionId === null) {
            return ['admissions' => false, 'examination' => false, 'results' => false];
        }

        $registrar = ['institution_id' => $scope->institutionId, 'office' => 'registrar'];
        $guidance = ['institution_id' => $scope->institutionId, 'office' => 'guidance'];

        return [
            'admissions' => $this->authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, ['institution_id' => $scope->institutionId])
                || $this->authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, $registrar),
            'examination' => $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, $guidance),
            'results' => $this->authorizer->allowsAny($actor, [CapabilityCatalog::GUIDANCE_RESULTS_PREPARE, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE], $guidance),
        ];
    }

    /** @param array<string, bool> $domains
     * @return array{admissions: int, examination: int, results: int}
     */
    public function cardCounts(User $actor, array $domains): array
    {
        $counts = ['admissions' => 0, 'examination' => 0, 'results' => 0];
        foreach ($counts as $domain => $_count) {
            if ($domains[$domain] ?? false) {
                $counts[$domain] = (int) DB::query()->fromSub($this->occurrencesForDomain($actor, $domain, []), 'domain_occurrences')->count();
            }
        }

        return $counts;
    }

    /** @param array<string, mixed> $filters */
    public function activity(User $actor, array $domains, array $filters): LengthAwarePaginator
    {
        $selected = isset($filters['domain']) ? [$filters['domain'] => $domains[$filters['domain']] ?? false] : $domains;
        $queries = [];
        foreach ($selected as $domain => $authorized) {
            if ($authorized) {
                $queries[] = $this->occurrencesForDomain($actor, $domain, $filters);
            }
        }

        $paginator = DB::query()->fromSub($this->unionOccurrences($queries), 'occurrences')
            ->orderByDesc('occurred_at')->orderByDesc('occurrence_id')->orderBy('event_type')->orderBy('resource_id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return $paginator->through(fn (object $row): array => [
            'occurrence_id' => $this->occurrenceIdentity($row),
            'occurred_at' => $row->occurred_at,
            'domain' => $row->domain,
            'safe_summary' => $this->summary((string) $row->event_type),
            'status' => $this->aggregateStatus((string) $row->statuses),
            'recipient_count' => (int) $row->recipient_count,
            'channel_summaries' => $this->channelSummaries((string) $row->channel_statuses, (int) $row->mail_count, (int) $row->in_app_count),
            'latest_attempt_at' => $row->latest_attempt_at,
            'detail_url' => route('setup.notifications.occurrences.show', ['occurrence' => $this->occurrenceIdentity($row)]),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function occurrence(User $actor, string $occurrenceId): ?array
    {
        [$eventId, $signature] = array_pad(explode('~', $occurrenceId, 2), 2, '');
        if (! preg_match('/^[a-f0-9]{64}$/', $eventId) || ! preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return null;
        }

        foreach ($this->accessibleDomains($actor) as $domain => $authorized) {
            if (! $authorized) {
                continue;
            }

            foreach ($this->occurrencesForDomain($actor, $domain, [])->where('event_id', $eventId)->get() as $row) {
                if (! hash_equals($this->occurrenceSignature($row), $signature)) {
                    continue;
                }

                return [
                    'occurrence_id' => $occurrenceId,
                    'occurred_at' => $row->occurred_at,
                    'domain' => $domain,
                    'safe_summary' => $this->summary((string) $row->event_type),
                    'status' => $this->aggregateStatus((string) $row->statuses),
                    'recipient_count' => (int) $row->recipient_count,
                    'channel_summaries' => $this->channelSummaries((string) $row->channel_statuses, (int) $row->mail_count, (int) $row->in_app_count),
                    'version' => (int) $row->event_version,
                    'event_id' => (string) $row->event_id,
                    'event_type' => (string) $row->event_type,
                    'resource_id' => (string) $row->resource_id,
                ];
            }
        }

        return null;
    }

    private function occurrenceIdentity(object $row): string
    {
        return (string) $row->event_id.'~'.$this->occurrenceSignature($row);
    }

    private function occurrenceSignature(object $row): string
    {
        $identity = json_encode([
            (string) $row->domain,
            (string) $row->event_id,
            (string) $row->event_type,
            (int) $row->event_version,
            (string) $row->resource_id,
        ], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $identity, (string) config('app.key'));
    }

    /** @param array<string, mixed> $occurrence */
    public function deliveries(User $actor, array $occurrence): LengthAwarePaginator
    {
        $resourceKeys = $this->resourceKeys($occurrence['domain'], $occurrence['event_type']);
        $authorizedIds = $this->authorizedRows($actor, $occurrence['domain'])
            ->where('event_id', $occurrence['event_id'])
            ->where('event_type', $occurrence['event_type'])
            ->where('event_version', $occurrence['version'])
            ->whereIn(DB::raw($this->jsonResourceExpression($resourceKeys)), [$occurrence['resource_id']])
            ->select('id');

        $rows = NotificationDelivery::query()->whereIn('id', $authorizedIds)
            ->orderBy('created_at')->orderBy('id')
            ->paginate(self::PER_PAGE, ['id', 'recipient_type', 'channel', 'status', 'attempts', 'next_attempt_at', 'delivered_at', 'provider_reference', 'last_error', 'updated_at'], 'deliveries_page')
            ->withQueryString();

        return $rows->through(static fn (NotificationDelivery $delivery): array => [
            'delivery_id' => (string) $delivery->getKey(),
            'recipient_label' => match ($delivery->recipient_type) {
                ApplicantIdentity::class => 'Applicant',
                ApplicationOpeningSubscription::class => 'Opening-alert subscriber',
                default => 'Staff member',
            },
            'recipient_type' => match ($delivery->recipient_type) {
                ApplicantIdentity::class => 'applicant',
                ApplicationOpeningSubscription::class => 'opening_alert_subscriber',
                default => 'staff',
            },
            'channel' => $delivery->channel,
            'status' => $delivery->status,
            'attempt_count' => (int) $delivery->attempts,
            'next_attempt_at' => $delivery->next_attempt_at?->toIso8601String(),
            'accepted_at' => $delivery->delivered_at?->toIso8601String(),
            'provider_reference_redacted' => $delivery->provider_reference === null ? null : 'Available',
            'diagnostic_redacted' => $delivery->last_error === null ? null : 'Diagnostic recorded',
            'version' => (int) $delivery->updated_at?->timestamp,
        ]);
    }

    /** @param array<string, mixed> $occurrence */
    public function verifyDeliveryInOccurrence(User $actor, array $occurrence, string $deliveryId): ?NotificationDelivery
    {
        $resourceKeys = $this->resourceKeys($occurrence['domain'], $occurrence['event_type']);
        $authorizedIds = $this->authorizedRows($actor, $occurrence['domain'])
            ->where('event_id', $occurrence['event_id'])
            ->where('event_type', $occurrence['event_type'])
            ->where('event_version', $occurrence['version'])
            ->whereIn(DB::raw($this->jsonResourceExpression($resourceKeys)), [$occurrence['resource_id']])
            ->select('id');

        return NotificationDelivery::query()
            ->whereIn('id', $authorizedIds)
            ->whereKey($deliveryId)
            ->first();
    }

    public function history(User $actor, string $occurrenceId): LengthAwarePaginator
    {
        $occurrence = $this->occurrence($actor, $occurrenceId);
        if ($occurrence === null) {
            return DB::table('audit_events')
                ->whereRaw('1 = 0')
                ->paginate(self::PER_PAGE, ['id', 'event_type', 'created_at'], 'history_page')
                ->withQueryString();
        }

        $resourceKeys = $this->resourceKeys($occurrence['domain'], $occurrence['event_type']);
        $authorizedDeliveryIds = $this->authorizedRows($actor, $occurrence['domain'])
            ->where('event_id', $occurrence['event_id'])
            ->where('event_type', $occurrence['event_type'])
            ->where('event_version', $occurrence['version'])
            ->whereIn(DB::raw($this->jsonResourceExpression($resourceKeys)), [$occurrence['resource_id']])
            ->selectRaw('CAST(id AS TEXT)');

        $events = DB::table('audit_events')
            ->whereIn('event_type', self::DELIVERY_AUDIT_EVENTS)
            ->whereIn('payload->delivery_id', $authorizedDeliveryIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['id', 'event_type', 'created_at'], 'history_page')
            ->withQueryString();

        return $events->through(static fn (object $event): array => [
            'occurred_at' => $event->created_at,
            'event' => match ($event->event_type) {
                'notification.delivery_processing' => 'Delivery attempt started',
                'notification.delivery_delivered' => 'Delivery accepted',
                'notification.delivery_retrying' => 'Retry scheduled',
                'notification.delivery_failed' => 'Delivery failed',
                'notification.delivery_outcome_unknown' => 'Provider outcome unknown',
                'notification.delivery_superseded' => 'Delivery superseded',
                'notification.delivery_manually_retried' => 'Manual delivery retry queued',
                default => 'Delivery activity',
            },
        ]);
    }

    private function authorizedRows(User $actor, string $domain): Builder
    {
        $institutionId = $actor->actorScope()->institutionId;
        $query = DB::table('notification_deliveries');
        if ($institutionId === null) {
            return $query->whereRaw('1 = 0');
        }

        $prefixes = array_keys(array_filter(self::EVENT_DOMAINS, static fn (string $value): bool => $value === $domain));
        $query->where(function (Builder $types) use ($prefixes): void {
            foreach ($prefixes as $index => $prefix) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $types->{$method}('event_type', 'like', $prefix.'%');
            }
        });

        if ($domain === 'admissions') {
            $attemptAllowed = $this->authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, ['institution_id' => $institutionId])
                || $this->authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, ['institution_id' => $institutionId, 'office' => 'registrar']);
            $openingAllowed = (bool) config('application-opening-alerts.enabled', false)
                && $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                    'institution_id' => $institutionId, 'office' => 'registrar',
                ])->allowed;

            return $query->where(function (Builder $resources) use ($attemptAllowed, $openingAllowed, $institutionId): void {
                $resources->where(function (Builder $attempts) use ($attemptAllowed, $institutionId): void {
                    $attempts->where('event_type', 'like', 'admission.%');
                    if (! $attemptAllowed) {
                        $attempts->whereRaw('1 = 0');

                        return;
                    }
                    $attempts->whereIn(DB::raw($this->jsonResourceExpression(['attempt_id'])), AdmissionAttempt::query()
                        ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId))
                        ->selectRaw('CAST(admission_attempts.id AS TEXT)'));
                });
                if ($openingAllowed) {
                    $resources->orWhere(function (Builder $opening) use ($institutionId): void {
                        $opening->whereIn('event_type', ['application.opening_alert.verify.v1', 'application.opening_alert.opened.v1'])
                            ->where('recipient_type', ApplicationOpeningSubscription::class)
                            ->whereIn('recipient_id', ApplicationOpeningSubscription::query()->where('institution_id', $institutionId)->select('id'))
                            ->whereRaw($this->jsonResourceExpression(['subscription_id']).' = CAST(recipient_id AS TEXT)');
                    });
                }
            });
        }

        if ($domain === 'examination') {
            if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, ['institution_id' => $institutionId, 'office' => 'guidance'])) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn(DB::raw($this->jsonResourceExpression(['session_id', 'exam_session_id'])), ExamSession::query()->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId))->selectRaw('CAST(exam_sessions.id AS TEXT)'));
        }
        $releaseEvents = ['result.released.v1', 'result.unreleased.v1'];
        $attemptIds = AdmissionAttempt::query()
            ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId))
            ->selectRaw('CAST(admission_attempts.id AS TEXT)');
        $query->where(function (Builder $workflows) use ($actor, $institutionId, $releaseEvents, $attemptIds): void {
            $workflows->where(function (Builder $release) use ($actor, $institutionId, $releaseEvents, $attemptIds): void {
                $release->whereIn('event_type', $releaseEvents)->whereIn(DB::raw($this->jsonResourceExpression(['attempt_id'])), $attemptIds);
                if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, ['institution_id' => $institutionId, 'office' => 'guidance'])) {
                    $release->whereRaw('1 = 0');
                }
            })->orWhere(function (Builder $preparation) use ($actor, $institutionId, $releaseEvents, $attemptIds): void {
                $preparation->whereNotIn('event_type', $releaseEvents)->whereNotLike('event_type', 'consultation.%')->whereIn(DB::raw($this->jsonResourceExpression(['attempt_id'])), $attemptIds);
                if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE, ['institution_id' => $institutionId, 'office' => 'guidance'])) {
                    $preparation->whereRaw('1 = 0');
                }
            });
            if ($this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE, ['institution_id' => $institutionId, 'office' => 'guidance'])) {
                $workflows->orWhere(function (Builder $consultation) use ($institutionId): void {
                    $consultation->where('event_type', 'like', 'consultation.%')
                        ->where('event_type', '!=', 'consultation.online_invited.v1')->whereIn(
                            DB::raw($this->jsonResourceExpression(['consultation_session_id'])),
                            ConsultationSession::query()
                                ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId))
                                ->selectRaw('CAST(consultation_sessions.id AS TEXT)'),
                        );
                });
            }
            if ($this->authorizer->allowsAny($actor, [CapabilityCatalog::GUIDANCE_RESULTS_PREPARE, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE], ['institution_id' => $institutionId, 'office' => 'guidance'])) {
                $workflows->orWhere(function (Builder $online) use ($institutionId): void {
                    $online->where('event_type', 'consultation.online_invited.v1')->whereIn(
                        DB::raw($this->jsonResourceExpression(['consultation_encounter_id'])),
                        ConsultationEncounter::query()->where('institution_id', $institutionId)
                            ->whereHas('admissionAttempt', fn ($attempt) => $attempt->where('institution_id', $institutionId)
                                ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId)))
                            ->selectRaw('CAST(consultation_encounters.id AS TEXT)'),
                    );
                });
            }
        });

        return $query;

    }

    /** @param array<string, mixed> $filters */
    private function occurrencesForDomain(User $actor, string $domain, array $filters): Builder
    {
        $resourceExpression = $this->resourceExpressionForDomain($domain);
        $query = $this->authorizedRows($actor, $domain)
            ->selectRaw("event_id as occurrence_id, event_id, event_type, event_version, {$resourceExpression} as resource_id, '{$domain}' as domain")
            ->selectRaw('MIN(created_at) as occurred_at, MAX(updated_at) as latest_attempt_at, COUNT(DISTINCT recipient_type || \'|\' || recipient_id) as recipient_count')
            ->selectRaw($this->channelAggregationSql())
            ->whereRaw($resourceExpression.' IS NOT NULL')
            ->groupBy('event_id', 'event_type', 'event_version', DB::raw($resourceExpression));

        if (! empty($filters['search'])) {
            $query->where(function (Builder $search) use ($filters, $resourceExpression): void {
                $search->where('event_type', 'like', '%'.$filters['search'].'%')->orWhereRaw($resourceExpression.' LIKE ?', ['%'.$filters['search'].'%']);
            });
        }
        foreach (['status', 'channel'] as $filter) {
            if (isset($filters[$filter])) {
                $matchingRows = DB::query()
                    ->fromSub($this->authorizedRows($actor, $domain), 'matching_deliveries')
                    ->whereColumn('matching_deliveries.event_id', 'notification_deliveries.event_id')
                    ->whereColumn('matching_deliveries.event_type', 'notification_deliveries.event_type')
                    ->whereColumn('matching_deliveries.event_version', 'notification_deliveries.event_version')
                    ->whereRaw(
                        $this->resourceExpressionForDomain($domain, 'matching_deliveries')
                        .' = '.$this->resourceExpressionForDomain($domain, 'notification_deliveries'),
                    )
                    ->where('matching_deliveries.'.$filter, $filters[$filter]);
                $query->whereExists($matchingRows->selectRaw('1'));
            }
        }
        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (isset($filters['to'])) {
            $query->where('created_at', '<', $filters['to'].' 23:59:59.999999');
        }

        return $query;
    }

    /** @param list<Builder> $queries */
    private function unionOccurrences(array $queries): Builder
    {
        if ($queries === []) {
            return DB::table('notification_deliveries')
                ->selectRaw('NULL as occurrence_id, NULL as event_id, NULL as event_type, NULL as event_version, NULL as resource_id, NULL as domain, NULL as occurred_at, NULL as latest_attempt_at, 0 as recipient_count, NULL as channels, NULL as statuses, NULL as channel_statuses, 0 as mail_count, 0 as in_app_count')
                ->whereRaw('1 = 0');
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    /** @return list<string> */
    private function resourceKeys(string $domain, ?string $eventType = null): array
    {
        if ($domain === 'admissions' && $eventType !== null && str_starts_with($eventType, 'application.opening_alert.')) {
            return ['subscription_id'];
        }

        if ($domain === 'examination') {
            return ['session_id', 'exam_session_id'];
        }

        if ($domain === 'results' && $eventType === 'consultation.online_invited.v1') {
            return ['consultation_encounter_id'];
        }

        if ($domain === 'results' && $eventType !== null && str_starts_with($eventType, 'consultation.')) {
            return ['consultation_session_id'];
        }

        return ['attempt_id'];
    }

    /** @param list<string> $keys */
    private function jsonResourceExpression(array $keys, string $table = ''): string
    {
        $driver = DB::connection()->getDriverName();
        $payload = $table === '' ? 'payload' : $table.'.payload';
        $extract = static function (string $key, bool $current = false) use ($driver, $payload): string {
            if ($driver === 'pgsql') {
                return $current ? "{$payload}->'event_resource'->>'{$key}'" : "{$payload}->>'{$key}'";
            }
            $path = $current ? 'event_resource.'.$key : $key;

            return $current ? "CAST(json_extract({$payload}, '$.{$path}') AS TEXT)" : "json_extract({$payload}, '$.{$path}')";
        };
        $coalesce = static fn (array $parts): string => count($parts) === 1 ? $parts[0] : 'COALESCE('.implode(', ', $parts).')';
        $historical = $coalesce(array_map(static fn (string $key): string => $extract($key), $keys));
        if ($keys === ['subscription_id']) {
            $historical = 'CAST('.$historical.' AS TEXT)';
        }
        $current = $coalesce(array_map(static fn (string $key): string => $extract($key, true), $keys));
        if (array_intersect($keys, ['session_id', 'exam_session_id']) !== []) {
            $eventType = $table === '' ? 'event_type' : $table.'.event_type';
            $current = "CASE WHEN {$eventType} = 'exam.reminder.v1' THEN ".$extract('id', true)." ELSE {$current} END";
        }

        $schema3Message = $extract('snapshot_schema_version')." = 'notice-delivery-3'";

        return 'CASE WHEN '.$extract('snapshot_schema_version')." = 'notice-delivery-2' OR {$schema3Message} THEN {$current} ELSE {$historical} END";
    }

    private function resourceExpressionForDomain(string $domain, string $table = ''): string
    {
        if ($domain === 'admissions') {
            $eventType = $table === '' ? 'event_type' : $table.'.event_type';

            return 'CASE WHEN '.$eventType." LIKE 'application.opening_alert.%' THEN "
                .$this->jsonResourceExpression(['subscription_id'], $table)
                .' ELSE '.$this->jsonResourceExpression(['attempt_id'], $table).' END';
        }

        if ($domain !== 'results') {
            return $this->jsonResourceExpression($this->resourceKeys($domain), $table);
        }

        $eventType = $table === '' ? 'event_type' : $table.'.event_type';

        return 'CASE WHEN '.$eventType." = 'consultation.online_invited.v1' THEN "
            .$this->jsonResourceExpression(['consultation_encounter_id'], $table)
            .' WHEN '.$eventType." LIKE 'consultation.%' THEN "
            .$this->jsonResourceExpression(['consultation_session_id'], $table)
            .' ELSE '.$this->jsonResourceExpression(['attempt_id'], $table).' END';
    }

    private function channelAggregationSql(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return "string_agg(DISTINCT channel, ',') as channels, string_agg(DISTINCT status, ',') as statuses, string_agg(DISTINCT channel || ':' || status, ',') as channel_statuses, COUNT(DISTINCT CASE WHEN channel = 'mail' THEN recipient_type || '|' || recipient_id END) as mail_count, COUNT(DISTINCT CASE WHEN channel = 'in_app' THEN recipient_type || '|' || recipient_id END) as in_app_count";
        }

        return "GROUP_CONCAT(DISTINCT channel) as channels, GROUP_CONCAT(DISTINCT status) as statuses, GROUP_CONCAT(DISTINCT channel || ':' || status) as channel_statuses, COUNT(DISTINCT CASE WHEN channel = 'mail' THEN recipient_type || '|' || recipient_id END) as mail_count, COUNT(DISTINCT CASE WHEN channel = 'in_app' THEN recipient_type || '|' || recipient_id END) as in_app_count";
    }

    private function aggregateStatus(string $statuses): string
    {
        $all = array_unique(explode(',', $statuses));
        if (count($all) > 1) {
            return 'mixed';
        }

        return match ($all[0] ?? '') {
            NotificationDelivery::STATUS_PENDING => 'queued',
            NotificationDelivery::STATUS_PROCESSING => 'in_progress',
            NotificationDelivery::STATUS_RETRYING => 'retry_scheduled',
            NotificationDelivery::STATUS_DELIVERED => 'accepted',
            NotificationDelivery::STATUS_FAILED_TERMINAL => 'failed',
            NotificationDelivery::STATUS_OUTCOME_UNKNOWN => 'unknown',
            NotificationDelivery::STATUS_SUPERSEDED => 'superseded',
            default => 'unknown',
        };
    }

    /** @return list<array{channel: string, status: string, count: int}> */
    private function channelSummaries(string $channelStatuses, int $mailCount, int $inAppCount): array
    {
        $grouped = [];
        foreach (array_unique(explode(',', $channelStatuses)) as $entry) {
            [$channel, $status] = array_pad(explode(':', $entry, 2), 2, '');
            $grouped[$channel]['statuses'][$status] = true;
        }

        $summaries = [];
        foreach (['mail' => $mailCount, 'in_app' => $inAppCount] as $channel => $count) {
            if ($count === 0) {
                continue;
            }
            $statuses = array_keys($grouped[$channel]['statuses'] ?? []);
            $status = count($statuses) !== 1 ? 'mixed' : NotificationDelivery::operationalStatus($channel, $statuses[0]);
            $summaries[] = ['channel' => $channel, 'status' => $status, 'count' => $count];
        }

        return $summaries;
    }

    private function summary(string $eventType): string
    {
        return match ($eventType) {
            'application.opening_alert.verify.v1' => 'Opening-alert email confirmation',
            'application.opening_alert.opened.v1' => 'Applications opening alert',
            'admission.accepted.v1' => 'Application accepted',
            'admission.dismissed.v1' => 'Application decision recorded',
            'admission.correction_requested.v1' => 'Application correction requested',
            'admission.document_correction_requested.v1' => 'Document correction request sent',
            'admission.submitted.v1' => 'Application receipt sent',
            'admission.new_submission.v1' => 'New application received',
            'admission.clearance.confirmed.v1' => 'Admission clearance confirmed',
            'exam.session_published.v1' => 'Examination schedule published',
            'exam.session_cancelled.v1' => 'Examination session cancelled',
            'exam.session_unpublished.v1' => 'Examination schedule withdrawn',
            'exam.reminder.v1' => 'Examination reminder sent',
            'result.released.v1' => 'Results released',
            'result.unreleased.v1' => 'Result release withdrawn',
            'consultation.session_published.v1' => 'Release Day session scheduled',
            'consultation.slot_reserved.v1' => 'Release Day appointment reserved',
            'consultation.online_invited.v1' => 'Online consultation invitation sent',
            default => 'Notification occurrence',
        };
    }
}

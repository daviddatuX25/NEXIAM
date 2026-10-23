<?php

namespace App\Staff\Queries;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use App\Staff\Support\AuditLogEventCatalog;
use App\Support\Search\ModularSortScope;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class AuditLogProjection
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'custom_api_key',
        'access_key',
        'birthdate',
        'email',
        'contact_email',
        'phone',
        'contact_number',
        'mobile',
        'contact_phone',
        'totp_secret',
        'recovery_codes',
    ];

    /** @var list<string> */
    private const VISIBLE_PAYLOAD_FIELDS = [
        'institution_id', 'academic_cycle_id', 'attempt_id', 'reference_code', 'assignment_id',
        'exam_assignment_id', 'exam_session_id', 'session_id', 'inquiry_id', 'receipt_number',
        'operation_id', 'command_id', 'correlation_id', 'user_id', 'actor_id', 'program_id',
        'program_code', 'note_id', 'document_id', 'result_document_id', 'print_job_id',
        'template_version_id', 'version', 'expected_version', 'status', 'prior_status',
        'new_status', 'status_unchanged', 'office', 'mode', 'channel', 'count', 'selective',
        'fields', 'identity_fields', 'attempt_fields', 'assignment_count', 'exam_eligible',
        'retake_required', 'first_name', 'middle_name', 'last_name', 'suffix',
        'before', 'after', 'old_values', 'new_values', 'before_identity', 'after_identity',
        'before_attempt', 'after_attempt', 'identity',
    ];

    /** @var list<string> */
    private const SEARCHABLE_PAYLOAD_FIELDS = [
        'reference_code', 'attempt_id', 'inquiry_id', 'receipt_number', 'session_id',
        'program_code', 'status',
    ];

    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /**
     * Build the authorized and filtered base query for audit events.
     *
     * @return Builder<AuditEvent>
     */
    public function query(Request $request): Builder
    {
        Validator::make($request->query(), [
            'category' => ['nullable', Rule::in(array_keys(AuditLogEventCatalog::DOMAINS))],
            'event' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:200'],
            'sort' => ['nullable', Rule::in(['created_at', 'actor', 'event_type'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'view_scope' => ['nullable', Rule::in(['office', 'own'])],
        ])->validate();

        $categoryFilter = $request->query('category');
        $eventFilter = $request->query('event');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $search = $request->query('search');

        $query = $this->visibleQuery($request);

        if ($categoryFilter) {
            $types = array_values(array_filter(
                $this->allEventTypes($request),
                static fn (string $type): bool => AuditLogEventCatalog::resolveDomain($type) === $categoryFilter,
            ));
            $query->whereIn('event_type', $types);
        }

        if ($eventFilter) {
            $query->where('event_type', $eventFilter);
        }

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom.' 00:00:00');
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo.' 23:59:59');
        }

        if ($search !== null && $search !== '') {
            $tokens = TokenizedInnerMatchScope::tokenize($search);

            foreach ($tokens as $token) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $token).'%';
                $institutionId = $request->user()?->actorScope()->institutionId;
                $matchingUserIds = User::query()
                    ->selectRaw('CAST(users.id AS TEXT)')
                    ->whereHas('staffProfile', fn (Builder $profiles) => $profiles->where('institution_id', $institutionId))
                    ->where(function (Builder $actors) use ($pattern): void {
                        $actors->whereRaw("LOWER(COALESCE(name, '')) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                            ->orWhereRaw("LOWER(COALESCE(email, '')) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
                    });

                $query->where(function (Builder $events) use ($pattern, $matchingUserIds, $token): void {
                    $events->whereRaw("LOWER(COALESCE(event_type, '')) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                        ->orWhereIn('actor', $matchingUserIds);
                    if (ctype_digit($token) || strtolower($token) === 'system') {
                        $events->orWhere('actor', strtolower($token));
                    }

                    foreach (self::SEARCHABLE_PAYLOAD_FIELDS as $field) {
                        $column = $events->getQuery()->getGrammar()->wrap('payload->'.$field);
                        $type = $events->getConnection()->getDriverName() === 'pgsql'
                            ? "json_typeof(payload -> '".$field."') IN ('string', 'number', 'boolean')"
                            : "json_type(payload, '$.".$field."') IN ('text', 'integer', 'real', 'true', 'false')";
                        $events->orWhere(function (Builder $facts) use ($column, $type, $pattern): void {
                            $facts->whereRaw($type)
                                ->whereRaw("LOWER(COALESCE(CAST({$column} AS TEXT), '')) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
                        });
                    }
                });
            }
        }

        ModularSortScope::apply(
            $query,
            $request->query('sort'),
            $request->query('dir'),
            [
                'created_at' => 'created_at',
                'event_type' => 'event_type',
                'actor' => 'actor',
            ],
            defaultKey: 'created_at',
            defaultDir: 'desc',
            tieBreakColumn: 'id',
        );

        return $query;
    }

    /**
     * @return list<string>
     */
    public function allEventTypes(Request $request): array
    {
        return $this->visibleQuery($request)->distinct()
            ->pluck('event_type')->filter()->values()->all();
    }

    /**
     * @return Builder<AuditEvent>
     */
    private function visibleQuery(Request $request): Builder
    {
        $query = AuditEvent::query();
        $user = $request->user();
        $scope = $user?->actorScope();
        $institutionId = $scope?->institutionId;

        if ($institutionId === null) {
            return $query->whereRaw('1 = 0');
        }

        $canSupervise = $this->authorizer->decideFresh($user, CapabilityCatalog::AUDIT_LOGS_VIEW, ['institution_id' => $institutionId])->allowed;
        $canViewOwn = $this->authorizer->decideFresh($user, CapabilityCatalog::AUDIT_LOGS_VIEW_OWN, ['institution_id' => $institutionId])->allowed;

        if (! $canSupervise && ! $canViewOwn) {
            return $query->whereRaw('1 = 0');
        }

        $query->where(function (Builder $events) use ($institutionId): void {
            $events->where('payload->institution_id', $institutionId)
                ->orWhere('payload->institution_id', (string) $institutionId)
                ->orWhereExists(function ($assoc) use ($institutionId): void {
                    $assoc->selectRaw('1')
                        ->from('audit_event_scope_associations')
                        ->where('audit_event_scope_associations.institution_id', $institutionId)
                        ->whereColumn('audit_event_scope_associations.audit_event_id', 'audit_events.id');
                });
        });

        $noteIdentity = $query->getQuery()->getGrammar()->wrap('payload->note_id');
        $query->where(function (Builder $events) use ($noteIdentity): void {
            $events->where('event_type', 'not like', 'advising_note.%')
                ->orWhereNotExists(function ($privateNotes) use ($noteIdentity): void {
                    $privateNotes->selectRaw('1')->from('program_advising_notes')
                        ->where('audience', 'private')
                        ->whereRaw('CAST(program_advising_notes.id AS TEXT) = CAST('.$noteIdentity.' AS TEXT)');
                });
        });

        $effectiveScope = $canSupervise ? (string) $request->query('view_scope', 'office') : 'own';

        if ($effectiveScope === 'own') {
            $actorId = (string) $user->id;
            $query->where('actor', $actorId);
        } elseif ($scope?->office === 'admin') {
            // Admin supervisory scope
        } else {
            $officePrefixes = match ($scope?->office) {
                'registrar' => ['registrar.', 'admission.', 'admissions.', 'application.', 'clearance.', 'ingestion.', 'enrollment.'],
                'guidance' => ['guidance.', 'exam.', 'examination.', 'assessment.', 'scoring.', 'grading.', 'omr.', 'release.', 'consultation.', 'direct_assessment.', 'staff.ta_'],
                default => [],
            };

            $query->where(function (Builder $events) use ($officePrefixes, $scope, $institutionId, $user): void {
                $events->where(function (Builder $sub) use ($officePrefixes, $scope): void {
                    foreach ($officePrefixes as $prefix) {
                        $sub->orWhere('event_type', 'like', $prefix.'%');
                    }
                    if ($scope?->office === 'registrar') {
                        $sub->orWhere('event_type', 'setup.application_suggestions_saved');
                    }
                })
                    ->orWhere(function (Builder $sub) use ($scope, $institutionId): void {
                        $sub->whereIn('event_type', ['auth.staff_login', 'auth.staff_logout', 'auth.staff_remembered_login'])
                            ->where(function (Builder $authSub) use ($scope, $institutionId): void {
                                $authSub->where('payload->office', $scope?->office)
                                    ->orWhereExists(function ($assoc) use ($scope, $institutionId): void {
                                        $assoc->selectRaw('1')
                                            ->from('audit_event_scope_associations')
                                            ->where('audit_event_scope_associations.institution_id', $institutionId)
                                            ->where('audit_event_scope_associations.office', $scope?->office)
                                            ->whereColumn('audit_event_scope_associations.audit_event_id', 'audit_events.id');
                                    });
                            });
                    })
                    ->orWhere('actor', (string) $user->id);
            });
        }

        return $query;
    }

    /**
     * @return array{
     *   as_of: string,
     *   filters: array{category: string|null, event: string|null, date_from: string|null, date_to: string|null, search: string|null, sort: string, dir: string, per_page: int},
     *   categories: array<string, array{label: string, icon: string, color: string}>,
     *   events: list<string>,
     *   total_filtered: int,
     *   pagination: array{
     *     current_page: int,
     *     last_page: int,
     *     per_page: int,
     *     total: int,
     *     from: int|null,
     *     to: int|null,
     *     prev_page_url: string|null,
     *     next_page_url: string|null
     *   },
     *   logs: list<array{
     *     id: string,
     *     event_type: string,
     *     title: string,
     *     domain: string,
     *     domain_meta: array{label: string, icon: string, color: string},
     *     summary: string,
     *     diff: list<array{field: string, label: string, old_value: mixed, new_value: mixed, kind: string}>,
     *     actor: string,
     *     actor_token: string,
     *     actor_info: array{
     *       kind: string,
     *       id: string,
     *       name: string,
     *       email: string|null,
     *       office: string|null,
     *       role_label: string,
     *       avatar: array{type: string, preset: string|null, initials: string}
     *     },
     *     payload: array<string, mixed>,
     *     prev_hash: string|null,
     *     event_hash: string|null,
     *     created_at: string,
     *     created_at_human: string,
     *     created_at_relative: string
     *   }>
     * }
     */
    public function for(Request $request): array
    {
        $query = $this->query($request);
        $allEventTypes = $this->allEventTypes($request);

        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));
        $paginator = $query->paginate($perPage)->withQueryString();
        $rawEvents = collect($paginator->items());

        // Batch actor resolution in a single query for this page
        $actorTokens = $rawEvents->pluck('actor')->filter()->unique()->values()->all();
        $users = $this->actorsFor($actorTokens, $request->user()?->actorScope()->institutionId);

        $logs = $rawEvents->map(function (AuditEvent $e) use ($users): array {
            $actorToken = (string) $e->actor;
            $actorInfo = $this->resolveActor($actorToken, $users->get($actorToken));

            $payload = is_array($e->payload) ? $e->payload : [];
            $domain = AuditLogEventCatalog::resolveDomain((string) $e->event_type);
            $domainMeta = AuditLogEventCatalog::DOMAINS[$domain] ?? AuditLogEventCatalog::DOMAINS['system'];
            $diff = AuditLogEventCatalog::extractDiff($payload);

            foreach ($diff as &$change) {
                if (! in_array(strtolower($change['field']), self::VISIBLE_PAYLOAD_FIELDS, true)
                    || in_array(strtolower($change['field']), self::SENSITIVE_KEYS, true)) {
                    $change['old_value'] = '[REDACTED]';
                    $change['new_value'] = '[REDACTED]';
                } else {
                    foreach (['old_value', 'new_value'] as $side) {
                        if (is_array($change[$side])) {
                            $change[$side] = $this->redact($change[$side]);
                        }
                    }
                }
            }
            unset($change);
            $payload = $this->redact($payload);

            return [
                'id' => (string) $e->id,
                'event_type' => (string) $e->event_type,
                'title' => AuditLogEventCatalog::resolveTitle((string) $e->event_type),
                'domain' => $domain,
                'domain_meta' => $domainMeta,
                'summary' => AuditLogEventCatalog::synthesizeSummary((string) $e->event_type, $payload),
                'diff' => $diff,
                'actor' => $actorInfo['name'],
                'actor_token' => $actorInfo['id'],
                'actor_info' => $actorInfo,
                'payload' => $payload,
                'prev_hash' => $e->prev_hash,
                'event_hash' => $e->event_hash,
                'created_at' => $e->created_at->toIso8601String(),
                'created_at_human' => $e->created_at->format('M j, Y h:i A'),
                'created_at_relative' => $e->created_at->diffForHumans(),
            ];
        })->all();
        $user = $request->user();
        $scope = $user?->actorScope();
        $institutionId = $scope?->institutionId;
        $canSupervise = $institutionId !== null && $this->authorizer->decideFresh($user, CapabilityCatalog::AUDIT_LOGS_VIEW, ['institution_id' => $institutionId])->allowed;
        $effectiveScope = $canSupervise ? (string) $request->query('view_scope', 'office') : 'own';
        $scopeOptions = $canSupervise
            ? [
                ['value' => 'office', 'label' => ($scope?->office === 'admin' ? 'Institutional Audit' : 'Office Audit')],
                ['value' => 'own', 'label' => 'My Actions Only'],
            ]
            : [
                ['value' => 'own', 'label' => 'My Actions Only'],
            ];

        return [
            'as_of' => now()->toIso8601String(),
            'filters' => [
                'category' => $request->query('category'),
                'event' => $request->query('event'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'search' => $request->query('search'),
                'sort' => $request->query('sort', 'created_at'),
                'dir' => $request->query('dir', 'desc'),
                'per_page' => $perPage,
                'view_scope' => $effectiveScope,
            ],
            'categories' => AuditLogEventCatalog::DOMAINS,
            'events' => $allEventTypes,
            'total_filtered' => $paginator->total(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
            'logs' => $logs,
            'scope_options' => $scopeOptions,
            'can_supervise' => $canSupervise,
        ];
    }

    /**
     * @param  list<string>  $actorTokens
     * @return Collection<int|string, User>
     */
    public function actorsFor(array $actorTokens, ?int $institutionId): Collection
    {
        $ids = array_values(array_filter($actorTokens, static fn (string $token): bool => ctype_digit($token)));
        if ($ids === [] || $institutionId === null) {
            return new Collection;
        }

        return User::query()->whereIn('id', $ids)
            ->where(function (Builder $actors) use ($institutionId): void {
                $actors->whereHas('staffProfile', fn (Builder $profiles) => $profiles->where('institution_id', $institutionId))
                    ->orWhereHas('applicantAccount.identity', fn (Builder $identities) => $identities->where('institution_id', $institutionId));
            })
            ->with(['staffProfile', 'applicantAccount'])
            ->get(['id', 'name', 'email'])
            ->keyBy(fn (User $user): string => (string) $user->id);
    }

    /**
     * @return array{
     *   kind: string,
     *   id: string,
     *   name: string,
     *   email: string|null,
     *   office: string|null,
     *   role_label: string,
     *   avatar: array{type: string, preset: string|null, initials: string}
     * }
     */
    public function resolveActor(string $actorToken, ?User $user): array
    {
        if ($actorToken === 'system') {
            return [
                'kind' => 'system',
                'id' => 'system',
                'name' => 'System Automated Process',
                'email' => null,
                'office' => 'system',
                'role_label' => 'Automated Process',
                'avatar' => [
                    'type' => 'preset',
                    'preset' => 'system',
                    'initials' => 'SYS',
                ],
            ];
        }

        if ($user !== null) {
            $profile = $user->staffProfile;
            $office = $profile?->office;
            $isApplicant = $profile === null && $user->applicantAccount !== null;
            $name = $profile !== null ? $user->name : ($isApplicant ? 'Applicant #'.$user->id : 'User #'.$user->id);
            $words = explode(' ', trim($name));
            $initials = collect($words)->map(fn ($word) => mb_substr($word, 0, 1))->take(2)->implode('');

            return [
                'kind' => $profile !== null ? 'staff' : ($isApplicant ? 'applicant' : 'unresolved'),
                'id' => (string) $user->id,
                'name' => $name,
                'email' => $profile !== null ? $user->email : null,
                'office' => $office,
                'role_label' => $profile !== null ? ucfirst($office).' Personnel (current profile)' : ($isApplicant ? 'Applicant' : 'Unresolved account'),
                'avatar' => [
                    'type' => $profile?->avatar_type ?? 'preset',
                    'preset' => $profile?->avatar_preset ?? 'records',
                    'initials' => strtoupper($initials ?: 'ST'),
                ],
            ];
        }

        $isNumeric = is_numeric($actorToken);

        return [
            'kind' => $isNumeric ? 'unresolved' : 'external',
            'id' => $isNumeric ? $actorToken : '[REDACTED]',
            'name' => $isNumeric ? "User #{$actorToken}" : 'External service',
            'email' => null,
            'office' => null,
            'role_label' => $isNumeric ? 'External or Former User' : 'Service Actor',
            'avatar' => [
                'type' => 'preset',
                'preset' => 'records',
                'initials' => $isNumeric ? 'US' : 'EX',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redact(array $payload): array
    {
        $sensitive = self::SENSITIVE_KEYS;
        foreach ($payload as $key => $value) {
            if (! is_int($key) && (! in_array(strtolower((string) $key), self::VISIBLE_PAYLOAD_FIELDS, true)
                || in_array(strtolower((string) $key), $sensitive, true))) {
                $payload[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->redact($value);
            }
        }

        return $payload;
    }
}

<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\CreateApplicantOfficeInquiry;
use App\Admission\Actions\MarkApplicantOfficeInquiryRead;
use App\Admission\Actions\ResolveApplicantOfficeInquiry;
use App\Admission\Actions\SendApplicantOfficeInquiryMessage;
use App\Admission\Queries\ApplicantOfficeInquiryQuery;
use App\Admission\Queries\CaseConversationQuery;
use App\Admission\Queries\CaseConversationStartQuery;
use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\CaseConversationToken;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\OfficeInquiries\CreateApplicantOfficeInquiryRequest;
use App\Http\Requests\Admission\OfficeInquiries\MarkApplicantOfficeInquiryReadRequest;
use App\Http\Requests\Admission\OfficeInquiries\ResolveApplicantOfficeInquiryRequest;
use App\Http\Requests\Admission\OfficeInquiries\SendApplicantOfficeInquiryMessageRequest;
use App\Http\Requests\Admission\OfficeInquiries\SnapshotApplicantOfficeInquiryRequest;
use App\Models\Admission\RecordThread;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApplicantOfficeInquiryController extends Controller
{
    public function __construct(
        private readonly ApplicantOfficeInquiryQuery $query,
        private readonly ApplicantOfficeInquiryAccess $access,
        private readonly CaseConversationQuery $caseConversations,
        private readonly CaseConversationStartQuery $caseConversationStart,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    public function applicantIndex(Request $request): Response
    {
        if ($request->user() === null) {
            return $this->error('session_expired', 'Sign in again to continue.', 401);
        }

        $availability = $this->access->availability($request->user());
        if (! $availability['available']) {
            return Inertia::render('Applicant/Inquiries/Index', [
                'inquiries' => [],
                'next_cursor' => null,
                'destinations' => [],
                'initial' => null,
                'staff_mode' => false,
                'office_label' => '',
                'synthetic_only' => true,
                'unavailable' => $availability,
                'principal_key' => '',
                'attempt_key' => '',
                'urls' => [
                    'index' => route('applicant.inquiries.index', [], false),
                    'create' => null,
                    'show' => [],
                    'snapshot' => null,
                    'send' => null,
                    'read' => null,
                    'resolve' => null,
                ],
            ])->toResponse($request)->header('Cache-Control', 'private, no-store');
        }

        $params = ['group' => 'offices'];
        $search = $request->query('office_search') ?? $request->query('search');
        if (is_string($search) && trim($search) !== '') {
            $params['office_search'] = trim($search);
        }
        $cursor = $request->query('office_cursor') ?? $request->query('before_id');
        if (is_string($cursor) && trim($cursor) !== '') {
            $params['office_cursor'] = trim($cursor);
        }

        return redirect()->route('applicant.admission-support', $params)
            ->withHeaders(['Cache-Control' => 'private, no-store']);
    }

    public function staffIndex(Request $request): Response
    {
        return $this->staffPage($request);
    }

    public function applicantShow(Request $request, string $inquiry): Response
    {
        if ($request->user() === null) {
            return $this->error('session_expired', 'Sign in again to continue.', 401);
        }

        return $this->page($request, false, $inquiry);
    }

    public function staffShow(Request $request, string $inquiry): Response
    {
        return $this->staffPage($request, $inquiry);
    }

    private function staffPage(Request $request, ?string $inquiry = null): Response
    {
        try {
            $actor = $request->user();
            abort_unless($actor instanceof User, 404);

            $officeAvailable = $this->access->isAvailable($actor);
            $caseAvailable = $this->authorizer->decideFresh($actor, CapabilityCatalog::RECORD_THREAD_PARTICIPATE)->allowed;
            $groupsAvailable = [
                'case_threads' => $caseAvailable,
                'offices' => $officeAvailable,
            ];

            $requestedGroup = $request->query('group');
            $threadId = $request->query('thread_id');
            $inquiryId = $request->query('inquiry_id');
            if (($requestedGroup !== null && ! in_array($requestedGroup, ['case_threads', 'offices'], true))
                || ($threadId !== null && ! is_string($threadId))
                || ($inquiryId !== null && ! is_string($inquiryId))
                || ($threadId !== null && $inquiryId !== null)
                || ($threadId !== null && $inquiry !== null)
                || ($inquiryId !== null && $inquiry !== null)
                || ($requestedGroup === 'case_threads' && ($inquiryId !== null || $inquiry !== null || $request->query('office_search') !== null || $request->query('office_cursor') !== null))
                || ($requestedGroup === 'offices' && ($threadId !== null || $request->query('case_search') !== null || $request->query('case_cursor') !== null))) {
                abort(404);
            }

            $programHead = $this->authorizer->decideFresh($actor, CapabilityCatalog::PROGRAM_HEAD_VIEW)->allowed;
            $group = is_string($requestedGroup)
                ? $requestedGroup
                : (($programHead || ($caseAvailable && ! $officeAvailable)) ? 'case_threads' : 'offices');
            if (($group === 'case_threads' && ($inquiryId !== null || $request->query('office_search') !== null || $request->query('office_cursor') !== null || $request->query('search') !== null))
                || ($group === 'offices' && ($threadId !== null || $request->query('case_search') !== null || $request->query('case_cursor') !== null))) {
                abort(404);
            }
            if ($inquiry !== null && $group === 'case_threads') {
                abort(404);
            }
            abort_unless($groupsAvailable[$group], 404);

            if ($group === 'case_threads') {
                $search = $request->query('case_search');
                $cursor = $request->query('case_cursor');
                abort_if(($search !== null && ! is_string($search)) || ($cursor !== null && ! is_string($cursor)), 404);
                $programCode = $request->query('program_code');
                $cycleCode = $request->query('cycle_code');
                abort_if(($programCode !== null && (! is_string($programCode) || $programCode === ''))
                    || ($cycleCode !== null && (! is_string($cycleCode) || $cycleCode === '')), 404);
                if ($programCode !== null || $cycleCode !== null) {
                    $scope = $actor->actorScope();
                    abort_unless($programHead && is_string($programCode)
                        && in_array($programCode, $scope->programCodes, true)
                        && ($cycleCode === null || $cycleCode === $scope->activeCycleCode), 404);
                    abort_unless(Program::query()->where('institution_id', $scope->institutionId)
                        ->where('code', $programCode)->where('is_active', true)->exists(), 404);
                    $cycleCode = $scope->activeCycleCode;
                }

                $selected = $threadId !== null
                    ? $this->caseConversations->selectedSummary($threadId, $actor, programCode: $programCode, cycleCode: $cycleCode)
                    : null;
                $resolveCasePayload = function () use ($actor, $programHead, $search, $cursor, $selected, $programCode, $cycleCode): array {
                    static $memo = null;
                    if ($memo !== null) {
                        return $memo;
                    }

                    $page = $programHead
                        ? $this->caseConversations->programHeadSummaries($actor, $search, $cursor, programCode: $programCode)
                        : $this->caseConversations->guidanceSummaries($actor, $search, $cursor);
                    $threadRows = array_map(static function (array $summary) use ($search, $cursor, $programCode, $cycleCode): array {
                        $params = [
                            'group' => 'case_threads',
                            'thread_id' => $summary['id'],
                        ];
                        if ($programCode !== null) {
                            $params['program_code'] = $programCode;
                            $params['cycle_code'] = $cycleCode;
                        }
                        if (is_string($search) && trim($search) !== '') {
                            $params['case_search'] = trim($search);
                        }
                        if (is_string($cursor) && trim($cursor) !== '') {
                            $params['case_cursor'] = trim($cursor);
                        }
                        $summary['show_url'] = route('staff.inquiries.index', $params, false);
                        $summary['case_record_url'] = route('admission-support.show', [
                            'attempt' => $summary['attempt_id'],
                            'program_code' => $summary['program_code'],
                            'cycle_code' => $summary['cycle_code'],
                            'tab' => 'threads',
                            'thread_id' => $summary['id'],
                            'thread_type' => $summary['thread_type'],
                        ], false);

                        return $summary;
                    }, $page['data']);

                    $selectedConversation = null;
                    if ($selected !== null) {
                        $thread = RecordThread::query()->findOrFail($selected['id']);
                        $selectedConversation = [
                            ...$this->caseConversations->forThread($thread, $actor),
                            'snapshot_url' => $selected['snapshot_url'],
                            'send_url' => $selected['send_url'],
                            'read_url' => $selected['read_url'],
                            'principal_key' => $selected['principal_key'],
                            'attempt_key' => $selected['attempt_key'],
                            'case_record_url' => route('admission-support.show', [
                                'attempt' => $selected['attempt_id'],
                                'program_code' => $selected['program_code'],
                                'cycle_code' => $selected['cycle_code'],
                                'tab' => 'threads',
                                'thread_id' => $selected['id'],
                                'thread_type' => $selected['thread_type'],
                            ], false),
                            'summary' => $selected,
                        ];
                    }

                    return $memo = [
                        'thread_rows' => $threadRows,
                        'next_cursor' => $page['pagination']['next_cursor'],
                        'selected_conversation' => $selectedConversation,
                        'attempt_key' => $selectedConversation['attempt_key'] ?? '',
                    ];
                };

                $caseIndexParams = ['group' => $group];
                if ($programCode !== null) {
                    $caseIndexParams['program_code'] = $programCode;
                    $caseIndexParams['cycle_code'] = $cycleCode;
                }
                if (is_string($search) && trim($search) !== '') {
                    $caseIndexParams['case_search'] = trim($search);
                }
                if (is_string($cursor) && trim($cursor) !== '') {
                    $caseIndexParams['case_cursor'] = trim($cursor);
                }

                return Inertia::render('Admission/Inquiries/'.($inquiry === null ? 'Index' : 'Show'), [
                    'group' => $group,
                    'groups_available' => $groupsAvailable,
                    'program_context' => $programCode === null ? null : ['program_code' => $programCode, 'cycle_code' => $cycleCode],
                    'start_context' => $programHead ? $this->caseConversationStart->context($actor) : null,
                    'thread_rows' => fn () => $resolveCasePayload()['thread_rows'],
                    'inquiries' => [],
                    'next_cursor' => fn () => $resolveCasePayload()['next_cursor'],
                    'search' => is_string($search) ? $search : '',
                    'selected_conversation' => fn () => $resolveCasePayload()['selected_conversation'],
                    'initial' => null,
                    'staff_mode' => true,
                    'principal_key' => CaseConversationToken::presentationContext($actor),
                    'attempt_key' => fn () => $resolveCasePayload()['attempt_key'],
                    'urls' => [
                        'index' => route('staff.inquiries.index', $caseIndexParams, false),
                        'show' => [],
                    ],
                ])->toResponse($request)->header('Cache-Control', 'private, no-store');

            }

            abort_unless($officeAvailable, 404);

            $officeRouteId = $inquiryId ?? $inquiry;

            return $this->page($request, true, is_string($officeRouteId) ? $officeRouteId : null);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function snapshot(SnapshotApplicantOfficeInquiryRequest $request, string $inquiry): Response
    {
        $input = $request->validated();

        return $this->respond(fn (): array => $this->query->snapshot($request->user(), $this->id($inquiry), $input['after_sequence'] ?? null, $input['before_sequence'] ?? null));
    }

    public function store(CreateApplicantOfficeInquiryRequest $request): Response
    {
        $input = $request->validated();

        return $this->respond(function () use ($request, $input): array {
            $receipt = CreateApplicantOfficeInquiry::handle($request->user(), $input['office'], $input['subject'], $input['body'], $input['idempotency_key']);
            $receipt['redirect_url'] = route('applicant.admission-support', [
                'group' => 'offices',
                'inquiry_id' => $receipt['inquiry_id'],
            ], false);

            return $receipt;
        });
    }

    public function send(SendApplicantOfficeInquiryMessageRequest $request, string $inquiry): Response
    {
        $input = $request->validated();

        return $this->respond(function () use ($request, $inquiry, $input): array {
            $receipt = SendApplicantOfficeInquiryMessage::handle($request->user(), $this->id($inquiry), $input['body'], $input['idempotency_key']);
            $message = $this->query->message($request->user(), $this->id($inquiry), (int) $receipt['message_id']);

            return [
                'message' => $message,
                'replayed' => $receipt['replayed'],
                'as_of' => now()->toISOString(),
            ];
        });
    }

    public function read(MarkApplicantOfficeInquiryReadRequest $request, string $inquiry): Response
    {
        $input = $request->validated();

        return $this->respond(fn (): array => MarkApplicantOfficeInquiryRead::handle($request->user(), $this->id($inquiry), $input['observed_sequence'], $input['observation_token']));
    }

    public function resolve(ResolveApplicantOfficeInquiryRequest $request, string $inquiry): Response
    {
        $input = $request->validated();

        return $this->respond(fn (): array => ResolveApplicantOfficeInquiry::handle($request->user(), $this->id($inquiry), $input['idempotency_key']));
    }

    private function page(Request $request, bool $staff, ?string $inquiry = null): Response
    {
        try {
            if (! $staff && $inquiry === null) {
                $availability = $this->access->availability($request->user());
                if (! $availability['available']) {
                    return Inertia::render('Applicant/Inquiries/Index', [
                        'inquiries' => [],
                        'next_cursor' => null,
                        'destinations' => [],
                        'initial' => null,
                        'staff_mode' => false,
                        'office_label' => '',
                        'synthetic_only' => true,
                        'unavailable' => $availability,
                        'principal_key' => '',
                        'attempt_key' => '',
                        'urls' => [
                            'index' => route('applicant.inquiries.index', [], false),
                            'create' => null,
                            'show' => [],
                            'snapshot' => null,
                            'send' => null,
                            'read' => null,
                            'resolve' => null,
                        ],
                    ])->toResponse($request)->header('Cache-Control', 'private, no-store');
                }
            }

            $beforeId = $request->query('office_cursor') ?? $request->query('before_id');
            $search = $request->query('office_search') ?? $request->query('search');
            if ($inquiry !== null) {
                $this->id($inquiry);
            }
            $resolveOfficePayload = function () use ($request, $staff, $inquiry, $beforeId, $search): array {
                static $memo = null;
                if ($memo !== null) {
                    return $memo;
                }

                $list = $this->query->listFor(
                    $request->user(),
                    is_string($beforeId) ? $beforeId : null,
                    is_string($search) ? $search : null,
                );
                if ($staff !== $list['staff_mode']) {
                    throw new RuntimeException('office_inquiry_not_found');
                }
                $initial = $inquiry === null ? null : $this->query->snapshot($request->user(), $this->id($inquiry));
                $destinations = $staff ? [] : $this->query->options($request->user())['destinations'];
                $prefix = $staff ? 'staff.inquiries' : 'applicant.inquiries';
                $returnUrl = null;
                if (! $staff) {
                    if ($request->filled('return_url')) {
                        $returnUrl = (string) $request->query('return_url');
                    } else {
                        $returnParams = ['group' => 'offices'];
                        if (is_string($search) && trim($search) !== '') {
                            $returnParams['office_search'] = trim($search);
                        }
                        if (is_string($beforeId) && trim($beforeId) !== '') {
                            $returnParams['office_cursor'] = trim($beforeId);
                        }
                        $returnUrl = route('applicant.admission-support', $returnParams, false);
                    }
                }
                $rows = array_map(static function (array $row) use ($prefix, $staff, $returnUrl, $search, $beforeId): array {
                    $params = ['inquiry' => $row['id']];
                    if ($staff) {
                        $params['group'] = 'offices';
                        if (is_string($search) && trim($search) !== '') {
                            $params['office_search'] = trim($search);
                        }
                        if (is_string($beforeId) && trim($beforeId) !== '') {
                            $params['office_cursor'] = trim($beforeId);
                        }
                    }
                    if (! $staff && $returnUrl !== null) {
                        $params['return_url'] = $returnUrl;
                    }
                    $row['show_url'] = route($prefix.'.show', $params, false);

                    return $row;
                }, $list['inquiries']);

                return $memo = [
                    'list' => $list,
                    'initial' => $initial,
                    'destinations' => $destinations,
                    'rows' => $rows,
                    'return_url' => $returnUrl,
                    'attempt_key' => $initial['attempt_key'] ?? $list['attempt_key'],
                ];
            };
            $staffIndexParams = ['group' => 'offices'];
            if (is_string($search) && trim($search) !== '') {
                $staffIndexParams['office_search'] = trim($search);
            }
            if (is_string($beforeId) && trim($beforeId) !== '') {
                $staffIndexParams['office_cursor'] = trim($beforeId);
            }
            $prefix = $staff ? 'staff.inquiries' : 'applicant.inquiries';
            $indexUrl = route($prefix.'.index', $staff ? $staffIndexParams : [], false);

            return Inertia::render(($staff ? 'Admission' : 'Applicant').'/Inquiries/'.($inquiry === null ? 'Index' : 'Show'), [
                'group' => $staff ? 'offices' : null,
                'groups_available' => fn () => $staff ? [
                    'case_threads' => $this->authorizer->decideFresh($request->user(), CapabilityCatalog::RECORD_THREAD_PARTICIPATE)->allowed,
                    'offices' => $this->access->isAvailable($request->user()),
                ] : [],
                'inquiries' => fn () => $resolveOfficePayload()['rows'],
                'thread_rows' => [],
                'next_cursor' => fn () => $resolveOfficePayload()['list']['next_cursor'],
                'destinations' => fn () => $resolveOfficePayload()['destinations'],
                'initial' => fn () => $resolveOfficePayload()['initial'],
                'selected_conversation' => null,
                'staff_mode' => $staff,
                'office_label' => fn () => $resolveOfficePayload()['list']['office_label'],
                'search' => is_string($search) ? $search : '',
                'synthetic_only' => true,
                'principal_key' => CaseConversationToken::presentationContext($request->user()),
                'unavailable' => null,
                'attempt_key' => fn () => $resolveOfficePayload()['attempt_key'],
                'urls' => fn () => [
                    'index' => $indexUrl,
                    'return' => $resolveOfficePayload()['return_url'] ?? $indexUrl,
                    'create' => $staff ? null : route('applicant.inquiries.store', [], false),
                    'show' => array_column($resolveOfficePayload()['rows'], 'show_url', 'id'),
                    'snapshot' => $inquiry === null ? null : route('inquiries.snapshot', ['inquiry' => $inquiry], false),
                    'send' => $inquiry === null ? null : route('inquiries.messages.store', ['inquiry' => $inquiry], false),
                    'read' => $inquiry === null ? null : route('inquiries.read', ['inquiry' => $inquiry], false),
                    'resolve' => ! $staff || $inquiry === null ? null : route('inquiries.resolve', ['inquiry' => $inquiry], false),
                ],
                'return_url' => fn () => $resolveOfficePayload()['return_url'],
            ])->toResponse($request)->header('Cache-Control', 'private, no-store');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function id(string $value): int
    {
        if (preg_match('/^[1-9][0-9]{0,18}$/D', $value) !== 1 || (strlen($value) === 19 && strcmp($value, '9223372036854775807') > 0)) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        return (int) $value;
    }

    private function respond(\Closure $operation): JsonResponse
    {
        try {
            return response()->json($operation(), 200, ['Cache-Control' => 'private, no-store']);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function failure(Throwable $exception): JsonResponse
    {
        $code = $exception->getMessage();
        if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException || ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [403, 404], true)) || $code === 'office_inquiry_not_found') {
            return $this->error('not_found', 'Inquiry not found.', 404);
        }
        if ($exception instanceof ValidationException || $code === 'Invalid or expired observation token.') {
            return $this->error('validation_failed', 'The inquiry request is invalid. Refresh and try again.', 422);
        }
        if ($code === 'Idempotency key was already used with a different request payload.' || in_array($code, ['office_inquiry_closed', 'office_inquiry_conflict', 'office_inquiry_destination_unavailable'], true)) {
            return $this->error('inquiry_conflict', 'The inquiry changed or the operation key conflicts. Refresh and try again.', 409);
        }
        if ($code === 'A started session and application key are required.') {
            return $this->error('session_expired', 'Sign in again to continue.', 401);
        }
        report($exception);

        return $this->error('inquiry_unavailable', 'Inquiries are temporarily unavailable. Try again later.', 503);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $status, ['Cache-Control' => 'private, no-store']);
    }
}

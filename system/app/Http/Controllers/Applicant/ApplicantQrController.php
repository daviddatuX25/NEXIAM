<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Queries\ApplicantOfficeInquiryQuery;
use App\Admission\Queries\CaseConversationQuery;
use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\ApplicantQrCode;
use App\Admission\Support\CaseConversationToken;
use App\Ai\Knowledge\PublicFaqQuery;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\RecordThread;
use App\Models\Reports\ReportTemplate;
use App\Models\User;
use App\Reports\Services\AdmissionSlipPrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Applicant-facing adapter for the Test Day Pass, admission-support messages,
 * and read-only access history surfaces.
 *
 * Domain: Student / Applicant Experience.
 *
 * Responsibilities:
 * - Resolve the signed-in applicant's admission attempt through their applicant
 *   identity, rather than accepting an attempt identifier as proof of access.
 * - Issue the attempt-bound hall token through {@see ApplicantQrCode}.
 * - Render the applicant messages shell with independently authorized Program support
 *   and Office inquiry groups, rejecting cross-domain leakage or ambiguous mixed selectors.
 * - Render bounded read-only access history of selected staff views for the authenticated
 *   applicant identity without logging new access events.
 */
class ApplicantQrController extends Controller
{
    public function show(Request $request): Response
    {
        $attempt = $this->attemptFor($request);

        if ($attempt === null) {
            return Inertia::render('Applicant/MyQr', ['page_state' => 'denied']);
        }

        $institutionId = $attempt->academicCycle?->institution_id;
        $template = ReportTemplate::query()
            ->where('surface_key', 'admission_slip')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('institution_id')->when($institutionId !== null, fn ($query) => $query->orWhere('institution_id', $institutionId)))
            ->when($institutionId !== null, fn ($query) => $query->orderByRaw('CASE WHEN institution_id = ? THEN 0 ELSE 1 END', [$institutionId]))
            ->latest('id')
            ->first();
        $entryUrl = ApplicantQrCode::entryUrl($attempt);

        return Inertia::render('Applicant/MyQr', [
            'page_state' => 'ready',
            'attempt_id' => $attempt->id,
            'qr' => $entryUrl,
            'locator' => ApplicantQrCode::issue($attempt),
            'entry_url' => $entryUrl,
            'print_url' => route('applicant.qr.print'),
            'admission_slip_template' => $template,
        ]);
    }

    public function print(Request $request, AdmissionSlipPrintService $service): \Symfony\Component\HttpFoundation\Response
    {
        $attempt = $this->attemptFor($request);
        abort_if($attempt === null, 404);

        return $service->renderResponse(
            $attempt,
            $request,
            route('applicant.qr.print', ['format' => 'pdf'])
        );
    }

    public function admissionSupport(
        Request $request,
        CaseConversationQuery $caseQuery,
        ApplicantOfficeInquiryQuery $officeQuery,
        ApplicantOfficeInquiryAccess $officeAccess,
        CapabilityAuthorizer $authorizer,
        PublicFaqQuery $faqQuery,
    ): Response {
        $actor = $request->user();
        if ($actor === null) {
            abort(401);
        }

        $account = $actor->applicantAccount()->first();
        $institutionId = (int) ($account?->applicantIdentity?->institution_id ?? 1);

        $hasApplicantAccount = $account !== null && $account->applicant_identity_id !== null;

        // Expose SAFE independent boolean availability. Do not infer from existing roots.
        $programSupportAvailable = $hasApplicantAccount
            && $authorizer->decideFresh($actor, CapabilityCatalog::RECORD_THREAD_PARTICIPATE)->allowed;
        $officesAvailable = $officeAccess->isAvailable($actor);

        $group = $request->query('group');
        $draftReason = $request->query('draft_reason');
        $draftReasons = [
            'exam_time_extension',
            'program_considering',
            'program_changed',
            'program_enrolled_elsewhere',
            'program_help',
        ];
        if ($draftReason !== null && (! is_string($draftReason) || ! in_array($draftReason, $draftReasons, true) || $group !== 'offices')) {
            abort(404, 'Thread not found.');
        }

        $caseSearch = $this->sanitizeSearch($request->query('case_search'));
        $caseCursor = $this->sanitizeCursor($request->query('case_cursor'));
        $threadId = $this->sanitizeSelector($request->query('thread_id'));

        $officeSearch = $this->sanitizeSearch($request->query('office_search'));
        $officeCursor = $this->sanitizeCursor($request->query('office_cursor'));
        $inquiryId = $this->sanitizeSelector($request->query('inquiry_id'));
        if ($draftReason !== null && $inquiryId !== null) {
            abort(404, 'Thread not found.');
        }

        // Reject ambiguous mixed selectors opaquely.
        if ($threadId !== null && $inquiryId !== null) {
            abort(404, 'Thread not found.');
        }

        if ($group !== null) {
            if (! in_array($group, ['program_support', 'offices'], true)) {
                abort(404, 'Thread not found.');
            }

            if ($group === 'program_support' && ($inquiryId !== null || $officeSearch !== null || $officeCursor !== null)) {
                abort(404, 'Thread not found.');
            }

            if ($group === 'offices' && ($threadId !== null || $caseSearch !== null || $caseCursor !== null)) {
                abort(404, 'Thread not found.');
            }
        } else {
            $hasCaseParams = $threadId !== null || $caseSearch !== null || $caseCursor !== null;
            $hasOfficeParams = $inquiryId !== null || $officeSearch !== null || $officeCursor !== null;
            if ($hasCaseParams && $hasOfficeParams) {
                abort(404, 'Thread not found.');
            }
        }

        // Determine active group.
        if ($group === 'program_support' || $group === 'offices') {
            $activeGroup = $group;
        } elseif ($threadId !== null || $caseSearch !== null || $caseCursor !== null) {
            $activeGroup = 'program_support';
        } elseif ($inquiryId !== null || $officeSearch !== null || $officeCursor !== null) {
            $activeGroup = 'offices';
        } else {
            $activeGroup = $programSupportAvailable
                ? 'program_support'
                : ($officesAvailable ? 'offices' : 'program_support');
        }

        $principalKey = '';
        try {
            $principalKey = CaseConversationToken::presentationContext($actor);
        } catch (\Throwable) {
            $principalKey = '';
        }

        $groupsAvailable = [
            'program_support' => $programSupportAvailable,
            'offices' => $officesAvailable,
        ];

        if ($activeGroup === 'program_support') {
            return $this->renderProgramSupport(
                $request,
                $actor,
                $caseQuery,
                $groupsAvailable,
                $programSupportAvailable,
                $principalKey,
                $caseSearch,
                $caseCursor,
                $threadId,
                $institutionId,
                $faqQuery,
            );
        }

        return $this->renderOffices(
            $request,
            $actor,
            $officeQuery,
            $officeAccess,
            $groupsAvailable,
            $officesAvailable,
            $principalKey,
            $officeSearch,
            $officeCursor,
            $inquiryId,
            $institutionId,
            $faqQuery,
            $draftReason,
        );
    }

    private function renderProgramSupport(
        Request $request,
        User $actor,
        CaseConversationQuery $caseQuery,
        array $groupsAvailable,
        bool $programSupportAvailable,
        string $principalKey,
        ?string $caseSearch,
        ?string $caseCursor,
        ?string $threadId,
        int $institutionId,
        PublicFaqQuery $faqQuery,
    ): Response {
        if (! $programSupportAvailable) {
            if ($threadId !== null) {
                abort(404, 'Thread not found.');
            }

            return Inertia::render('Applicant/AdmissionSupport', [
                'faqs' => $faqQuery->getPublicFaqs($institutionId),
                'faq_categories' => $faqQuery->getCategories(),
                'active_group' => 'program_support',
                'groups_available' => $groupsAvailable,
                'cases' => [],
                'case_selected' => null,
                'case_snapshot' => null,
                'case_search' => $caseSearch,
                'case_cursor' => $caseCursor,
                'next_case_cursor' => null,
                'has_more' => false,
                'principal_key' => $principalKey,
                'attempt_key' => '',
                'urls' => [
                    'index' => route('applicant.admission-support', ['group' => 'program_support'], false),
                    'access_history' => route('applicant.access-history', [], false),
                    'snapshot' => null,
                    'send' => null,
                    'read' => null,
                ],
                'snapshotUrl' => null,
                'sendUrl' => null,
                'readUrl' => null,
            ]);
        }

        $resolveCasePayload = function () use ($request, $actor, $caseQuery, $caseSearch, $caseCursor, $threadId): array {
            static $memo = null;
            if ($memo !== null) {
                return $memo;
            }

            $summariesResult = $caseQuery->applicantSummaries($actor, $caseSearch, $caseCursor, 20);
            $cases = $summariesResult['data'];
            $selectedSummary = null;
            $snapshot = null;
            $attemptKey = '';

            if ($threadId !== null) {
                try {
                    $selectedSummary = $caseQuery->selectedSummary($threadId, $actor, RecordThread::TYPE_APPLICANT_SUPPORT);
                    $threadModel = RecordThread::query()->find($threadId);
                    if ($threadModel === null) {
                        abort(404, 'Thread not found.');
                    }
                    $snapshot = $caseQuery->forThread($threadModel, $actor);
                    $attemptKey = (string) ($snapshot['attempt_key'] ?? '');
                } catch (HttpExceptionInterface $e) {
                    throw $e;
                } catch (\Throwable) {
                    abort(404, 'Thread not found.');
                }
            } elseif ($cases !== []) {
                $attemptKey = (string) ($cases[0]['attempt_key'] ?? '');
            } else {
                $attempt = $this->attemptFor($request);
                $attemptKey = $attempt !== null ? json_encode([
                    'case',
                    (string) $attempt->institution_id,
                    (string) $attempt->id,
                    (string) ($attempt->academicCycle?->code ?? ''),
                    'applicant',
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : '';
            }

            return $memo = [
                'cases' => $cases,
                'case_selected' => $selectedSummary,
                'case_snapshot' => $snapshot,
                'next_case_cursor' => $summariesResult['pagination']['next_cursor'],
                'has_more' => (bool) $summariesResult['pagination']['has_more'],
                'attempt_key' => $attemptKey,
            ];
        };

        $snapshotUrl = $threadId !== null ? route('case-conversations.show', ['thread' => $threadId], false) : null;
        $sendUrl = $threadId !== null ? route('case-conversations.messages.store', ['thread' => $threadId], false) : null;
        $readUrl = $threadId !== null ? route('case-conversations.read', ['thread' => $threadId], false) : null;

        return Inertia::render('Applicant/AdmissionSupport', [
            'faqs' => $faqQuery->getPublicFaqs($institutionId),
            'faq_categories' => $faqQuery->getCategories(),
            'active_group' => 'program_support',
            'groups_available' => $groupsAvailable,
            'cases' => fn () => $resolveCasePayload()['cases'],
            'case_selected' => fn () => $resolveCasePayload()['case_selected'],
            'case_snapshot' => fn () => $resolveCasePayload()['case_snapshot'],
            'case_search' => $caseSearch,
            'case_cursor' => $caseCursor,
            'next_case_cursor' => fn () => $resolveCasePayload()['next_case_cursor'],
            'has_more' => fn () => $resolveCasePayload()['has_more'],
            'principal_key' => $principalKey,
            'attempt_key' => fn () => $resolveCasePayload()['attempt_key'],
            'urls' => [
                'index' => route('applicant.admission-support', ['group' => 'program_support'], false),
                'access_history' => route('applicant.access-history', [], false),
                'snapshot' => $snapshotUrl,
                'send' => $sendUrl,
                'read' => $readUrl,
            ],
            'snapshotUrl' => $snapshotUrl,
            'sendUrl' => $sendUrl,
            'readUrl' => $readUrl,
        ]);

    }

    private function renderOffices(
        Request $request,
        User $actor,
        ApplicantOfficeInquiryQuery $officeQuery,
        ApplicantOfficeInquiryAccess $officeAccess,
        array $groupsAvailable,
        bool $officesAvailable,
        string $principalKey,
        ?string $officeSearch,
        ?string $officeCursor,
        ?string $inquiryId,
        int $institutionId,
        PublicFaqQuery $faqQuery,
        ?string $draftReason,
    ): Response {
        if (! $officesAvailable) {
            if ($inquiryId !== null) {
                abort(404, 'Thread not found.');
            }

            return Inertia::render('Applicant/AdmissionSupport', [
                'faqs' => $faqQuery->getPublicFaqs($institutionId),
                'faq_categories' => $faqQuery->getCategories(),
                'active_group' => 'offices',
                'groups_available' => $groupsAvailable,
                'office_search' => $officeSearch,
                'office_cursor' => $officeCursor,
                'inquiries' => [],
                'next_cursor' => null,
                'destinations' => [],
                'initial' => null,
                'staff_mode' => false,
                'office_label' => '',
                'synthetic_only' => true,
                'unavailable' => $officeAccess->availability($actor),
                'principal_key' => $principalKey,
                'draft_reason' => $draftReason,
                'attempt_key' => '',
                'urls' => [
                    'index' => route('applicant.admission-support', ['group' => 'offices'], false),
                    'access_history' => route('applicant.access-history', [], false),
                    'create' => null,
                    'show' => [],
                    'snapshot' => null,
                    'send' => null,
                    'read' => null,
                    'resolve' => null,
                ],
                'inquiry_draft' => null,
            ]);
        }

        $resolveOfficePayload = function () use ($actor, $officeQuery, $officeCursor, $officeSearch, $inquiryId): array {
            static $memo = null;
            if ($memo !== null) {
                return $memo;
            }

            try {
                $list = $officeQuery->listFor($actor, $officeCursor, $officeSearch);
                if ($list['staff_mode'] !== false) {
                    throw new \RuntimeException('office_inquiry_not_found');
                }
                $initial = null;
                if ($inquiryId !== null) {
                    if (! preg_match('/^[1-9][0-9]{0,18}$/D', $inquiryId)) {
                        abort(404, 'Thread not found.');
                    }
                    $initial = $officeQuery->snapshot($actor, (int) $inquiryId);
                }
            } catch (HttpExceptionInterface $e) {
                throw $e;
            } catch (\Throwable) {
                abort(404, 'Thread not found.');
            }

            $rows = array_map(static function (array $row): array {
                $row['show_url'] = route('applicant.inquiries.show', ['inquiry' => $row['id']], false);

                return $row;
            }, $list['inquiries']);
            try {
                $destinations = $officeQuery->options($actor)['destinations'];
            } catch (\Throwable) {
                $destinations = [];
            }

            return $memo = [
                'list' => $list,
                'initial' => $initial,
                'rows' => $rows,
                'destinations' => $destinations,
                'attempt_key' => $initial['attempt_key'] ?? $list['attempt_key'] ?? '',
            ];
        };

        return Inertia::render('Applicant/AdmissionSupport', [
            'faqs' => $faqQuery->getPublicFaqs($institutionId),
            'faq_categories' => $faqQuery->getCategories(),
            'active_group' => 'offices',
            'groups_available' => $groupsAvailable,
            'office_search' => $officeSearch,
            'office_cursor' => $officeCursor,
            'inquiries' => fn () => $resolveOfficePayload()['rows'],
            'next_cursor' => fn () => $resolveOfficePayload()['list']['next_cursor'],
            'destinations' => fn () => $resolveOfficePayload()['destinations'],
            'initial' => fn () => $resolveOfficePayload()['initial'],
            'staff_mode' => false,
            'office_label' => fn () => (string) $resolveOfficePayload()['list']['office_label'],
            'synthetic_only' => true,
            'unavailable' => null,
            'principal_key' => $principalKey,
            'inquiry_draft' => function () use ($draftReason, $principalKey, $resolveOfficePayload): ?array {
                $draft = match ($draftReason) {
                    'exam_time_extension' => [
                        'office' => 'guidance',
                        'subject' => 'Question about exam timing',
                        'body' => 'I would like to ask about an exam timing concern. Please review my request.',
                    ],
                    'program_considering' => [
                        'office' => 'guidance',
                        'subject' => 'Program plan update',
                        'body' => 'I am considering a change to my program plan. Please advise me about the current official program-choice process.',
                    ],
                    'program_changed' => [
                        'office' => 'registrar',
                        'subject' => 'Program record correction request',
                        'body' => 'I chose a different program and would like help reconciling my NEXIAM record. This is a report, not an official program change.',
                    ],
                    'program_enrolled_elsewhere' => [
                        'office' => 'registrar',
                        'subject' => 'Program enrollment update',
                        'body' => 'I have enrolled in a different program. Please review whether my NEXIAM admission record needs a correction. This is an applicant report, not a verified enrollment record.',
                    ],
                    'program_help' => [
                        'office' => 'guidance',
                        'subject' => 'Help with program plans',
                        'body' => 'I would like help deciding what to do about my program plan.',
                    ],
                    default => null,
                };
                if ($draft === null) {
                    return null;
                }
                $payload = $resolveOfficePayload();
                if ($payload['attempt_key'] === ''
                    || ! in_array($draft['office'], array_column($payload['destinations'], 'code'), true)) {
                    return null;
                }

                return [
                    'reason' => (string) $draftReason,
                    'principal_key' => $principalKey,
                    'attempt_key' => (string) $payload['attempt_key'],
                    ...$draft,
                ];
            },
            'draft_reason' => $draftReason,
            'attempt_key' => fn () => (string) $resolveOfficePayload()['attempt_key'],
            'urls' => fn () => [
                'index' => route('applicant.admission-support', ['group' => 'offices'], false),
                'access_history' => route('applicant.access-history', [], false),
                'create' => route('applicant.inquiries.store', [], false),
                'show' => array_column($resolveOfficePayload()['rows'], 'show_url', 'id'),
                'snapshot' => $inquiryId !== null ? route('inquiries.snapshot', ['inquiry' => $inquiryId], false) : null,
                'send' => $inquiryId !== null ? route('inquiries.messages.store', ['inquiry' => $inquiryId], false) : null,
                'read' => $inquiryId !== null ? route('inquiries.read', ['inquiry' => $inquiryId], false) : null,
                'resolve' => null,
            ],
        ]);
    }

    private function sanitizeSearch(mixed $search): ?string
    {
        if (! is_string($search)) {
            return null;
        }

        $trimmed = trim($search);
        if ($trimmed === '') {
            return null;
        }

        return mb_strlen($trimmed) > 120 ? mb_substr($trimmed, 0, 120) : $trimmed;
    }

    private function sanitizeCursor(mixed $cursor): ?string
    {
        if (! is_string($cursor)) {
            return null;
        }

        $trimmed = trim($cursor);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function sanitizeSelector(mixed $selector): ?string
    {
        if (! is_string($selector)) {
            return null;
        }

        $trimmed = trim($selector);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function attemptFor(Request $request): ?AdmissionAttempt
    {
        $account = $request->user()?->applicantAccount()->first();

        if ($account === null) {
            return null;
        }

        return AdmissionAttempt::query()
            ->where('applicant_identity_id', $account->applicant_identity_id)
            ->latest('id')
            ->first();
    }

    private static function isoOrNull(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }
}

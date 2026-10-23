<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\CreateApplicantOfficeInquiry;
use App\Admission\Actions\MarkApplicantOfficeInquiryRead;
use App\Admission\Actions\SendApplicantOfficeInquiryMessage;
use App\Admission\Queries\ApplicantOfficeInquiryQuery;
use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\CaseConversationToken;
use App\Admission\Support\InquirySubjectPresets;
use App\Ai\Knowledge\PublicFaqQuery;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class PublicOfficeInquiryController extends Controller
{
    public function __construct(
        private readonly ApplicantOfficeInquiryQuery $query,
        private readonly ApplicantOfficeInquiryAccess $access,
        private readonly PublicFaqQuery $faqQuery,
    ) {}

    public function modal(Request $request): JsonResponse
    {
        try {
            $faqs = $this->faqQuery->getPublicFaqs();
            $categories = $this->faqQuery->getCategories();
            $selectedId = $request->filled('inquiry_id') ? $this->id((string) $request->query('inquiry_id')) : null;

            $user = $request->user();
            if ($user instanceof User && $user->applicantAccount()->exists() && $this->access->isAvailable($user)) {
                $list = $this->query->listFor($user);
                $destinations = $this->query->options($user)['destinations'];
                $targetId = $selectedId ?? (isset($list['inquiries'][0]['id']) ? (int) $list['inquiries'][0]['id'] : null);
                $initial = $targetId !== null ? $this->query->snapshot($user, $targetId) : null;
                $rows = array_map(static function (array $row): array {
                    $row['show_url'] = route('applicant.admission-support', ['group' => 'offices', 'inquiry_id' => $row['id']], false);

                    return $row;
                }, $list['inquiries']);

                return response()->json([
                    'faqs' => $faqs,
                    'categories' => $categories,
                    'authenticated_applicant' => true,
                    'device_token' => null,
                    'inquiry_state' => [
                        'available' => true,
                        'destinations' => $destinations,
                        'subject_presets' => InquirySubjectPresets::catalog(),
                        'inquiries' => $rows,
                        'initial' => $initial,
                        'principal_key' => CaseConversationToken::presentationContext($user),
                        'attempt_key' => $initial['attempt_key'] ?? $list['attempt_key'],
                        'synthetic_only' => true,
                        'urls' => [
                            'index' => route('applicant.admission-support', ['group' => 'offices'], false),
                            'create' => route('applicant.inquiries.store', [], false),
                            'snapshot' => $targetId === null ? null : route('inquiries.snapshot', ['inquiry' => $targetId], false),
                            'send' => $targetId === null ? null : route('inquiries.messages.store', ['inquiry' => $targetId], false),
                            'read' => $targetId === null ? null : route('inquiries.read', ['inquiry' => $targetId], false),
                            'snapshot_template' => '/inquiries/{inquiry}/snapshot',
                            'send_template' => '/inquiries/{inquiry}/messages',
                            'read_template' => '/inquiries/{inquiry}/read',
                            'clear_device' => null,
                        ],
                    ],
                ], 200, ['Cache-Control' => 'private, no-store']);
            }

            [$rawToken, $tokenHash] = $this->resolveOrIssueDeviceToken($request);
            $state = $this->query->preApplicationState($tokenHash, $selectedId);
            $activeId = $state['initial']['thread']['id'] ?? null;

            $response = response()->json([
                'faqs' => $faqs,
                'categories' => $categories,
                'authenticated_applicant' => false,
                'device_token' => $rawToken,
                'inquiry_state' => [
                    ...$state,
                    'urls' => [
                        'index' => route('public.inquiries.modal', [], false),
                        'create' => route('public.inquiries.store', [], false),
                        'snapshot' => $activeId === null ? null : route('public.inquiries.snapshot', ['inquiry' => $activeId], false),
                        'send' => $activeId === null ? null : route('public.inquiries.messages.store', ['inquiry' => $activeId], false),
                        'read' => $activeId === null ? null : route('public.inquiries.read', ['inquiry' => $activeId], false),
                        'snapshot_template' => '/public/inquiries/{inquiry}/snapshot',
                        'send_template' => '/public/inquiries/{inquiry}/messages',
                        'read_template' => '/public/inquiries/{inquiry}/read',
                        'clear_device' => route('public.inquiries.clear-device', [], false),
                    ],
                ],
            ], 200, ['Cache-Control' => 'private, no-store']);

            return $this->attachDeviceCookie($response, $rawToken);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $input = Validator::make($request->all(), [
                'office' => ['required', 'string', 'in:registrar,guidance'],
                'subject' => ['required', 'string', 'max:120'],
                'body' => ['required', 'string', 'max:4000'],
                'idempotency_key' => ['required', 'string', 'uuid'],
            ])->validate();

            [$rawToken, $tokenHash] = $this->resolveOrIssueDeviceToken($request);
            $receipt = CreateApplicantOfficeInquiry::handlePreApplication(
                $tokenHash,
                (string) $input['office'],
                (string) $input['subject'],
                (string) $input['body'],
                (string) $input['idempotency_key'],
            );
            $inquiryId = (int) $receipt['inquiry_id'];
            $snapshot = $this->query->snapshotPreApplication($tokenHash, $inquiryId);

            $response = response()->json([
                ...$receipt,
                'device_token' => $rawToken,
                'snapshot' => $snapshot,
                'urls' => [
                    'snapshot' => route('public.inquiries.snapshot', ['inquiry' => $inquiryId], false),
                    'send' => route('public.inquiries.messages.store', ['inquiry' => $inquiryId], false),
                    'read' => route('public.inquiries.read', ['inquiry' => $inquiryId], false),
                ],
            ], 200, ['Cache-Control' => 'private, no-store']);

            return $this->attachDeviceCookie($response, $rawToken);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function snapshot(Request $request, string $inquiry): JsonResponse
    {
        try {
            $input = Validator::make($request->query(), [
                'after_sequence' => ['nullable', 'string', 'max:32'],
                'before_sequence' => ['nullable', 'string', 'max:32'],
            ])->validate();

            $tokenHash = $this->requireDeviceTokenHash($request);
            $payload = $this->query->snapshotPreApplication(
                $tokenHash,
                $this->id($inquiry),
                $input['after_sequence'] ?? null,
                $input['before_sequence'] ?? null,
            );

            return response()->json($payload, 200, ['Cache-Control' => 'private, no-store']);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function send(Request $request, string $inquiry): JsonResponse
    {
        try {
            $input = Validator::make($request->all(), [
                'body' => ['required', 'string', 'max:4000'],
                'idempotency_key' => ['required', 'string', 'uuid'],
            ])->validate();

            $tokenHash = $this->requireDeviceTokenHash($request);
            $inquiryId = $this->id($inquiry);
            $receipt = SendApplicantOfficeInquiryMessage::handlePreApplication(
                $tokenHash,
                $inquiryId,
                (string) $input['body'],
                (string) $input['idempotency_key'],
            );
            $message = $this->query->messagePreApplication($tokenHash, $inquiryId, (int) $receipt['message_id']);

            return response()->json([
                'message' => $message,
                'replayed' => $receipt['replayed'],
                'as_of' => now()->toISOString(),
            ], 200, ['Cache-Control' => 'private, no-store']);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function read(Request $request, string $inquiry): JsonResponse
    {
        try {
            $input = Validator::make($request->all(), [
                'observed_sequence' => ['required', 'string', 'max:32'],
                'observation_token' => ['required', 'string', 'max:1024'],
            ])->validate();

            $tokenHash = $this->requireDeviceTokenHash($request);
            $payload = MarkApplicantOfficeInquiryRead::handlePreApplication(
                $tokenHash,
                $this->id($inquiry),
                (string) $input['observed_sequence'],
                (string) $input['observation_token'],
            );

            return response()->json($payload, 200, ['Cache-Control' => 'private, no-store']);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function clearDevice(Request $request): JsonResponse
    {
        if ($request->hasSession()) {
            $request->session()->forget(ApplicantOfficeInquiryAccess::DEVICE_COOKIE);
        }

        return response()
            ->json(['cleared' => true], 200, ['Cache-Control' => 'private, no-store'])
            ->withCookie(Cookie::forget(ApplicantOfficeInquiryAccess::DEVICE_COOKIE));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveOrIssueDeviceToken(Request $request): array
    {
        $candidates = [
            $request->cookie(ApplicantOfficeInquiryAccess::DEVICE_COOKIE),
            $request->header(ApplicantOfficeInquiryAccess::DEVICE_HEADER),
            $request->hasSession() ? $request->session()->get(ApplicantOfficeInquiryAccess::DEVICE_COOKIE) : null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            try {
                $hash = $this->access->hashDeviceToken($candidate);
                $trimmed = trim($candidate);
                if ($request->hasSession()) {
                    $request->session()->put(ApplicantOfficeInquiryAccess::DEVICE_COOKIE, $trimmed);
                }

                return [$trimmed, $hash];
            } catch (RuntimeException) {
                continue;
            }
        }

        $rawToken = Str::random(48);
        $hash = $this->access->hashDeviceToken($rawToken);
        if ($request->hasSession()) {
            $request->session()->put(ApplicantOfficeInquiryAccess::DEVICE_COOKIE, $rawToken);
        }

        return [$rawToken, $hash];
    }

    private function requireDeviceTokenHash(Request $request): string
    {
        $candidates = [
            $request->cookie(ApplicantOfficeInquiryAccess::DEVICE_COOKIE),
            $request->header(ApplicantOfficeInquiryAccess::DEVICE_HEADER),
            $request->hasSession() ? $request->session()->get(ApplicantOfficeInquiryAccess::DEVICE_COOKIE) : null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            try {
                return $this->access->hashDeviceToken($candidate);
            } catch (RuntimeException) {
                continue;
            }
        }

        throw new RuntimeException('office_inquiry_not_found');
    }

    private function attachDeviceCookie(JsonResponse $response, string $rawToken): JsonResponse
    {
        return $response->cookie(
            ApplicantOfficeInquiryAccess::DEVICE_COOKIE,
            $rawToken,
            60 * 24 * 30,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            'lax',
        );
    }

    private function id(string $value): int
    {
        if (preg_match('/^[1-9][0-9]{0,18}$/D', $value) !== 1 || (strlen($value) === 19 && strcmp($value, '9223372036854775807') > 0)) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        return (int) $value;
    }

    private function failure(Throwable $exception): JsonResponse
    {
        $code = $exception->getMessage();
        if ($exception instanceof AuthorizationException
            || $exception instanceof ModelNotFoundException
            || ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [403, 404], true))
            || $code === 'office_inquiry_not_found') {
            return response()->json([
                'code' => 'not_found',
                'message' => 'Inquiry not found.',
            ], 404, ['Cache-Control' => 'private, no-store']);
        }
        if ($exception instanceof ValidationException) {
            return response()->json([
                'code' => 'validation_failed',
                'message' => 'The inquiry request is invalid. Review the fields and try again.',
                'errors' => $exception->errors(),
            ], 422, ['Cache-Control' => 'private, no-store']);
        }
        if ($code === 'Idempotency key was already used with a different request payload.'
            || in_array($code, ['office_inquiry_closed', 'office_inquiry_conflict', 'office_inquiry_destination_unavailable'], true)) {
            return response()->json([
                'code' => 'inquiry_conflict',
                'message' => 'The inquiry changed or the operation key conflicts. Refresh and try again.',
            ], 409, ['Cache-Control' => 'private, no-store']);
        }

        report($exception);

        return response()->json([
            'code' => 'inquiry_unavailable',
            'message' => 'Inquiries are temporarily unavailable. Try again later.',
        ], 503, ['Cache-Control' => 'private, no-store']);
    }
}

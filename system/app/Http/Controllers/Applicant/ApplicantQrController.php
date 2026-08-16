<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantQrCode;
use App\Admission\Support\RecordThreadService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ApplicantQrController extends Controller
{
    public function show(Request $request): Response
    {
        $attempt = $this->attemptFor($request);

        if ($attempt === null) {
            return Inertia::render('Applicant/MyQr', ['page_state' => 'denied']);
        }

        return Inertia::render('Applicant/MyQr', [
            'page_state' => 'ready',
            'attempt_id' => $attempt->id,
            'qr' => ApplicantQrCode::issue($attempt),
        ]);
    }

    public function admissionSupport(Request $request, RecordThreadService $threads, RecordAuditEvent $audit): Response
    {
        $attempt = $this->attemptFor($request);

        if ($attempt === null) {
            return Inertia::render('Applicant/AdmissionSupport', ['page_state' => 'denied']);
        }

        $threadsList = $threads->forAttempt($attempt, 'applicant_support');
        $threadsData = array_map(function ($thread) use ($threads) {
            return [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'status' => $thread->status,
                'messages' => array_map(fn ($message) => [
                    'id' => $message->id,
                    'sender' => $message->sender?->name,
                    'body' => $message->body,
                    'created_at' => self::isoOrNull($message->created_at),
                ], $threads->messagesFor($thread)),
            ];
        }, $threadsList);

        // Access history = audit events scoped to this attempt (privacy-safe rows).
        $accessHistory = AuditEvent::query()
            ->where('payload->attempt_id', (string) $attempt->id)
            ->whereIn('event_type', [
                'program.head_record_viewed',
                'registrar.enrollment-eligibility.viewed',
                'applicant.qr_resolved',
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['event_type', 'actor', 'created_at', 'payload']);

        return Inertia::render('Applicant/AdmissionSupport', [
            'page_state' => 'ready',
            'threads' => $threadsData,
            'access_history' => $accessHistory->map(fn ($event) => [
                'event_type' => $event->event_type,
                'actor' => $event->actor,
                'occurred_at' => self::isoOrNull($event->created_at),
            ])->all(),
        ]);
    }

    private function attemptFor(Request $request): ?AdmissionAttempt
    {
        $account = $request->user()?->applicantAccount;

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

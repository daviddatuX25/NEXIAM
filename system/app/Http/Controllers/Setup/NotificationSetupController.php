<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Notifications\Queries\NotificationOccurrenceQuery;
use App\Notifications\Support\CommunicationTemplateScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSetupController extends Controller
{
    public function index(
        Request $request,
        NotificationOccurrenceQuery $query,
        CapabilityAuthorizer $authorizer,
        CommunicationTemplateScope $scope,
    ): Response {
        /** @var User $actor */
        $actor = $request->user();
        $domains = $query->accessibleDomains($actor);
        abort_unless(
            in_array(true, $domains, true)
                || $scope->canAccessMetadata($actor, 'admissions')
                || $scope->canAccessMetadata($actor, 'examination')
                || $scope->canAccessMetadata($actor, 'results'),
            403,
        );

        $filters = $this->validatedFilters($request);
        $counts = $query->cardCounts($actor, $domains);
        $activity = $query->activity($actor, $domains, $filters);

        $domainCards = [
            'admissions' => [
                'title' => 'Admissions & Applicant Intake',
                'office' => 'Office of the University Registrar',
                'url' => '/setup/notifications/admissions',
                'can_access' => true,
                'count' => $counts['admissions'],
            ],
            'examination' => [
                'title' => 'Entrance Examination Operations',
                'office' => 'Guidance & Testing Center',
                'url' => '/setup/notifications/examination',
                'can_access' => true,
                'count' => $counts['examination'],
            ],
            'results' => [
                'title' => 'Results & Consultations',
                'office' => 'Guidance & Testing Center',
                'url' => '/setup/notifications/results',
                'can_access' => true,
                'count' => $counts['results'],
            ],
        ];
        $domainCards = array_filter(
            $domainCards,
            fn (array $card, string $domain): bool => $scope->canAccessMetadata($actor, $domain),
            ARRAY_FILTER_USE_BOTH,
        );

        $institutionId = (int) ($actor->actorScope()->institutionId ?? $actor->institution_id ?? Institution::current()?->id);
        $canManageControls = $authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, ['institution_id' => $institutionId])
            || $authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, ['institution_id' => $institutionId, 'office' => 'registrar']);

        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->first();

        $admissionPolicy = AdmissionNotificationPolicy::fromStorage($activeCycle?->admission_notification_policy);
        $examPolicy = ExamNotificationPolicy::fromStorage($activeCycle?->exam_notification_policy);

        $masterControls = [
            'outbound_email_enabled' => (bool) ($activeCycle?->communication_master_controls['outbound_email_enabled'] ?? true),
            'portal_inbox_enabled' => (bool) ($activeCycle?->communication_master_controls['portal_inbox_enabled'] ?? true),
            'staff_action_alerts_enabled' => (bool) ($activeCycle?->communication_master_controls['staff_action_alerts_enabled'] ?? true),
            'can_manage' => $canManageControls,
            'cycle_label' => $activeCycle ? $activeCycle->staffLabel() : 'System Default Policy',
        ];

        $domainPolicies = [
            'admissions' => $domains['admissions'] ? [
                'title' => 'Admissions & Applicant Intake',
                'office' => 'Office of the University Registrar',
                'applicant_status_email_enabled' => $admissionPolicy->applicantStatusEmailEnabled,
                'registrar_new_submission_alert_enabled' => $admissionPolicy->registrarNewSubmissionAlertEnabled,
                'attach_pdf_document' => true,
                'url' => '/setup/notifications/admissions',
                'can_access' => true,
            ] : null,
            'examination' => $domains['examination'] ? [
                'title' => 'Entrance Examination Operations',
                'office' => 'Guidance & Testing Center',
                'publication_email_enabled' => $examPolicy->publicationEmailEnabled,
                'reminder_email_enabled' => $examPolicy->reminderEmailEnabled,
                'reminder_offsets_minutes' => $examPolicy->reminderOffsetsMinutes,
                'attach_pdf_document' => true,
                'url' => '/setup/notifications/examination',
                'can_access' => true,
            ] : null,
            'results' => $domains['results'] ? [
                'title' => 'Results & Consultations',
                'office' => 'Guidance & Testing Center',
                'candidate_portal_release_enabled' => true,
                'attach_pdf_document' => true,
                'url' => '/setup/notifications/results',
                'can_access' => true,
            ] : null,
        ];

        return Inertia::render('Setup/Notifications', [
            'master_controls' => $masterControls,
            'domain_policies' => $domainPolicies,
            'domain_cards' => $domainCards,
            'activity' => $activity,
            'filters' => $filters,
            'health' => ['status' => 'unknown', 'source' => null, 'observed_at' => null],
        ]);
    }

    public function updateControls(
        Request $request,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $recordAuditEvent,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $institutionId = (int) ($actor->actorScope()->institutionId ?? $actor->institution_id ?? Institution::current()?->id);

        abort_unless(
            $authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, ['institution_id' => $institutionId])
                || $authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, ['institution_id' => $institutionId, 'office' => 'registrar']),
            403,
        );

        $validated = $request->validate([
            'outbound_email_enabled' => ['required', 'boolean'],
            'portal_inbox_enabled' => ['required', 'boolean'],
            'staff_action_alerts_enabled' => ['required', 'boolean'],
        ]);

        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->first();

        if ($activeCycle) {
            $activeCycle->update(['communication_master_controls' => $validated]);
        }

        $recordAuditEvent->handle('notification.master_controls_updated', [
            'institution_id' => $institutionId,
            'academic_cycle_id' => $activeCycle?->id,
            'controls' => $validated,
            'actor_id' => $actor->id,
        ], 'staff');

        return back()->with('status', 'Master communication controls updated.');
    }

    public function occurrences(Request $request, NotificationOccurrenceQuery $query): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $domains = $query->accessibleDomains($actor);
        abort_unless(in_array(true, $domains, true), 403);

        return response()->json($query->activity($actor, $domains, $this->validatedFilters($request)));
    }

    public function showOccurrence(
        Request $request,
        string $occurrence,
        NotificationOccurrenceQuery $query,
        CapabilityAuthorizer $authorizer,
    ): Response {
        /** @var User $actor */
        $actor = $request->user();
        $summary = $query->occurrence($actor, $occurrence);
        abort_if($summary === null, 404);

        $institutionId = $actor->actorScope()->institutionId;
        $canRetry = false;
        if ($institutionId !== null) {
            $canRetry = match ($summary['domain']) {
                'admissions' => $authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, ['institution_id' => $institutionId])
                    || $authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, ['institution_id' => $institutionId, 'office' => 'registrar']),
                'examination' => $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, ['institution_id' => $institutionId, 'office' => 'guidance']),
                'results' => $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, ['institution_id' => $institutionId, 'office' => 'guidance']),
                default => false,
            };
        }

        $deliveries = $query->deliveries($actor, $summary);
        unset($summary['event_id'], $summary['event_type'], $summary['resource_id']);

        return Inertia::render('Setup/Notifications/Occurrence', [
            'occurrence' => $summary,
            'deliveries' => $deliveries,
            'history' => $query->history($actor, (string) $summary['occurrence_id']),
            'can_retry' => $canRetry,
        ]);
    }

    public function retryDelivery(
        Request $request,
        string $occurrence,
        string $delivery,
        NotificationOccurrenceQuery $query,
        CapabilityAuthorizer $authorizer,
        NotificationBusinessValidity $businessValidity,
        RecordAuditEvent $recordAuditEvent,
    ): RedirectResponse|JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $deliveryRecord = NotificationDelivery::find($delivery);
        abort_if($deliveryRecord === null, 404);

        $domain = match (true) {
            str_starts_with($deliveryRecord->event_type, 'admission.') => 'admissions',
            str_starts_with($deliveryRecord->event_type, 'exam.') => 'examination',
            str_starts_with($deliveryRecord->event_type, 'result.') || str_starts_with($deliveryRecord->event_type, 'consultation.') => 'results',
            default => null,
        };

        $institutionId = $actor->actorScope()->institutionId;
        $authorized = $institutionId !== null && match ($domain) {
            'admissions' => $authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, ['institution_id' => $institutionId])
                || $authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, ['institution_id' => $institutionId, 'office' => 'registrar']),
            'examination' => $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, ['institution_id' => $institutionId, 'office' => 'guidance']),
            'results' => $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, ['institution_id' => $institutionId, 'office' => 'guidance']),
            default => false,
        };
        abort_unless($authorized, 403);

        $summary = $query->occurrence($actor, $occurrence);
        abort_if($summary === null, 404);

        $verifiedDelivery = $query->verifyDeliveryInOccurrence($actor, $summary, $delivery);
        abort_if($verifiedDelivery === null, 404);
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'acknowledge_duplicate_risk' => ['nullable', 'boolean'],
            'expected_version' => ['nullable', 'integer'],
        ]);

        return DB::transaction(function () use ($deliveryRecord, $request, $businessValidity, $recordAuditEvent, $actor): RedirectResponse|JsonResponse {
            $lockedDelivery = NotificationDelivery::query()->lockForUpdate()->find($deliveryRecord->id);
            abort_if($lockedDelivery === null, 404);

            if ($request->filled('expected_version')) {
                abort_if(
                    (int) $lockedDelivery->updated_at?->timestamp !== (int) $request->input('expected_version'),
                    409,
                    'The delivery record has been updated by another process. Please refresh.',
                );
            }

            abort_unless(
                in_array($lockedDelivery->status, [NotificationDelivery::STATUS_FAILED_TERMINAL, NotificationDelivery::STATUS_OUTCOME_UNKNOWN], true),
                422,
                'Only failed terminal or uncertain deliveries can be retried.',
            );

            if ($lockedDelivery->status === NotificationDelivery::STATUS_OUTCOME_UNKNOWN) {
                if (! $request->boolean('acknowledge_duplicate_risk')) {
                    throw ValidationException::withMessages([
                        'acknowledge_duplicate_risk' => 'Duplicate risk acknowledgment is required for deliveries with unknown outcomes.',
                    ]);
                }
                if (! $request->filled('reason') || trim((string) $request->input('reason')) === '') {
                    throw ValidationException::withMessages([
                        'reason' => 'A reason is required when retrying deliveries with unknown outcomes.',
                    ]);
                }
            }

            if (! $businessValidity->isCurrent($lockedDelivery)) {
                $lockedDelivery->update([
                    'status' => NotificationDelivery::STATUS_SUPERSEDED,
                    'claim_token' => null,
                    'lease_expires_at' => null,
                    'next_attempt_at' => null,
                    'last_error' => null,
                ]);
                $recordAuditEvent->handle('notification.delivery_superseded', [
                    'delivery_id' => $lockedDelivery->id,
                    'event_type' => $lockedDelivery->event_type,
                    'channel' => $lockedDelivery->channel,
                    'attempts' => $lockedDelivery->attempts,
                    'reason' => 'retry_business_validity_failed',
                ], 'staff');

                if ($request->wantsJson()) {
                    return response()->json(['message' => 'This notification is no longer current and has been superseded.'], 422);
                }

                return back()->withErrors(['delivery' => 'This notification is no longer current and has been superseded.'])->setStatusCode(422);
            }

            $priorStatus = $lockedDelivery->status;
            $reason = (string) $request->input('reason', '');
            $lockedDelivery->update([
                'status' => NotificationDelivery::STATUS_PENDING,
                'attempts' => 0,
                'next_attempt_at' => null,
                'last_error' => null,
                'claim_token' => null,
                'lease_expires_at' => null,
                'outcome_unknown_at' => null,
            ]);

            $recordAuditEvent->handle('notification.delivery_manually_retried', [
                'delivery_id' => $lockedDelivery->id,
                'event_type' => $lockedDelivery->event_type,
                'channel' => $lockedDelivery->channel,
                'reason' => $reason,
                'prior_status' => $priorStatus,
                'actor_id' => $actor->id,
            ], 'staff');

            DB::afterCommit(fn () => ProcessNotificationDelivery::dispatch($lockedDelivery->id));

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Delivery queued for retry.']);
            }

            return back()->with('success', 'Delivery queued for retry.');
        });
    }

    /** @return array{domain?: string, search?: string, status?: string, channel?: string, from?: string, to?: string} */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'domain' => ['sometimes', 'string', Rule::in(['admissions', 'examination', 'results'])],
            'search' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'processing', 'delivered', 'retrying', 'failed_terminal', 'outcome_unknown', 'superseded'])],
            'channel' => ['sometimes', 'string', Rule::in(['mail', 'in_app'])],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        if (isset($validated['from'], $validated['to'])) {
            abort_if(Carbon::parse($validated['from'])->gt(Carbon::parse($validated['to'])), 422);
        }

        return $validated;
    }
}

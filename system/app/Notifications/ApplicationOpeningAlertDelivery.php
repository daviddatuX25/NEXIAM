<?php

namespace App\Notifications;

use App\Models\Admission\ApplicationOpeningOccurrence;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Admission\ApplicationOpeningToken;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;

final class ApplicationOpeningAlertDelivery
{
    public function __construct(private readonly NotificationIntentDispatcher $dispatcher) {}

    public function verificationAfterCommit(
        ApplicationOpeningSubscription $subscription,
        ApplicationOpeningToken $token,
        Institution $institution,
    ): void {
        $intent = NotificationIntent::v1('application.opening_alert.verify.v1', 'application.opening_alert.verify', [
            'institution_id' => (int) $institution->id,
            'template_values' => ['institution_name' => (string) $institution->name],
            'handoff' => ['verify_token_id' => (string) $token->id],
        ], [NotificationDelivery::CHANNEL_MAIL]);
        $deliveries = $this->dispatcher->dispatch(
            $intent,
            hash('sha256', 'application-opening-verify:'.$token->id),
            [$subscription],
            [NotificationDelivery::CHANNEL_MAIL],
        );
        NotificationIntentDispatcher::enqueueAfterCommit($deliveries);
    }

    public function openingAfterCommit(
        ApplicationOpeningSubscription $subscription,
        ApplicationOpeningOccurrence $occurrence,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        Institution $institution,
        ApplicationOpeningToken $unsubscribeToken,
        ApplicationOpeningToken $prefillToken,
    ): ?NotificationDelivery {
        $eventId = hash('sha256', implode(':', [
            'application-opening', $occurrence->id, $subscription->id, $subscription->generation,
            $unsubscribeToken->id,
        ]));
        $intent = NotificationIntent::v1('application.opening_alert.opened.v1', 'application.opening_alert.opened', [
            'institution_id' => (int) $institution->id,
            'template_values' => [
                'institution_name' => (string) $institution->name,
                'academic_year' => $cycle->staffLabel(),
                'semester' => $cycle->semesterLabel(),
                'deadline' => $window->closes_at->format('F j, Y H:i T'),
            ],
            'handoff' => [
                'unsubscribe_token_id' => (string) $unsubscribeToken->id,
                'prefill_token_id' => (string) $prefillToken->id,
            ],
            'occurrence_id' => (int) $occurrence->id,
            'cycle_id' => (int) $cycle->id,
            'window_id' => (int) $window->id,
            'availability_generation' => (int) $occurrence->availability_generation,
            'opens_at' => $window->opens_at->toIso8601String(),
            'closes_at' => $window->closes_at->toIso8601String(),
        ], [NotificationDelivery::CHANNEL_MAIL]);
        $deliveries = $this->dispatcher->dispatch($intent, $eventId, [$subscription], [NotificationDelivery::CHANNEL_MAIL]);
        NotificationIntentDispatcher::enqueueAfterCommit($deliveries);

        return $deliveries->first();
    }
}

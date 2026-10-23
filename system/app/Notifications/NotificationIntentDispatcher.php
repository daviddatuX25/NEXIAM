<?php

namespace App\Notifications;

use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\NoticeRecipientContextResolver;
use App\Notifications\Templates\NotificationTemplateResolver;
use App\Notifications\Templates\ResultNoticeRecipientContextResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes immutable recipient/channel snapshots inside the owning domain transaction.
 * The unique event/recipient/channel constraint fences durable delivery duplicates.
 * Provider and rendering work belongs to the after-commit delivery worker.
 */
class NotificationIntentDispatcher
{
    public function __construct(
        private readonly NotificationTemplateResolver $templateResolver,
        private readonly NoticeRecipientContextResolver $contextResolver,
        private readonly AdmissionExaminationNoticeContext $admissionExamination,
        private readonly ResultNoticeRecipientContextResolver $results,
        private readonly PracticeNotificationSafety $practiceSafety,
    ) {}

    /**
     * @param  iterable<User|ApplicantIdentity|ApplicationOpeningSubscription>  $recipients
     * @param  list<string>  $channels
     * @return Collection<int, NotificationDelivery>
     */
    public function dispatch(NotificationIntent $intent, string $eventId, iterable $recipients, array $channels): Collection
    {
        $created = [];
        $mapping = NotificationTemplateResolver::mapping($intent);
        $builtin = $mapping !== null && CommunicationTemplateRegistry::authoringMode($mapping[0], $mapping[1]) === 'builtin';
        $selectedVersion = null;
        $selectedInstitutionId = null;
        $channels = array_values(array_unique(array_intersect($channels, [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_MAIL, NotificationDelivery::CHANNEL_PUSH])));
        if ($builtin) {
            $channels = array_values(array_diff($channels, [NotificationDelivery::CHANNEL_MAIL]));
        }
        foreach ($recipients as $recipient) {
            $recipientType = match (true) {
                $recipient instanceof User => User::class,
                $recipient instanceof ApplicantIdentity => ApplicantIdentity::class,
                $recipient instanceof ApplicationOpeningSubscription => ApplicationOpeningSubscription::class,
                default => null,
            };
            if ($recipientType === ApplicationOpeningSubscription::class) {
                if (! in_array($intent->eventType, ['application.opening_alert.verify.v1', 'application.opening_alert.opened.v1'], true)) {
                    continue;
                }
                $recipientChannels = array_values(array_intersect($channels, [NotificationDelivery::CHANNEL_MAIL]));
                $practiceSetId = null;
            } else {
                $practiceSafety = $this->practiceSafety;
                $practiceSetId = $recipient instanceof User || $recipient instanceof ApplicantIdentity
                    ? $practiceSafety->setId($intent, $recipient)
                    : null;
                $recipientChannels = $practiceSetId === null ? $channels : $practiceSafety->channels($practiceSetId, $recipient, $channels);
            }
            if ($recipientType === null || $recipient->getKey() === null || $recipientChannels === []) {
                continue;
            }
            if ($practiceSetId !== null && ! $practiceSafety->rootsAreOwned($practiceSetId, $practiceSafety->roots($intent->payload))) {
                continue;
            }
            $existing = NotificationDelivery::query()->where('event_id', $eventId)
                ->where('recipient_type', $recipientType)->where('recipient_id', $recipient->getKey())->get();
            $newChannels = array_values(array_diff($recipientChannels, $existing->pluck('channel')->all()));
            if ($newChannels === []) {
                continue;
            }
            $payload = $intent->payload;
            // The legacy delivery column is required; built-in copy keeps the intent default without selecting an authored family version.
            $templateVersion = $intent->templateVersion;
            if ($existing->isNotEmpty()) {
                $frozen = $existing->first();
                if ($frozen->event_type !== $intent->eventType || $frozen->template_key !== $intent->templateKey) {
                    throw new \RuntimeException('notice_event_identity_mismatch');
                }
                $payload = $frozen->payload;
                $templateVersion = $frozen->template_version;
            } elseif ($mapping !== null) {
                [$domain, $slug] = $mapping;
                $context = $this->recipientContext($intent, $recipient, $domain, $slug);
                if ($selectedInstitutionId === null) {
                    $selectedInstitutionId = $context['institution_id'];
                    if (! $builtin) {
                        $selectedVersion = $this->templateResolver->select($selectedInstitutionId, $domain, $slug);
                    }
                } elseif ($selectedInstitutionId !== $context['institution_id']) {
                    throw new \RuntimeException('notice_recipient_resource_mismatch');
                }
                if ($recipient instanceof ApplicationOpeningSubscription) {
                    if (! $selectedVersion instanceof CommunicationTemplateVersion) {
                        throw new \RuntimeException('application_opening_alert_template_unavailable');
                    }
                    $payload = $this->freezeOpeningAlert($intent, $recipient, $selectedVersion, $context);
                    $templateVersion = (int) $selectedVersion->version;
                } elseif ($builtin) {
                    $payload = $this->templateResolver->freezeBuiltin(
                        $intent, $domain, $slug, $recipientType, (int) $recipient->getKey(), $context,
                    );
                } elseif ($selectedVersion !== null) {
                    $payload = $this->templateResolver->freeze($selectedVersion, $intent, $recipientType, (int) $recipient->getKey(), $context);
                    $templateVersion = (int) $selectedVersion->version;
                    if (($payload['snapshot_schema_version'] ?? null) === 'notice-delivery-3') {
                        $payload['recipient']['email'] = $recipient::query()->whereKey($recipient->getKey())->value('email');
                    }
                }

            }

            if ($existing->isEmpty() && $practiceSetId !== null) {
                $payload['practice_fixture_set_id'] = $practiceSetId;
                $payload['practice_roots'] = $practiceSafety->roots($intent->payload);
                if (in_array(NotificationDelivery::CHANNEL_MAIL, $newChannels, true)) {
                    $payload['practice_recipient_email'] = $recipient::query()->whereKey($recipient->getKey())->value('email');
                }
                if ($intent->eventType === 'exam.reminder.v1') {
                    $payload['practice_reminder_window'] = $intent->payload['window'] ?? null;
                }
            }
            $serializedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
            foreach ($newChannels as $channel) {
                $attributes = [
                    'id' => (string) Str::uuid(), 'event_id' => $eventId, 'event_type' => $intent->eventType,
                    'event_version' => $intent->eventVersion, 'recipient_type' => $recipientType,
                    'recipient_id' => $recipient->getKey(), 'channel' => $channel,
                    'template_key' => $intent->templateKey, 'template_version' => $templateVersion,
                    'payload' => $serializedPayload,
                    'status' => NotificationDelivery::STATUS_PENDING, 'attempts' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ];
                if (NotificationDelivery::query()->insertOrIgnore($attributes) !== 1) {
                    continue;
                }
                $created[] = NotificationDelivery::query()->where('event_id', $eventId)
                    ->where('recipient_type', $recipientType)->where('recipient_id', $recipient->getKey())
                    ->where('channel', $channel)->firstOrFail();
            }
        }

        return collect($created);
    }

    /** @param Collection<int, NotificationDelivery>|list<NotificationDelivery> $deliveries */
    public static function enqueueAfterCommit(Collection|array $deliveries): void
    {
        foreach ($deliveries as $delivery) {
            DB::afterCommit(fn () => ProcessNotificationDelivery::dispatch($delivery->id));
        }
    }

    /** @return array{institution_id:int,values:array<string,string>,resource:array<string,mixed>,print_binding?:array<string,mixed>} */
    private function recipientContext(NotificationIntent $intent, User|ApplicantIdentity|ApplicationOpeningSubscription $recipient, string $domain, string $slug): array
    {
        if ($recipient instanceof ApplicationOpeningSubscription) {
            $values = $intent->payload['template_values'] ?? [];
            if (! is_array($values) || ! is_string($values['institution_name'] ?? null)) {
                throw new \RuntimeException('notice_recipient_resource_mismatch');
            }

            return [
                'institution_id' => (int) $recipient->institution_id,
                'values' => $values,
                'resource' => [
                    'subscription_id' => (int) $recipient->id,
                    'generation' => (int) $recipient->generation,
                    'handoff' => $intent->payload['handoff'] ?? [],
                    ...array_intersect_key($intent->payload, array_fill_keys(['occurrence_id', 'cycle_id', 'window_id', 'availability_generation', 'opens_at', 'closes_at'], true)),
                ],
            ];
        }
        if ($domain === 'examination' && $slug === 'reminder' && $recipient instanceof ApplicantIdentity) {
            return $this->contextResolver->forReminder($intent, $recipient);
        }
        if ($domain === 'admissions' || $domain === 'examination') {
            return $this->admissionExamination->resolve($intent, $recipient);
        }
        if ($domain === 'results' && $recipient instanceof ApplicantIdentity) {
            return $this->results->resolve($intent, $recipient, $slug);
        }

        throw new \RuntimeException('notice_recipient_resource_mismatch');
    }

    /** @return array<string,mixed> */
    private function freezeOpeningAlert(
        NotificationIntent $intent,
        ApplicationOpeningSubscription $recipient,
        CommunicationTemplateVersion $version,
        array $context,
    ): array {
        if (! in_array($intent->eventType, ['application.opening_alert.verify.v1', 'application.opening_alert.opened.v1'], true)
            || (int) $version->template?->institution_id !== (int) $recipient->institution_id
            || ! is_array($intent->payload['handoff'] ?? null)) {
            throw new \RuntimeException('application_opening_alert_snapshot_invalid');
        }

        $snapshot = $this->templateResolver->freeze(
            $version,
            $intent,
            ApplicationOpeningSubscription::class,
            (int) $recipient->id,
            $context,
        );
        $snapshot['snapshot_schema_version'] = 'application-opening-alert-1';
        $snapshot['subscription_id'] = (int) $recipient->id;
        $snapshot['handoff'] = $intent->payload['handoff'];

        return $snapshot;
    }
}

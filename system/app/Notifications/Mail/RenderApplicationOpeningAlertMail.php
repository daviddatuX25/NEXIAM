<?php

namespace App\Notifications\Mail;

use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Admission\ApplicationOpeningToken;
use App\Notifications\NotificationDelivery;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\Email\NoticeEmailRenderer;
use App\Notifications\Templates\Email\NoticeLegacyEmailRenderer;
use App\Notifications\Templates\Email\NoticeMessageEmailRenderer;
use RuntimeException;

final class RenderApplicationOpeningAlertMail
{
    public function __construct(
        private readonly NoticeEmailAssetVerifier $assetVerifier,
        private readonly NoticeEmailRenderer $emailRenderer,
        private readonly NoticeMessageEmailRenderer $messageEmailRenderer,
        private readonly NoticeLegacyEmailRenderer $legacyRenderer,
    ) {}

    /** @return array{subject:string,production_html:string,text:string,assets:array} */
    public function render(NotificationDelivery $delivery): array
    {
        $snapshot = $delivery->payload;
        $subscription = ApplicationOpeningSubscription::query()->find($delivery->recipient_id);
        if ($subscription === null || $subscription->email === null
            || ($snapshot['snapshot_schema_version'] ?? null) !== 'application-opening-alert-1'
            || ($snapshot['recipient']['type'] ?? null) !== ApplicationOpeningSubscription::class
            || (string) ($snapshot['recipient']['id'] ?? '') !== (string) $subscription->id
            || (int) ($snapshot['subscription_id'] ?? 0) !== (int) $subscription->id
            || ($snapshot['event_type'] ?? null) !== $delivery->event_type
            || ($snapshot['template_key'] ?? null) !== $delivery->template_key
            || (int) ($snapshot['template']['version'] ?? 0) !== (int) $delivery->template_version) {
            throw new RuntimeException('application_opening_alert_snapshot_invalid');
        }

        $values = $snapshot['context']['values'] ?? [];
        $handoff = $snapshot['handoff'] ?? [];
        if (! is_array($values) || ! is_array($handoff)) {
            throw new RuntimeException('application_opening_alert_snapshot_invalid');
        }
        $eventType = $delivery->event_type;
        if ($eventType === 'application.opening_alert.verify.v1' && ! is_string($handoff['verify_token_id'] ?? null)) {
            throw new RuntimeException('application_opening_alert_handoff_invalid');
        }
        if ($eventType === 'application.opening_alert.opened.v1'
            && (! is_string($handoff['unsubscribe_token_id'] ?? null) || ! is_string($handoff['prefill_token_id'] ?? null))) {
            throw new RuntimeException('application_opening_alert_handoff_invalid');
        }

        foreach (['confirmation_url', 'apply_url', 'prefill_url', 'unsubscribe_url'] as $key) {
            $values[$key] = '';
        }
        $values['apply_url'] = url('/apply');
        foreach ([
            'verify_token_id' => ['confirmation_url', 'verify'],
            'unsubscribe_token_id' => ['unsubscribe_url', 'unsubscribe'],
            'prefill_token_id' => ['prefill_url', 'prefill'],
        ] as $handoffKey => [$valueKey, $purpose]) {
            $tokenId = $handoff[$handoffKey] ?? null;
            if ($tokenId === null) {
                continue;
            }
            if (! is_string($tokenId) || $tokenId === '') {
                throw new RuntimeException('application_opening_alert_handoff_invalid');
            }
            $token = ApplicationOpeningToken::query()->whereKey($tokenId)
                ->where('subscription_id', $subscription->id)
                ->where('generation', $subscription->generation)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->whereNull('superseded_at')
                ->where('expires_at', '>', now())
                ->first();
            if ($token === null || ! is_string($token->encrypted_token)) {
                if ($purpose === 'prefill' && $eventType === 'application.opening_alert.opened.v1') {
                    $values['prefill_url'] = url('/apply');

                    continue;
                }
                throw new RuntimeException('application_opening_alert_handoff_unavailable');
            }
            $path = $purpose === 'prefill'
                ? '/apply/opening-alerts/prefill/'.rawurlencode($token->encrypted_token)
                : '/apply/opening-alerts/'.rawurlencode($token->encrypted_token).'?purpose='.$purpose;
            $values[$valueKey] = url($path);
        }

        $template = $snapshot['template'] ?? [];
        $email = $snapshot['email'] ?? [];
        $blueprint = $email['blueprint'] ?? null;
        if (! is_array($blueprint) || ! is_array($email['assets'] ?? null)) {
            throw new RuntimeException('application_opening_alert_snapshot_invalid');
        }
        $assets = $this->assetVerifier->verify(
            (int) $snapshot['institution_id'],
            (string) ($template['id'] ?? ''),
            $blueprint,
            $email['assets'],
        );
        $allowedTokens = array_keys($values);
        $schema = (string) ($template['content_schema_version'] ?? '');
        $rendererVersion = (string) ($template['email_renderer_version'] ?? '');
        $subject = (string) ($email['subject_template'] ?? '');
        if ($schema === 'notice-content-3') {
            $rendered = $this->messageEmailRenderer->render(
                $blueprint,
                $values,
                $allowedTokens,
                (string) ($template['email_schema_version'] ?? ''),
                $rendererVersion,
                $assets,
                $subject,
                $email['action'] ?? null,
            );

            return $this->ensureTextLinks($rendered, $eventType, $values);
        }
        if ($schema === 'notice-content-2') {
            $rendered = $this->emailRenderer->render(
                $blueprint,
                $values,
                $allowedTokens,
                (string) ($template['email_schema_version'] ?? ''),
                $rendererVersion,
                $assets,
                $subject,
            );

            return $this->ensureTextLinks($rendered, $eventType, $values);
        }
        if ($schema === 'notice-content-1') {
            $rendered = $this->legacyRenderer->render($snapshot, $delivery->template_key, $delivery->template_version);

            return $this->ensureTextLinks($rendered, $eventType, $values);
        }

        throw new RuntimeException('email_renderer_unavailable');
    }

    /** @param array{subject:string,production_html:string,text:string,assets:array} $rendered
     * @param  array<string,mixed>  $values
     * @return array{subject:string,production_html:string,text:string,assets:array}
     */
    private function ensureTextLinks(array $rendered, string $eventType, array $values): array
    {
        $links = $eventType === 'application.opening_alert.verify.v1'
            ? ['Confirm email address' => 'confirmation_url']
            : [
                'Start your application' => 'apply_url',
                'Open Apply with your email filled in' => 'prefill_url',
                'Unsubscribe' => 'unsubscribe_url',
            ];
        foreach ($links as $label => $key) {
            $url = $values[$key] ?? null;
            if (is_string($url) && $url !== '' && ! str_contains($rendered['text'], $url)) {
                $rendered['text'] = trim($rendered['text'])."\n\n{$label}: {$url}";
            }
        }

        return $rendered;
    }
}

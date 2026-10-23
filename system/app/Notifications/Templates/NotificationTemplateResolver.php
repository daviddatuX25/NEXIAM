<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Notifications\NotificationIntent;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\Email\NoticeEmailLinkPolicy;
use App\Notifications\Templates\Email\NoticeLegacyEmailRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

final class NotificationTemplateResolver
{
    public function __construct(private readonly NoticeRecipientContextResolver $contextResolver) {}

    /** @return array{string,string}|null */
    public static function mapping(NotificationIntent $intent): ?array
    {
        return match ([$intent->eventType, $intent->templateKey]) {
            ['application.opening_alert.verify.v1', 'application.opening_alert.verify'] => ['admissions', 'opening-alert-verify'],
            ['application.opening_alert.opened.v1', 'application.opening_alert.opened'] => ['admissions', 'opening-alert-opened'],
            ['admission.submitted.v1', 'admission.submitted'] => ['admissions', 'submitted'],
            ['admission.new_submission.v1', 'admission.new_submission'] => ['admissions', 'new-submission'],
            ['admission.accepted.v1', 'admission.accepted'] => ['admissions', 'accepted'],
            ['admission.dismissed.v1', 'admission.dismissed'] => ['admissions', 'dismissed'],
            ['admission.correction_requested.v1', 'admission.correction_requested'] => ['admissions', 'correction-requested'],
            ['admission.document_correction_requested.v1', 'admission.document_correction_requested'] => ['admissions', 'document-correction-requested'],
            ['admission.clearance.confirmed.v1', 'admission.clearance_confirmed'] => ['admissions', 'clearance-confirmed'],
            ['exam.session_published.v1', 'exam.session_published'] => ['examination', 'session-published'],
            ['exam.reminder.v1', 'exam.reminder'] => ['examination', 'reminder'],
            ['exam.session_cancelled.v1', 'exam.session_cancelled'] => ['examination', 'session-cancelled'],
            ['exam.session_unpublished.v1', 'exam.session_unpublished'] => ['examination', 'session-unpublished'],
            ['exam.slot_reserved.v1', 'exam.slot_reserved'] => ['examination', 'slot-reserved'],
            ['exam.proctor_duty_assigned.v1', 'exam.proctor_duty_assigned'] => ['examination', 'proctor-duty-assigned'],
            ['exam.session_completed.v1', 'exam.session_completed'] => ['examination', 'session-completed'],
            ['result.released.v1', 'result.released.online'] => ['results', 'released-online'],
            ['result.released.v1', 'result.released.f2f'] => ['results', 'released-f2f'],
            ['result.unreleased.v1', 'result.unreleased'] => ['results', 'unreleased'],
            ['consultation.session_published.v1', 'consultation.session_published'] => ['results', 'consultation-published'],
            ['consultation.slot_reserved.v1', 'consultation.slot_reserved'] => ['results', 'consultation-reserved'],
            ['consultation.online_invited.v1', 'consultation.online_invited'] => ['results', 'consultation-online-invited'],
            default => null,
        };
    }

    public static function permitsPrint(string $eventType, string $templateKey): bool
    {
        return in_array([$eventType, $templateKey], [
            ['admission.accepted.v1', 'admission.accepted'],
            ['exam.session_published.v1', 'exam.session_published'],
            ['result.released.v1', 'result.released.online'],
        ], true);
    }

    public function select(int $institutionId, string $domain, string $canonicalSlug): ?CommunicationTemplateVersion
    {
        $template = CommunicationTemplate::query()->where('institution_id', $institutionId)
            ->where('domain', $domain)->where('slug', $canonicalSlug)->lockForUpdate()->first();
        $version = $template?->activeVersion();
        $version?->setRelation('template', $template);

        return $version;
    }

    public function freezeBuiltin(
        NotificationIntent $intent,
        string $domain,
        string $slug,
        string $recipientType,
        int $recipientId,
        array $context,
    ): array {
        $definition = CommunicationTemplateRegistry::builtinMessageDefinition($domain, $slug)
            ?? throw new \RuntimeException('notice_builtin_copy_unavailable');
        $variantKey = $slug === 'consultation-online-invited'
            ? (($context['resource']['booking_path'] ?? null) === 'scheduled' ? 'scheduled' : 'immediate')
            : null;
        $copy = $variantKey === null ? $definition : ($definition['variants'][$variantKey] ?? $definition);
        $values = $context['values'];
        if (! isset($values['application_no']) && is_string($intent->payload['reference_code'] ?? null)
            && trim($intent->payload['reference_code']) !== '') {
            $values['application_no'] = $intent->payload['reference_code'];
        }
        if (! isset($values['session_code']) && is_string($intent->payload['code'] ?? null)
            && trim($intent->payload['code']) !== '') {
            $values['session_code'] = $intent->payload['code'];
        }
        if ($variantKey === 'scheduled' && is_string($context['resource']['access_starts_at'] ?? null)) {
            $values['scheduled_at'] = CarbonImmutable::parse($context['resource']['access_starts_at'])
                ->format('F j, Y \\a\\t g:i A T');
        }
        $title = $this->substituteBuiltin((string) $copy['title'], $values);
        $body = $this->substituteBuiltin((string) $copy['body'], $values);
        if ($title === null || $body === null) {
            $fallbackTitle = $this->substituteBuiltin((string) ($copy['fallback_title'] ?? $definition['fallback_title']), $values);
            $fallbackBody = $this->substituteBuiltin((string) ($copy['fallback_body'] ?? $definition['fallback_body']), $values);
            if ($fallbackTitle === null || $fallbackBody === null) {
                throw new \RuntimeException('notice_builtin_fallback_context_missing');
            }
            $title = $fallbackTitle;
            $body = $fallbackBody;
        }

        return [
            'snapshot_schema_version' => 'notice-delivery-2',
            'institution_id' => (int) $context['institution_id'],
            'domain' => $domain,
            'event_type' => $intent->eventType,
            'template_key' => $intent->templateKey,
            'canonical_slug' => $slug,
            'recipient' => ['type' => $recipientType, 'id' => $recipientId],
            'event_resource' => $context['resource'],
            'context' => ['values' => $values, 'missing_required_keys' => []],
            'portal' => [
                'title' => $title,
                'body' => $body,
                'action_label' => $intent->payload['action_label'] ?? null,
                'action_url' => $intent->payload['action_url'] ?? null,
            ],
        ];
    }

    private function substituteBuiltin(string $copy, array $values): ?string
    {
        $result = preg_replace_callback('/\\{\\{\\s*([^{}]+?)\\s*\\}\\}/', static function (array $match) use ($values): string {
            $key = trim($match[1]);

            return isset($values[$key]) ? (string) $values[$key] : $match[0];
        }, $copy);
        if (! is_string($result) || str_contains($result, '{{') || str_contains($result, '}}')) {
            return null;
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function freeze(CommunicationTemplateVersion $version, NotificationIntent $intent, string $recipientType, int $recipientId, array $context): array
    {
        if ($version->content_schema_version === 'notice-content-3') {
            $this->assertDocumentRequestEmailContainsItems($intent, $version->email_blueprint);

            return $this->freezeContent3($version, $intent, $recipientType, $recipientId, $context);
        }

        $template = $version->template;
        $legacy = $version->content_schema_version === 'notice-content-1';
        $subject = $legacy ? (string) $version->email_subject : (string) $version->subject;
        $blueprint = $legacy ? [
            'schema_version' => NoticeLegacyEmailRenderer::SCHEMA,
            'body_template' => (string) $version->email_body,
            'action_label' => $version->action_label,
            'action_url' => $this->legacyActionUrl($version, $intent),
        ] : $version->email_blueprint;
        if (! is_array($blueprint) || (! $legacy && (! is_string($version->content_sha256)
            || ! preg_match('/^[a-f0-9]{64}$/D', $version->content_sha256)))) {
            throw new \RuntimeException('notice_snapshot_integrity');
        }
        $this->assertDocumentRequestEmailContainsItems($intent, $blueprint);
        $actual = $this->contextResolver->allowlisted($template->domain, $template->slug, $context['values']);
        $missing = $this->contextResolver->missingRequired($subject, $blueprint, $actual);
        $allowed = array_map(static fn (array $token): string => trim($token['token'], '{} '),
            (CommunicationTemplateRegistry::findBySlug($template->domain, $template->slug)
                ?? CommunicationTemplateRegistry::historicalSlotBySlug($template->domain, $template->slug))['tokens'] ?? []);
        $failure = null;
        if (array_diff($missing, $allowed) !== []) {
            $failure = ['code' => 'email_token_unknown'];
            $missing = array_values(array_intersect($missing, $allowed));
        } elseif ($missing !== []) {
            $failure = ['code' => 'email_context_missing', 'missing_context_keys' => $missing];
        }
        $assets = [];
        foreach ($legacy ? [] : NoticeEmailAssetVerifier::assetIds($blueprint) as $id) {
            $asset = CommunicationTemplateDocument::query()->whereKey($id)
                ->where('institution_id', $template->institution_id)
                ->where('communication_template_id', $template->id)->firstOrFail();
            $assets[] = ['id' => $id, 'sha256' => $asset->checksum,
                'cid' => 'notice-'.$id.'@nexiam.inline', 'mime' => $asset->mime_type,
                'filename' => $id.($asset->mime_type === 'image/png' ? '.png' : '.jpg')];
        }
        $print = $this->freezePrint($version, $intent, $context);
        $contentHash = $version->content_sha256;
        if ($legacy && (! is_string($contentHash) || ! preg_match('/^[a-f0-9]{64}$/D', $contentHash))) {
            $contentHash = CommunicationTemplatePayload::hash($version->only([
                'content_schema_version', 'email_renderer_version', 'email_subject', 'email_body', 'in_app_title', 'in_app_body',
                'action_label', 'action_route', 'document_mode', 'document_asset_id', 'builder_json', 'paper_size', 'orientation', 'import_provenance',
            ]));
        }

        return [
            'snapshot_schema_version' => 'notice-delivery-2',
            'institution_id' => (int) $template->institution_id, 'domain' => $template->domain,
            'event_type' => $intent->eventType, 'template_key' => $intent->templateKey, 'canonical_slug' => $template->slug,
            'recipient' => ['type' => $recipientType, 'id' => $recipientId],
            'event_resource' => $context['resource'],
            'template' => ['id' => $template->id, 'version' => (int) $version->version,
                'content_sha256' => $contentHash, 'content_schema_version' => $version->content_schema_version,
                'email_schema_version' => $blueprint['schema_version'] ?? null, 'email_renderer_version' => $version->email_renderer_version],
            'context' => ['values' => $actual, 'missing_required_keys' => $missing],
            'email' => ['subject_template' => $subject, 'blueprint' => $blueprint, 'assets' => $assets, 'preparation_failure' => $failure],
            'portal' => $this->freezePortal($version, $intent, $actual),
            'print' => $print,
            'import' => $version->import_provenance === null ? null : array_intersect_key(
                $version->import_provenance, array_fill_keys(['source_asset_id', 'source_sha256', 'converter_version',
                    'report_id', 'report_sha256', 'review_acknowledgment'], true),
            ),
        ];
    }

    /** @return array<string,mixed> */
    private function freezeContent3(
        CommunicationTemplateVersion $version,
        NotificationIntent $intent,
        string $recipientType,
        int $recipientId,
        array $context,
    ): array {
        $template = $version->template;
        $mapping = self::mapping($intent);
        $slot = CommunicationTemplateRegistry::findBySlug($template->domain, $template->slug)
            ?? CommunicationTemplateRegistry::historicalSlotBySlug($template->domain, $template->slug);
        if ($mapping !== [$template->domain, $template->slug]
            || CommunicationTemplateRegistry::authoringMode($template->domain, $template->slug) === 'builtin'
            || ($slot['event_key'] ?? null) !== $template->event_key
            || $version->communication_template_id !== $template->id
            || (int) $context['institution_id'] !== (int) $template->institution_id
            || ! in_array($version->email_renderer_version, ['notice-email-1', 'notice-message-email-2'], true)
            || ! is_array($version->email_blueprint)
            || ($version->email_blueprint['schema_version'] ?? null) !== 'notice-email-1'
            || ! is_string($version->content_sha256)
            || ! preg_match('/^[a-f0-9]{64}$/D', $version->content_sha256)) {
            throw new \RuntimeException('notice_snapshot_integrity');
        }

        $subject = (string) $version->subject;
        $blueprint = $version->email_blueprint;
        $emailAction = $version->email_renderer_version === 'notice-message-email-2'
            ? $this->freezeEmailAction($version, $slot)
            : null;
        $actual = $this->contextResolver->allowlisted($template->domain, $template->slug, $context['values']);
        $missing = $this->contextResolver->missingRequired($subject, $blueprint, $actual);
        $allowed = array_map(static fn (array $token): string => trim($token['token'], '{} '),
            $slot['tokens'] ?? []);
        $failure = null;
        if (array_diff($missing, $allowed) !== []) {
            $failure = ['code' => 'email_token_unknown'];
            $missing = array_values(array_intersect($missing, $allowed));
        } elseif ($missing !== []) {
            $failure = ['code' => 'email_context_missing', 'missing_context_keys' => $missing];
        }
        $assets = [];
        foreach (NoticeEmailAssetVerifier::assetIds($blueprint) as $id) {
            $asset = CommunicationTemplateDocument::query()->whereKey($id)
                ->where('institution_id', $template->institution_id)
                ->where('communication_template_id', $template->id)->firstOrFail();
            $assets[] = ['id' => $id, 'sha256' => $asset->checksum,
                'cid' => 'notice-'.$id.'@nexiam.inline', 'mime' => $asset->mime_type,
                'filename' => $id.($asset->mime_type === 'image/png' ? '.png' : '.jpg')];
        }
        $print = app(NoticeDocumentPin::class)->freeze($template, $version->document_revision_id);
        if ($print !== null && $template->slug === 'released-online') {
            $binding = $context['print_binding'] ?? null;
            if (! is_array($binding)) {
                throw new \RuntimeException('released_result_binding_missing');
            }
            $print['canonical_released_result'] = $binding;
        } elseif ($print !== null && array_key_exists('print_binding', $context)) {
            throw new \RuntimeException('released_result_binding_invalid');
        }

        return [
            'snapshot_schema_version' => 'notice-delivery-3',
            'institution_id' => (int) $template->institution_id,
            'domain' => $template->domain,
            'event_type' => $intent->eventType,
            'template_key' => $intent->templateKey,
            'canonical_slug' => $template->slug,
            'recipient' => ['type' => $recipientType, 'id' => $recipientId],
            'event_resource' => $context['resource'],
            'template' => ['id' => $template->id, 'version' => (int) $version->version,
                'content_sha256' => $version->content_sha256, 'content_schema_version' => 'notice-content-3',
                'email_schema_version' => 'notice-email-1', 'email_renderer_version' => $version->email_renderer_version],
            'context' => ['values' => $actual, 'missing_required_keys' => $missing],
            'email' => ['subject_template' => $subject, 'blueprint' => $blueprint, 'assets' => $assets,
                'preparation_failure' => $failure, ...($emailAction === null ? [] : ['action' => $emailAction])],
            'portal' => $this->freezePortal($version, $intent, $actual),
            'print' => $print,
        ];
    }

    /** @return array{enabled:false,label:null,route:null,url:null}|array{enabled:true,label:string,route:string,url:string} */
    private function freezeEmailAction(CommunicationTemplateVersion $version, array $slot): array
    {
        if ($version->action_route === null && $version->action_label === null) {
            return ['enabled' => false, 'label' => null, 'route' => null, 'url' => null];
        }

        $routeName = $version->action_route;
        $label = $version->action_label;
        $canonicalRoute = $slot['default_action_route'] ?? null;
        $route = is_string($routeName) ? app('router')->getRoutes()->getByName($routeName) : null;
        if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 64
            || ! is_string($canonicalRoute) || $routeName !== $canonicalRoute
            || $route === null || ! in_array('GET', $route->methods(), true) || $route->parameterNames() !== []) {
            throw new \RuntimeException('notice_snapshot_integrity');
        }

        try {
            $url = rtrim((string) config('app.url'), '/').route($routeName, absolute: false);
        } catch (RouteNotFoundException|UrlGenerationException) {
            throw new \RuntimeException('notice_snapshot_integrity');
        }

        if (! NoticeEmailLinkPolicy::allows($url)) {
            throw new \RuntimeException('notice_snapshot_integrity');
        }

        return ['enabled' => true, 'label' => $label, 'route' => $routeName, 'url' => $url];
    }

    /** @return array{title:string,body:string,action_label:?string,action_url:?string} */
    private function freezePortal(CommunicationTemplateVersion $version, NotificationIntent $intent, array $values): array
    {
        $safe = ['title' => (string) ($intent->payload['subject'] ?? ''), 'body' => (string) ($intent->payload['body'] ?? ''),
            'action_label' => (string) ($intent->payload['action_label'] ?? ''), 'action_url' => (string) ($intent->payload['action_url'] ?? '')];
        $legacy = $version->content_schema_version === 'notice-content-1';
        $title = (string) ($legacy ? $version->in_app_title : $version->subject);
        $body = (string) ($legacy ? $version->in_app_body : $version->body);
        if (trim($title) === '' || trim($body) === ''
            || $this->contextResolver->missingRequired($title, ['body' => $body], $values) !== []) {
            return $safe;
        }
        $substitute = static fn (string $text): string => (string) preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/',
            static fn (array $match): string => $values[trim($match[1])], $text);
        $title = $substitute($title);
        $body = $substitute($body);
        if (str_contains($title.$body, '{{') || str_contains($title.$body, '}}')
            || preg_match('/[\x00-\x1f\x7f]/', $title) || mb_strlen($title) > 255 || mb_strlen($body) > 500) {
            return $safe;
        }

        $label = null;
        $url = null;
        if ($version->action_route !== null && $version->action_route !== '') {
            try {
                $url = rtrim((string) config('app.url'), '/').route($version->action_route, absolute: false);
            } catch (RouteNotFoundException|UrlGenerationException) {
                return $safe;
            }
            $label = $version->action_label;
        }

        return ['title' => $title, 'body' => $body, 'action_label' => $label, 'action_url' => $url];
    }

    private function legacyActionUrl(CommunicationTemplateVersion $version, NotificationIntent $intent): string
    {
        if ($version->action_route === null || $version->action_route === '') {
            return '#';
        }
        $slot = CommunicationTemplateRegistry::findBySlug($version->template->domain, $version->template->slug)
            ?? CommunicationTemplateRegistry::historicalSlotBySlug($version->template->domain, $version->template->slug);
        if ($version->action_route === ($slot['default_action_route'] ?? null) && is_string($intent->payload['action_url'] ?? null)) {
            return $intent->payload['action_url'];
        }
        try {
            return rtrim((string) config('app.url'), '/').route($version->action_route, absolute: false);
        } catch (RouteNotFoundException|UrlGenerationException) {
            return '#';
        }
    }

    private function assertDocumentRequestEmailContainsItems(NotificationIntent $intent, mixed $email): void
    {
        if ($intent->eventType !== 'admission.document_correction_requested.v1') {
            return;
        }

        $containsToken = static function (mixed $value) use (&$containsToken): bool {
            if (is_string($value)) {
                return preg_match('/\\{\\{\\s*document_requests\\s*\\}\\}/', $value) === 1;
            }
            if (! is_array($value)) {
                return false;
            }

            foreach ($value as $child) {
                if ($containsToken($child)) {
                    return true;
                }
            }

            return false;
        };

        if (! $containsToken($email)) {
            throw new \RuntimeException('document_request_email_must_include_itemized_instructions');
        }
    }

    /** @return array<string,mixed>|null */
    private function freezePrint(CommunicationTemplateVersion $version, NotificationIntent $intent, array $context): ?array
    {
        if (! self::permitsPrint($intent->eventType, $intent->templateKey) || $version->document_mode === 'none') {
            return null;
        }
        $print = ['mode' => $version->document_mode];
        if ($version->document_mode === 'builder') {
            $print['blueprint'] = $version->builder_json;
            $legacy = $version->content_schema_version === 'notice-content-1';
            $print['settings'] = ['paper_size' => $version->paper_size ?? ($legacy ? 'a4' : null),
                'orientation' => $version->orientation ?? ($legacy ? 'portrait' : null)];
        } elseif ($version->document_mode === 'docx') {
            $asset = CommunicationTemplateDocument::query()->whereKey($version->document_asset_id)
                ->where('institution_id', $version->template->institution_id)
                ->where('communication_template_id', $version->communication_template_id)->firstOrFail();
            $print['source_asset_id'] = (string) $asset->id;
            $print['sha256'] = $asset->checksum;
        }
        if ($intent->templateKey === 'result.released.online') {
            $print['canonical_released_result'] = $context['print_binding'] ?? ['preparation_failure' => ['code' => 'released_result_data_unavailable']];
        }

        return $print;
    }
}

<?php

namespace App\Notifications\Channels;

use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\User;
use App\Notifications\Contracts\DeliveryChannel;
use App\Notifications\Mail\NotificationMailable;
use App\Notifications\Mail\NotificationMailOutcomeUnknown;
use App\Notifications\Mail\RenderApplicationOpeningAlertMail;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\PracticeNotificationSafety;
use App\Notifications\PracticeReminderSafety;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\Email\NoticeEmailRenderer;
use App\Notifications\Templates\Email\NoticeLegacyEmailRenderer;
use App\Notifications\Templates\Email\NoticeMessageEmailRenderer;
use App\Notifications\Templates\NoticeDeliverySchema3;
use App\Notifications\Templates\NotificationTemplateResolver;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Mail channel (Mailpit in dev). Sends the versioned template snapshot stored
 * on the delivery; records the provider reference on success. Mail failure is
 * surfaced to the worker state machine — never to a toast before delivery.
 */
final class MailDeliveryChannel implements DeliveryChannel
{
    public function __construct(
        private readonly NoticeDocumentAttachmentCompiler $documentCompiler,
        private readonly NoticeEmailRenderer $emailRenderer,
        private readonly NoticeMessageEmailRenderer $messageEmailRenderer,
        private readonly NoticeEmailAssetVerifier $assetVerifier,
        private readonly NoticeLegacyEmailRenderer $legacyRenderer,
    ) {}

    public function channelName(): string
    {
        return NotificationDelivery::CHANNEL_MAIL;
    }

    public function deliver(NotificationDelivery $delivery): ?string
    {
        if (! app(PracticeNotificationSafety::class)->deliveryIsSafe($delivery)) {
            throw new \RuntimeException('practice_notification_delivery_unavailable');
        }
        if ($delivery->recipient_type === ApplicationOpeningSubscription::class) {
            if ($delivery->channel !== NotificationDelivery::CHANNEL_MAIL) {
                throw new \RuntimeException('application_opening_alert_mail_only');
            }
            $recipientEmail = ApplicationOpeningSubscription::query()->whereKey($delivery->recipient_id)->value('email');
            if (! is_string($recipientEmail) || trim($recipientEmail) === '') {
                throw new \RuntimeException('application_opening_alert_recipient_unavailable');
            }
            if (! app(NotificationBusinessValidity::class)->isCurrent($delivery)) {
                throw new \RuntimeException('application_opening_alert_no_longer_current');
            }
            $renderedEmail = app(RenderApplicationOpeningAlertMail::class)->render($delivery);
            $mailable = new NotificationMailable(
                templateKey: $delivery->template_key,
                templateVersion: $delivery->template_version,
                payload: $delivery->payload,
                renderedEmail: $renderedEmail,
            );
            if (! app(NotificationBusinessValidity::class)->isCurrent($delivery)) {
                throw new \RuntimeException('application_opening_alert_no_longer_current');
            }
            try {
                $sent = Mail::to($recipientEmail)->send($mailable);
            } catch (\Throwable $exception) {
                throw new NotificationMailOutcomeUnknown('provider_outcome_unknown', 0, $exception);
            }
            if ($sent === null || ! is_string($sent->getMessageId()) || trim($sent->getMessageId()) === '') {
                throw new NotificationMailOutcomeUnknown('mail_send_unobserved');
            }

            return $sent->getMessageId();
        }
        if ($delivery->event_type === 'exam.reminder.v1'
            && ! app(PracticeReminderSafety::class)->deliveryIsSafe($delivery)) {
            throw new \RuntimeException('practice_reminder_delivery_unavailable');
        }
        $renderedEmail = null;
        $snapshotSchema = $delivery->payload['snapshot_schema_version'] ?? null;
        if (in_array($snapshotSchema, ['notice-delivery-2', 'notice-delivery-3'], true)) {
            $snapshot = $delivery->payload;
            $expectedContentSchema = $snapshotSchema === 'notice-delivery-3' ? 'notice-content-3' : null;
            if (($snapshot['recipient']['type'] ?? null) !== $delivery->recipient_type
                || (string) ($snapshot['recipient']['id'] ?? '') !== (string) $delivery->recipient_id
                || ($snapshot['event_type'] ?? null) !== $delivery->event_type
                || ($snapshot['template_key'] ?? null) !== $delivery->template_key
                || (int) ($snapshot['template']['version'] ?? 0) !== (int) $delivery->template_version
                || ($snapshotSchema === 'notice-delivery-3'
                    && ! is_array($snapshot['context']['missing_required_keys'] ?? null))
                || ($snapshotSchema !== 'notice-delivery-3' && ($snapshot['context']['missing_required_keys'] ?? []) !== [])) {
                throw new \RuntimeException('notice_snapshot_integrity');
            }
            if ($snapshotSchema === 'notice-delivery-3'
                && ! NoticeDeliverySchema3::valid($snapshot)) {
                throw new \RuntimeException('notice_snapshot_integrity');
            }
            if (($snapshot['print'] ?? null) !== null && ! NotificationTemplateResolver::permitsPrint($delivery->event_type, $delivery->template_key)) {
                throw new \RuntimeException('notice_print_event_ineligible');
            }
            $failure = $snapshot['email']['preparation_failure'] ?? null;
            if ($snapshotSchema === 'notice-delivery-3' && $failure !== null && ! is_array($failure)) {
                throw new \RuntimeException('notice_snapshot_integrity');
            }
            if (is_array($failure)) {
                if (($failure['code'] ?? null) === 'email_context_missing') {
                    $missingKeys = $failure['missing_context_keys'] ?? null;
                    if ($snapshotSchema === 'notice-delivery-3'
                        && (! is_array($missingKeys) || ! array_is_list($missingKeys) || $missingKeys === []
                            || $missingKeys !== $snapshot['context']['missing_required_keys']
                            || array_filter($missingKeys, static fn (mixed $key): bool => ! is_string($key) || $key === '') !== [])) {
                        throw new \RuntimeException('notice_snapshot_integrity');
                    }
                    throw new \RuntimeException('email_context_missing:'.implode(',', $missingKeys ?? []));
                }
                throw new \RuntimeException(($failure['code'] ?? null) === 'email_token_unknown' ? 'email_token_unknown' : 'notice_snapshot_integrity');
            }
            if ($snapshotSchema === 'notice-delivery-3' && $snapshot['context']['missing_required_keys'] !== []) {
                throw new \RuntimeException('notice_snapshot_integrity');
            }
            if (($snapshot['template']['content_schema_version'] ?? null) === 'notice-content-1') {
                $renderedEmail = $this->legacyRenderer->render($snapshot, $delivery->template_key, $delivery->template_version);
            } elseif (in_array(($snapshot['template']['content_schema_version'] ?? null), ['notice-content-2', 'notice-content-3'], true)) {
                $assets = $this->assetVerifier->verify(
                    (int) $snapshot['institution_id'], $snapshot['template']['id'],
                    $snapshot['email']['blueprint'], $snapshot['email']['assets'],
                );
                if (($snapshot['template']['content_schema_version'] ?? null) === 'notice-content-3') {
                    $renderedEmail = $this->messageEmailRenderer->render(
                        $snapshot['email']['blueprint'], $snapshot['context']['values'],
                        array_keys($snapshot['context']['values']), $snapshot['template']['email_schema_version'],
                        $snapshot['template']['email_renderer_version'], $assets, $snapshot['email']['subject_template'],
                        $snapshot['email']['action'] ?? null,
                    );
                } else {
                    $renderedEmail = $this->emailRenderer->render(
                        $snapshot['email']['blueprint'], $snapshot['context']['values'],
                        array_keys($snapshot['context']['values']), $snapshot['template']['email_schema_version'],
                        $snapshot['template']['email_renderer_version'], $assets, $snapshot['email']['subject_template'],
                    );
                }
            } else {
                throw new \RuntimeException('email_renderer_unavailable');
            }
        } elseif ($snapshotSchema !== null) {
            throw new \RuntimeException('notice_delivery_schema_unavailable');
        }
        if ($snapshotSchema === null && is_array($delivery->payload['email_preparation_failure'] ?? null)) {
            throw new \RuntimeException('frozen_email_preparation_failed');
        }
        $email = $snapshotSchema === 'notice-delivery-3' ? ($delivery->payload['recipient']['email'] ?? null) : match ($delivery->recipient_type) {
            User::class => User::query()->whereKey($delivery->recipient_id)->value('email'),
            ApplicantIdentity::class => ApplicantIdentity::query()->whereKey($delivery->recipient_id)->value('email'),
            ApplicationOpeningSubscription::class => ApplicationOpeningSubscription::query()->whereKey($delivery->recipient_id)->value('email'),
            default => null,
        };
        if (($delivery->payload['practice_fixture_set_id'] ?? null) !== null) {
            $email = $delivery->payload['practice_recipient_email'] ?? null;
            if (! is_string($email) || ! str_ends_with(strtolower($email), '.test')) {
                throw new \RuntimeException('practice_notification_recipient_unavailable');
            }
        }

        if (! is_string($email) || trim($email) === '') {
            throw new \RuntimeException('Mail recipient or address is unavailable.');
        }

        $attachmentPath = null;
        $attachmentName = null;
        try {
            $snapshot = in_array($snapshotSchema, ['notice-delivery-2', 'notice-delivery-3'], true)
                ? ($delivery->payload['print'] ?? null)
                : ([$delivery->event_type, $delivery->template_key] === ['exam.reminder.v1', 'exam.reminder']
                    ? null : ($delivery->payload['document_snapshot'] ?? null));
            if (is_array($snapshot)) {
                if (in_array($snapshotSchema, ['notice-delivery-2', 'notice-delivery-3'], true)) {
                    $attachmentPath = $this->documentCompiler->compileFrozenPdf($snapshot, $delivery);
                    $attachmentName = 'notice.pdf';
                } else {
                    $mode = $snapshot['mode'] ?? null;
                    $attachmentName = 'notice.pdf';
                    if ($mode === 'builder' && is_array($snapshot['builder_json'] ?? null)) {
                        $attachmentPath = $this->documentCompiler->compileBuilderPdf(
                            $snapshot['builder_json'],
                            (string) ($snapshot['paper_size'] ?? 'a4'),
                            (string) ($snapshot['orientation'] ?? 'portrait'),
                            $attachmentName,
                            $delivery,
                        );
                    } elseif ($mode === 'builder') {
                        throw new \RuntimeException('Frozen builder document snapshot is incomplete.');
                    } elseif ($mode === 'docx'
                        && is_string($snapshot['document_path'] ?? null)
                        && is_string($snapshot['document_checksum'] ?? null)) {
                        $docxPath = $this->privateStoragePath($snapshot['document_path']);
                        $attachmentPath = $this->documentCompiler->compileNoticePdf(
                            $docxPath,
                            is_array($delivery->payload['event_context'] ?? null) ? $delivery->payload['event_context'] : [],
                            $attachmentName,
                            $snapshot['document_checksum'],
                            $delivery,
                        );
                    } elseif ($mode === 'result_document') {
                        $attachmentPath = $this->documentCompiler->compileResultDocumentPdf(
                            $snapshot,
                            $attachmentName,
                            $delivery,
                        );
                    } elseif ($mode === 'docx') {
                        throw new \RuntimeException('Frozen DOCX document snapshot is incomplete.');
                    } else {
                        $attachmentName = null;
                    }
                }
            }

            $mailable = new NotificationMailable(
                templateKey: $delivery->template_key,
                templateVersion: $delivery->template_version,
                payload: $delivery->payload,
                attachmentPath: $attachmentPath,
                attachmentName: $attachmentName,
                renderedEmail: $renderedEmail,
            );
            try {
                $sent = Mail::to($email)->send($mailable);
            } catch (\Throwable $exception) {
                throw new NotificationMailOutcomeUnknown('provider_outcome_unknown', 0, $exception);
            }
            if ($sent === null || ! is_string($sent->getMessageId()) || trim($sent->getMessageId()) === '') {
                throw new NotificationMailOutcomeUnknown('mail_send_unobserved');
            }

            return $sent->getMessageId();
        } finally {
            if ($attachmentPath !== null) {
                if (is_file($attachmentPath)) {
                    @unlink($attachmentPath);
                }
                $directory = dirname($attachmentPath);
                if (str_starts_with(basename($directory), 'notice_pdf_') && is_dir($directory)) {
                    @rmdir($directory);
                }
            }
        }
    }

    private function privateStoragePath(string $relativePath): string
    {
        $storageRoot = realpath(Storage::disk('local')->path(''));
        $resolvedPath = realpath(Storage::disk('local')->path($relativePath));
        if ($storageRoot === false
            || $resolvedPath === false
            || ! str_starts_with($resolvedPath, $storageRoot.DIRECTORY_SEPARATOR)
            || ! is_file($resolvedPath)) {
            throw new \RuntimeException('Frozen notice document is unavailable in private storage.');
        }

        return $resolvedPath;
    }
}

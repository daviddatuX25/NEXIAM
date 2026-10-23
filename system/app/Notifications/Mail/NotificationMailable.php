<?php

namespace App\Notifications\Mail;

use App\Notifications\Templates\NoticeDeliverySchema3;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Versioned-template mailer. The template key + version are frozen on the
 * delivery row; content is rendered from the snapshot payload at send time.
 * The message id is captured after send so the delivery row can record a
 * provider reference (truthful "delivered" evidence).
 */
class NotificationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public ?string $messageId = null;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $templateKey,
        public readonly int $templateVersion,
        public readonly array $payload,
        public readonly ?string $attachmentPath = null,
        public readonly ?string $attachmentName = null,
        public readonly ?array $renderedEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedEmail['subject'] ?? (string) ($this->payload['subject'] ?? 'NEXIAM notification'),
            using: $this->renderedEmail === null ? [] : [function (Email $message): void {
                $message->text($this->renderedEmail['text']);
                foreach ($this->renderedEmail['assets'] as $asset) {
                    $part = (new DataPart($asset['bytes'], $asset['filename'], $asset['mime']))
                        ->asInline()->setContentId($asset['cid']);
                    $message->addPart($part);
                }
            }],
        );
    }

    public function attachments(): array
    {
        $schema3 = ($this->payload['snapshot_schema_version'] ?? null) === 'notice-delivery-3';
        if ($schema3 && ! NoticeDeliverySchema3::valid($this->payload)) {
            throw new \RuntimeException('notice_snapshot_integrity');
        }
        if ($schema3 && ($this->payload['print'] ?? null) !== null && $this->attachmentPath === null) {
            throw new \RuntimeException('notice_print_attachment_missing');
        }
        $path = $schema3 ? $this->attachmentPath : ($this->attachmentPath ?? ($this->payload['attachment_path'] ?? null));

        if ($path !== null) {
            if (! is_file($path)) {
                throw new \RuntimeException("Notification attachment file does not exist: [{$path}]");
            }
            $attachment = Attachment::fromPath($path);
            $name = $this->attachmentName ?? ($this->payload['attachment_name'] ?? null);
            if ($name !== null && is_string($name) && trim($name) !== '') {
                $attachment->as($name);
            }

            return [$attachment];
        }

        return [];
    }

    public function content(): Content
    {
        $snapshotSchema = $this->payload['snapshot_schema_version'] ?? null;
        if ($snapshotSchema === 'notice-delivery-3'
            && ! NoticeDeliverySchema3::valid($this->payload)) {
            throw new \RuntimeException('notice_snapshot_integrity');
        }
        if (in_array($snapshotSchema, ['notice-delivery-2', 'notice-delivery-3', 'application-opening-alert-1'], true)) {
            if ($this->renderedEmail === null) {
                throw new \RuntimeException('notice_email_not_prepared');
            }

            return new Content(htmlString: $this->renderedEmail['production_html']);
        }
        $body = (string) ($this->payload['body'] ?? 'Your NEXIAM notification.');
        $bodyMarkup = ($this->payload['body_is_html'] ?? false) === true
            ? $body
            : nl2br($this->e($body));
        $actionUrl = (string) ($this->payload['action_url'] ?? url('/'));
        $actionLabel = (string) ($this->payload['action_label'] ?? 'View in NEXIAM');

        return new Content(
            htmlString: <<<HTML
            <div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;">
              <h1 style="color:#0f172a;font-size:20px;margin:0 0 16px;">NEXIAM</h1>
              {$bodyMarkup}
              <p style="margin:24px 0;">
                <a href="{$this->e($actionUrl)}" style="background:#2563eb;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;">{$this->e($actionLabel)}</a>
              </p>
              <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">
              <small style="color:#94a3b8;font-size:12px;">Template: {$this->e($this->templateKey)} v{$this->templateVersion}</small>
            </div>
            HTML,
        );
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

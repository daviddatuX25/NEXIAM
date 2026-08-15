<?php

namespace App\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) ($this->payload['subject'] ?? 'NEXIAM notification'),
        );
    }

    public function content(): Content
    {
        $body = (string) ($this->payload['body'] ?? 'Your NEXIAM notification.');
        $actionUrl = (string) ($this->payload['action_url'] ?? url('/'));
        $actionLabel = (string) ($this->payload['action_label'] ?? 'View in NEXIAM');

        return new Content(
            htmlString: <<<HTML
            <div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;">
              <h1 style="color:#0f172a;font-size:20px;margin:0 0 16px;">NEXIAM</h1>
              <p style="color:#334155;font-size:15px;line-height:1.6;">{$this->e($body)}</p>
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

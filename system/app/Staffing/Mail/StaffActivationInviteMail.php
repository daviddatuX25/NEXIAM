<?php

namespace App\Staffing\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class StaffActivationInviteMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public string $activationUrl,
        public string $publicReference,
        public string $fallbackCode,
        public ?string $expiresAt = null,
        public string $timezone = 'UTC',
    ) {
        $this->afterCommit();
    }

    public function build(): static
    {
        $name = htmlspecialchars($this->recipient->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $activationUrl = htmlspecialchars($this->activationUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $publicReference = htmlspecialchars($this->publicReference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fallbackCode = htmlspecialchars($this->fallbackCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $expiry = $this->expiresAt === null
            ? 'No calendar expiry. This voucher remains single-use and revocable.'
            : 'This invitation expires '.htmlspecialchars(Carbon::parse($this->expiresAt)->setTimezone($this->timezone)->format('F j, Y g:i A T (e)'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'.';

        return $this->subject('NEXIAM Staff Account Activation')
            ->html(<<<HTML
                <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 24px;">
                    <p style="font-size: 13px; font-weight: 700; letter-spacing: 0.12em; color: #1d4ed8;">NEXIAM</p>
                    <h1 style="font-size: 24px; margin-bottom: 8px;">Activate Staff Account</h1>
                    <p>Hello {$name},</p>
                    <p>Your NEXIAM staff account is ready to activate. Use the secure link below to continue.</p>
                    <p style="margin: 28px 0;"><a href="{$activationUrl}" style="display: inline-block; background: #1d4ed8; color: #ffffff; padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: 700;">Activate Staff Account</a></p>
                    <p><strong>Reference code:</strong> {$publicReference}</p>
                    <p><strong>Fallback code:</strong> {$fallbackCode}</p>
                    <p>{$expiry} If you did not expect this invitation, contact your NEXIAM administrator.</p>
                </div>
                HTML);
    }
}

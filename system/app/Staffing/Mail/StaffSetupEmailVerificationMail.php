<?php

namespace App\Staffing\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffSetupEmailVerificationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public string $confirmationUrl;

    public function __construct(public User $recipient, string $plainToken)
    {
        $this->confirmationUrl = route('staff.account-setup.email.confirm', ['token' => $plainToken]);
        $this->afterCommit();
    }

    public function build(): static
    {
        $name = htmlspecialchars($this->recipient->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $confirmationUrl = htmlspecialchars($this->confirmationUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $this->subject('Confirm your NEXIAM staff email address')
            ->html(<<<HTML
                <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 24px;">
                    <p style="font-size: 13px; font-weight: 700; letter-spacing: 0.12em; color: #1d4ed8;">NEXIAM</p>
                    <h1 style="font-size: 24px; margin-bottom: 8px;">Confirm your staff account email</h1>
                    <p>Hello {$name},</p>
                    <p>Confirm this address for your NEXIAM staff account. Your address remains unverified until you explicitly confirm.</p>
                    <p style="margin: 28px 0;"><a href="{$confirmationUrl}" style="display: inline-block; background: #1d4ed8; color: #ffffff; padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: 700;">Review email confirmation</a></p>
                    <p>This link expires in 24 hours. If you did not request it, you can ignore this message.</p>
                </div>
                HTML);
    }
}

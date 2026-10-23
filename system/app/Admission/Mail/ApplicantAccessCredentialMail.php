<?php

namespace App\Admission\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicantAccessCredentialMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $applicantName,
        public readonly string $referenceCode,
        public readonly string $accessKey,
        public readonly string $qrLoginUrl,
        public readonly string $expiresAtIso,
    ) {
        $this->afterCommit();
    }

    public function build(): static
    {
        $name = htmlspecialchars($this->applicantName !== '' ? $this->applicantName : 'Applicant', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $referenceCode = htmlspecialchars($this->referenceCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $accessKey = htmlspecialchars($this->accessKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $qrLoginUrl = htmlspecialchars($this->qrLoginUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $expiresAtIso = htmlspecialchars($this->expiresAtIso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $this->subject("NEXIAM Applicant Portal Access ({$this->referenceCode})")
            ->html(<<<HTML
                <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 24px;">
                    <p style="font-size: 13px; font-weight: 700; letter-spacing: 0.12em; color: #1d4ed8;">NEXIAM ADMISSIONS</p>
                    <h1 style="font-size: 24px; margin-bottom: 8px;">Your Applicant Portal Credentials</h1>
                    <p>Hello {$name},</p>
                    <p>Your admission application has been recorded under reference code <strong>{$referenceCode}</strong>. Keep these credentials safe to track your application, examination schedule, and admission progress.</p>
                    <p style="margin: 28px 0;">
                        <a href="{$qrLoginUrl}" style="display: inline-block; background: #1d4ed8; color: #ffffff; padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: 700;">Sign In with Temporary Voucher (24h)</a>
                    </p>
                    <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin: 20px 0;">
                        <p style="margin: 0 0 8px 0;"><strong>Applicant Reference:</strong> <span style="font-family: monospace;">{$referenceCode}</span></p>
                        <p style="margin: 0 0 8px 0;"><strong>Durable Access Key:</strong> <span style="font-family: monospace; font-size: 18px; letter-spacing: 0.1em; color: #1d4ed8;">{$accessKey}</span></p>
                        <p style="margin: 0; font-size: 13px; color: #4b5563;">The one-tap sign-in voucher above is single-use and expires at {$expiresAtIso}. Your 8-character Access Key remains valid with your birthdate at any time unless reissued by the Registrar.</p>
                    </div>
                </div>
                HTML);
    }
}

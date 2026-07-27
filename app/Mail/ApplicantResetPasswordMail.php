<?php

namespace App\Mail;

use App\Models\Applicant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicantResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;

    public function __construct(
        public Applicant $applicant,
        string $token
    ) {
        $this->resetUrl = url("/portal/reset/{$token}");
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SecureCAT — Reset Your Applicant Portal Password',
            from: config('mail.from.address', 'noreply@securecat.local'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.applicant-reset-password',
        );
    }
}

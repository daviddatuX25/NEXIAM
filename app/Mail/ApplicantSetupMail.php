<?php

namespace App\Mail;

use App\Models\Applicant;
use App\Services\AdmissionSlipService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicantSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $setupUrl;

    public string $applicantName;

    public ?string $referenceNumber;

    public int $tokenExpiryHours;

    public bool $admissionSlipAttached;

    public function __construct(
        public Applicant $applicant
    ) {
        $this->applicant->loadMissing('application');
        $application = $this->applicant->application;

        $this->setupUrl = url("/portal/setup/{$applicant->setup_token}");
        $this->applicantName = trim(($application?->first_name ?? '').' '.($application?->last_name ?? '')) ?: 'Applicant';
        $this->referenceNumber = $application?->reference_number;
        $this->tokenExpiryHours = (int) config('auth.setup_token_expires_hours', 72);
        $this->admissionSlipAttached = AdmissionSlipService::isEnabled() && $application?->status === 'accepted';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SecureCAT — Application Accepted: Set Up Your Portal',
            from: config('mail.from.address', 'noreply@securecat.local'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.applicant-setup',
        );
    }

    public function attachments(): array
    {
        if (! $this->admissionSlipAttached) {
            return [];
        }

        $application = $this->applicant->application;
        if (! $application) {
            return [];
        }

        try {
            $pdf = app(AdmissionSlipService::class)->generatePdf($application);

            return [
                Attachment::fromData(fn () => $pdf->output(), "admission-slip-{$application->reference_number}.pdf")
                    ->mime('application/pdf'),
            ];
        } catch (\Throwable $e) {
            \Log::warning('Failed to attach admission slip to setup email', [
                'applicant_id' => $this->applicant->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}

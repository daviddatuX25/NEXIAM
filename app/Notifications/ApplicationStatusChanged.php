<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Application $application,
        public readonly string $oldStatus,
        public readonly string $newStatus
    ) {}

    public function via(object $notifiable): array
    {
        // Skip mail for 'accepted' — the account setup email covers the acceptance notification
        if ($this->newStatus === 'accepted') {
            return ['database'];
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabels = [
            'pending' => 'Pending Review',
            'accepted' => 'Accepted',
            'dismissed' => 'Dismissed',
        ];

        return (new MailMessage)
            ->subject('SecureCAT — Application Update')
            ->view('emails.application-status-changed', [
                'applicantName' => $this->application->first_name ?? 'Applicant',
                'referenceNumber' => $this->application->reference_number,
                'newStatus' => $this->newStatus,
                'statusLabel' => $statusLabels[$this->newStatus] ?? ucfirst($this->newStatus),
                'rejectionReason' => $this->application->rejection_reason,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_status_changed',
            'application_id' => $this->application->id,
            'message' => "Your application status changed from {$this->oldStatus} to {$this->newStatus}",
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\ConsultationSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultReleased extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ConsultationSummary $summary) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $application = $notifiable->application ?? null;
        $applicantName = $application?->first_name ?? 'Applicant';

        return (new MailMessage)
            ->subject('SecureCAT — Your Exam Results Are Available')
            ->view('emails.result-released', [
                'applicantName' => $applicantName,
                'portalUrl' => url('/portal'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'result_released',
            'summary_id' => $this->summary->id,
            'message' => 'Your exam results are now available.',
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\ExamSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExamSessionPostponed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ExamSession $session) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $application = $notifiable->application ?? null;
        $applicantName = $application?->first_name ?? 'Applicant';

        return (new MailMessage)
            ->subject('SecureCAT — Your Exam Session Has Been Postponed')
            ->view('emails.exam-session-postponed', [
                'applicantName' => $applicantName,
                'sessionDate' => $this->session->scheduled_at?->format('F j, Y') ?? $this->session->date?->format('F j, Y') ?? 'TBA',
                'sessionTime' => $this->session->scheduled_at?->format('g:i A') ?? '',
                'sessionRoom' => $this->session->room?->name ?? 'TBA',
                'portalUrl' => url('/portal'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'exam_session_postponed',
            'session_id' => $this->session->id,
            'message' => 'Your exam session has been postponed. A new schedule will be announced.',
        ];
    }
}

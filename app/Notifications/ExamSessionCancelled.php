<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ExamSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExamSessionCancelled extends Notification implements ShouldQueue
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
            ->subject('SecureCAT — Exam Session Cancelled')
            ->view('emails.exam-session-cancelled', [
                'applicantName' => $applicantName,
                'sessionDate' => $this->session->scheduled_at?->format('F j, Y') ?? $this->session->date?->format('F j, Y') ?? 'TBA',
                'sessionRoom' => $this->session->room?->name,
                'portalUrl' => url('/portal'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_cancelled',
            'session_id' => $this->session->id,
            'message' => "The exam session for {$this->session->room?->name} has been cancelled.",
        ];
    }
}

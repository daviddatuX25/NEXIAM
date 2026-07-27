<?php

namespace App\Notifications;

use App\Models\ExamSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExamSessionStaffPostponed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ExamSession $session) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $staffName = $notifiable->name ?? 'Staff';

        return (new MailMessage)
            ->subject('SecureCAT — Exam Session Postponed')
            ->view('emails.exam-session-staff-postponed', [
                'staffName' => $staffName,
                'sessionDate' => $this->session->scheduled_at?->format('F j, Y') ?? $this->session->date?->format('F j, Y') ?? 'TBA',
                'sessionTime' => $this->session->scheduled_at?->format('g:i A') ?? '',
                'sessionRoom' => $this->session->room?->name ?? 'TBA',
                'roomBuilding' => $this->session->room?->building ?? '',
                'dashboardUrl' => route('admin.exam-scheduling.show', $this->session),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'exam_session_staff_postponed',
            'session_id' => $this->session->id,
            'message' => 'An exam session on '.
                ($this->session->date?->format('M j, Y') ?? 'TBA').' has been postponed.',
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\ExamSession;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExamSessionStaffAssigned extends Notification implements ShouldQueue
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
        $applicantCount = $this->session->applicants()->count();

        return (new MailMessage)
            ->subject('SecureCAT — Exam Session Assignment')
            ->view('emails.exam-session-staff-assigned', [
                'staffName' => $staffName,
                'sessionDate' => $this->session->scheduled_at?->format('F j, Y') ?? $this->session->date?->format('F j, Y') ?? 'TBA',
                'sessionTime' => $this->session->scheduled_at?->format('g:i A') ?? '',
                'endTime' => $this->session->end_time ? Carbon::parse($this->session->end_time)->format('g:i A') : '',
                'sessionRoom' => $this->session->room?->name ?? 'TBA',
                'roomBuilding' => $this->session->room?->building ?? '',
                'applicantCount' => $applicantCount,
                'dashboardUrl' => route('admin.exam-scheduling.show', $this->session),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'exam_session_staff_assigned',
            'session_id' => $this->session->id,
            'message' => 'You have been assigned to an exam session on '.
                ($this->session->date?->format('M j, Y') ?? 'TBA').'.',
        ];
    }
}

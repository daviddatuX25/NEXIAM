<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ExamSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExamSessionCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ExamSession $session) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_completed',
            'session_id' => $this->session->id,
            'message' => "The exam session for {$this->session->room?->name} has been completed.",
            'url' => route('proctor.sessions.show', $this->session),
        ];
    }
}

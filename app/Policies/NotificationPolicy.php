<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPolicy
{
    /**
     * View a notification. User must own it.
     */
    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && $notification->notifiable_id === $user->getKey();
    }

    /**
     * Mark notification as read. User must own it.
     */
    public function markAsRead(User $user, DatabaseNotification $notification): bool
    {
        return $this->view($user, $notification);
    }

    /**
     * Delete notification. User must own it.
     */
    public function delete(User $user, DatabaseNotification $notification): bool
    {
        return $this->view($user, $notification);
    }
}

<?php

namespace App\Examination\Support;

use App\Models\Examination\ProctorDuty;
use App\Models\User;

final class ProctorDutyGate
{
    public static function hasActiveDuty(User $user, int $examSessionId): bool
    {
        return ProctorDuty::query()
            ->where('exam_session_id', $examSessionId)
            ->where('user_id', $user->id)
            ->where('status', ProctorDuty::STATUS_ACTIVE)
            ->exists();
    }
}

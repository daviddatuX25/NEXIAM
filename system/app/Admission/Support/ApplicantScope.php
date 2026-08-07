<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccount;
use App\Models\User;

final class ApplicantScope
{
    public static function attemptBelongsToUser(AdmissionAttempt $attempt, User $user): bool
    {
        $account = ApplicantAccount::query()
            ->where('user_id', $user->id)
            ->first();

        if ($account === null) {
            return false;
        }

        return (int) $attempt->applicant_identity_id === (int) $account->applicant_identity_id;
    }

    public static function identityIdForUser(User $user): ?int
    {
        return ApplicantAccount::query()
            ->where('user_id', $user->id)
            ->value('applicant_identity_id');
    }
}

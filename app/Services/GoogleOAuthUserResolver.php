<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserCredential;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleOAuthUserResolver
{
    public function findOrAttachUser(SocialiteUser $socialUser): ?User
    {
        $credential = UserCredential::where('provider', UserCredential::PROVIDER_GOOGLE)
            ->where('identifier', $socialUser->getId())
            ->first();

        if ($credential) {
            return $credential->user;
        }

        $googleEmail = strtolower(trim($socialUser->getEmail()));
        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$googleEmail])->first();

        if (! $user) {
            return null;
        }

        UserCredential::create([
            'user_id'    => $user->id,
            'provider'   => UserCredential::PROVIDER_GOOGLE,
            'identifier' => $socialUser->getId(),
        ]);

        return $user;
    }
}

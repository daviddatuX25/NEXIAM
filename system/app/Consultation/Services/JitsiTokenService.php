<?php

declare(strict_types=1);

namespace App\Consultation\Services;

use App\Models\Consultation\ConsultationEncounter;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class JitsiTokenService
{
    public function issue(ConsultationEncounter $encounter, User $user, string $role, ?Carbon $expiresAt = null): string
    {
        if (! in_array($role, ['moderator', 'participant'], true)) {
            throw new InvalidArgumentException('Unsupported Jitsi participant role.');
        }

        $secret = (string) config('jitsi.app_secret', '');
        $audience = (string) config('jitsi.audience', '');
        if (strlen($secret) < 32 || $audience === '' || $encounter->room_name === null || $encounter->room_name === '') {
            throw new InvalidArgumentException('Jitsi media token configuration or encounter context is invalid.');
        }

        $now = now()->timestamp;
        $maximumTtl = min(900, max(1, (int) config('jitsi.jwt_ttl_seconds', 900)));
        $expiration = min(
            $now + $maximumTtl,
            $expiresAt?->timestamp ?? PHP_INT_MAX,
        );

        if ($expiration <= $now) {
            throw new InvalidArgumentException('The consultation access window has expired.');
        }

        $isModerator = $role === 'moderator';
        $claims = [
            'sub' => (string) config('jitsi.domain'),
            'aud' => $audience,
            'iss' => (string) config('jitsi.app_id'),
            'room' => $encounter->room_name,
            'context' => [
                'user' => [
                    'id' => $this->participantId($user),
                    'moderator' => $isModerator,
                    'affiliation' => $isModerator ? 'owner' : 'member',
                ],
                'nexiam' => [
                    'version' => 1,
                    'encounter_id' => (string) $encounter->getKey(),
                    'institution_id' => (string) $encounter->institution_id,
                    'attempt_id' => (string) $encounter->admission_attempt_id,
                    'schedule_generation' => (int) $encounter->schedule_generation,
                    'consultation_policy_version' => (int) $encounter->consultation_policy_version,
                ],
            ],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiration,
            'schedule_generation' => (int) $encounter->schedule_generation,
        ];

        return JWT::encode($claims, $secret, 'HS256');
    }

    public function participantId(User $user): string
    {
        return hash_hmac('sha256', 'jitsi-user:'.$user->getKey(), (string) config('jitsi.app_secret'));
    }
}

<?php

namespace App\Admission\Support;

/**
 * Stable normalized identity key for INV-002 duplicate control.
 * Does not treat Applicant No as a secret.
 */
final class NormalizedIdentityKey
{
    public static function make(
        string $firstName,
        string $lastName,
        string $birthdate,
        string $sex,
        ?string $middleName = null,
    ): string {
        $parts = [
            mb_strtolower(trim($firstName)),
            mb_strtolower(trim((string) $middleName)),
            mb_strtolower(trim($lastName)),
            trim($birthdate),
            mb_strtolower(trim($sex)),
        ];

        return hash('sha256', implode('|', $parts));
    }
}

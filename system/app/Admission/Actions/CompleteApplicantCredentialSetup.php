<?php

namespace App\Admission\Actions;

/**
 * @deprecated D-085 — free-form password setup removed.
 * Use AuthenticateApplicantWithAccessKey (access key + birthdate) instead.
 */
class CompleteApplicantCredentialSetup
{
    /**
     * @deprecated
     */
    public function handle(string $tokenPlaintext, string $email, string $password, string $name): never
    {
        unset($tokenPlaintext, $email, $password, $name);

        throw new \RuntimeException(
            'Applicant password setup was removed (D-085). Use access key + birthdate authentication.',
        );
    }
}

<?php

namespace App\Admission\Data;

/**
 * Validated public intake shape — mirrors legacy Apply fields with greenfield REWRITE notes.
 *
 * Disposition vs legacy:
- PRESERVE: identity, contact, school, strand, address, three program prefs, terms + Privacy Policy dialog
- RETIRE: catalog-driven digital grades; hard-copy Form 138/TOR evidence remains separate
- REWRITE: program prefs use Program.code; access key hashed only (D-085)
- REJECT: accept_immediately on public path; hard-coded subject columns
 */
final class AdmissionIntakeData
{
    /**
     * @param  list<string>  $programPreferenceCodes  1–3 distinct active, admission-offered program codes; index 0 required
     * @param  array<string, string>  $grades  field code => numeric string value
     */
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $birthdate,
        public readonly string $sex,
        public readonly string $contactEmail,
        public readonly string $applicantType,
        public readonly array $programPreferenceCodes,
        public readonly array $grades = [],
        public readonly ?string $middleName = null,
        public readonly ?string $suffix = null,
        public readonly ?string $contactPhone = null,
        public readonly ?string $lastSchoolEnrolled = null,
        public readonly ?string $strand = null,
        public readonly ?string $addressLine = null,
        public readonly ?string $city = null,
        public readonly ?string $province = null,
        public readonly ?string $zipCode = null,
        public readonly bool $termsAccepted = false,
        public readonly ?int $publicPolicyId = null,
        public readonly ?int $publicPolicyRevisionId = null,
        public readonly ?string $publicPolicyRevisionDigest = null,
        public readonly ?float $gwa = null,
        public readonly ?string $gwaScale = null,
    ) {}

    public function primaryProgramCode(): string
    {
        return $this->programPreferenceCodes[0];
    }

    public function gwa(): ?string
    {
        return $this->gwa === null ? null : (string) $this->gwa;
    }
}

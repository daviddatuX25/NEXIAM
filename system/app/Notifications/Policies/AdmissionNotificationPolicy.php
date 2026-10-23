<?php

namespace App\Notifications\Policies;

use InvalidArgumentException;

final readonly class AdmissionNotificationPolicy
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public int $version,
        public bool $applicantStatusEmailEnabled,
        public bool $registrarNewSubmissionAlertEnabled,
    ) {}

    public static function defaults(): self
    {
        return new self(
            version: 0,
            applicantStatusEmailEnabled: true,
            registrarNewSubmissionAlertEnabled: true,
        );
    }

    /**
     * @param  array<string, mixed>|null  $policy
     */
    public static function fromStorage(?array $policy): self
    {
        if ($policy === null) {
            return self::defaults();
        }

        self::assertKeys($policy, [
            'schema_version',
            'version',
            'applicant_status_email_enabled',
            'registrar_new_submission_alert_enabled',
        ]);

        if (($policy['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || ! is_int($policy['version'] ?? null)
            || $policy['version'] < 0
            || ! is_bool($policy['applicant_status_email_enabled'] ?? null)
            || ! is_bool($policy['registrar_new_submission_alert_enabled'] ?? null)) {
            throw new InvalidArgumentException('Stored Admissions notification policy is invalid.');
        }

        return new self(
            version: $policy['version'],
            applicantStatusEmailEnabled: $policy['applicant_status_email_enabled'],
            registrarNewSubmissionAlertEnabled: $policy['registrar_new_submission_alert_enabled'],
        );
    }

    /**
     * @param  array{applicant_status_email_enabled: bool, registrar_new_submission_alert_enabled: bool}  $values
     */
    public function next(array $values): self
    {
        return new self(
            version: $this->version + 1,
            applicantStatusEmailEnabled: $values['applicant_status_email_enabled'],
            registrarNewSubmissionAlertEnabled: $values['registrar_new_submission_alert_enabled'],
        );
    }

    /**
     * @return array{schema_version: int, version: int, applicant_status_email_enabled: bool, registrar_new_submission_alert_enabled: bool}
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'version' => $this->version,
            'applicant_status_email_enabled' => $this->applicantStatusEmailEnabled,
            'registrar_new_submission_alert_enabled' => $this->registrarNewSubmissionAlertEnabled,
        ];
    }

    /**
     * @param  array<string, mixed>  $policy
     * @param  list<string>  $allowed
     */
    private static function assertKeys(array $policy, array $allowed): void
    {
        if (array_diff(array_keys($policy), $allowed) !== []) {
            throw new InvalidArgumentException('Stored Admissions notification policy contains unsupported fields.');
        }
    }
}

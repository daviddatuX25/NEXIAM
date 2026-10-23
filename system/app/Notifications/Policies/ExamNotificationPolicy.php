<?php

namespace App\Notifications\Policies;

use InvalidArgumentException;

final readonly class ExamNotificationPolicy
{
    public const SCHEMA_VERSION = 1;

    /** @var list<int> */
    public const DEFAULT_REMINDER_OFFSETS_MINUTES = [1440];

    /** @var list<int> */
    public const ALLOWED_REMINDER_OFFSETS_MINUTES = [60, 120, 1440, 4320];

    /**
     * @param  list<int>  $reminderOffsetsMinutes
     */
    public function __construct(
        public int $version,
        public bool $publicationEmailEnabled,
        public array $reminderOffsetsMinutes,
        public bool $reminderEmailEnabled,
    ) {}

    public static function defaults(): self
    {
        return new self(
            version: 0,
            publicationEmailEnabled: true,
            reminderOffsetsMinutes: self::DEFAULT_REMINDER_OFFSETS_MINUTES,
            reminderEmailEnabled: true,
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
            'publication_email_enabled',
            'reminder_offsets_minutes',
            'reminder_email_enabled',
        ]);

        if (($policy['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || ! is_int($policy['version'] ?? null)
            || $policy['version'] < 0
            || ! is_bool($policy['publication_email_enabled'] ?? null)
            || ! is_bool($policy['reminder_email_enabled'] ?? null)
            || ! is_array($policy['reminder_offsets_minutes'] ?? null)) {
            throw new InvalidArgumentException('Stored Examination notification policy is invalid.');
        }

        $offsets = self::validatedOffsets($policy['reminder_offsets_minutes']);

        return new self(
            version: $policy['version'],
            publicationEmailEnabled: $policy['publication_email_enabled'],
            reminderOffsetsMinutes: $offsets,
            reminderEmailEnabled: $policy['reminder_email_enabled'],
        );
    }

    /**
     * @param  array{publication_email_enabled: bool, reminder_offsets_minutes: list<int>, reminder_email_enabled: bool}  $values
     */
    public function next(array $values): self
    {
        return new self(
            version: $this->version + 1,
            publicationEmailEnabled: $values['publication_email_enabled'],
            reminderOffsetsMinutes: self::validatedOffsets($values['reminder_offsets_minutes']),
            reminderEmailEnabled: $values['reminder_email_enabled'],
        );
    }

    /**
     * Resolve nullable, closed session overrides over the cycle default.
     * An empty reminder list deliberately disables optional reminders.
     *
     * @param  array<string, mixed>|null  $override
     */
    public function withSessionOverride(?array $override): self
    {
        if ($override === null) {
            return $this;
        }

        self::assertKeys($override, [
            'schema_version',
            'publication_email_enabled',
            'reminder_offsets_minutes',
            'reminder_email_enabled',
        ]);

        if (($override['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Exam-session notification policy schema is unsupported.');
        }

        $publicationEmailEnabled = $override['publication_email_enabled'] ?? $this->publicationEmailEnabled;
        $reminderEmailEnabled = $override['reminder_email_enabled'] ?? $this->reminderEmailEnabled;

        if (! is_bool($publicationEmailEnabled) || ! is_bool($reminderEmailEnabled)) {
            throw new InvalidArgumentException('Exam-session notification channel override is invalid.');
        }

        $offsets = array_key_exists('reminder_offsets_minutes', $override)
            ? self::validatedOffsets($override['reminder_offsets_minutes'])
            : $this->reminderOffsetsMinutes;

        return new self(
            version: $this->version,
            publicationEmailEnabled: $publicationEmailEnabled,
            reminderOffsetsMinutes: $offsets,
            reminderEmailEnabled: $reminderEmailEnabled,
        );
    }

    /**
     * @return array{schema_version: int, version: int, publication_email_enabled: bool, reminder_offsets_minutes: list<int>, reminder_email_enabled: bool}
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'version' => $this->version,
            'publication_email_enabled' => $this->publicationEmailEnabled,
            'reminder_offsets_minutes' => $this->reminderOffsetsMinutes,
            'reminder_email_enabled' => $this->reminderEmailEnabled,
        ];
    }

    /**
     * @return array{schema_version: int, cycle_policy_version: int, session_version: int, publication_email_enabled: bool, reminder_offsets_minutes: list<int>, reminder_email_enabled: bool}
     */
    public function snapshot(int $sessionVersion): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'cycle_policy_version' => $this->version,
            'session_version' => $sessionVersion,
            'publication_email_enabled' => $this->publicationEmailEnabled,
            'reminder_offsets_minutes' => $this->reminderOffsetsMinutes,
            'reminder_email_enabled' => $this->reminderEmailEnabled,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public static function fromSnapshot(?array $snapshot): ?self
    {
        if ($snapshot === null) {
            return null;
        }

        self::assertKeys($snapshot, [
            'schema_version',
            'cycle_policy_version',
            'session_version',
            'publication_email_enabled',
            'reminder_offsets_minutes',
            'reminder_email_enabled',
        ]);

        if (($snapshot['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || ! is_int($snapshot['cycle_policy_version'] ?? null)
            || ! is_int($snapshot['session_version'] ?? null)
            || ! is_bool($snapshot['publication_email_enabled'] ?? null)
            || ! is_bool($snapshot['reminder_email_enabled'] ?? null)
            || ! is_array($snapshot['reminder_offsets_minutes'] ?? null)) {
            throw new InvalidArgumentException('Exam-session notification snapshot is invalid.');
        }

        return new self(
            version: $snapshot['cycle_policy_version'],
            publicationEmailEnabled: $snapshot['publication_email_enabled'],
            reminderOffsetsMinutes: self::validatedOffsets($snapshot['reminder_offsets_minutes']),
            reminderEmailEnabled: $snapshot['reminder_email_enabled'],
        );
    }

    /**
     * @param  array<string, mixed>  $policy
     * @param  list<string>  $allowed
     */
    private static function assertKeys(array $policy, array $allowed): void
    {
        if (array_diff(array_keys($policy), $allowed) !== []) {
            throw new InvalidArgumentException('Examination notification policy contains unsupported fields.');
        }
    }

    /**
     * @return list<int>
     */
    private static function validatedOffsets(mixed $offsets): array
    {
        if (! is_array($offsets) || count($offsets) > count(self::ALLOWED_REMINDER_OFFSETS_MINUTES)) {
            throw new InvalidArgumentException('Exam reminder offsets are invalid.');
        }

        $normalized = [];

        foreach ($offsets as $offset) {
            if (! is_int($offset) || ! in_array($offset, self::ALLOWED_REMINDER_OFFSETS_MINUTES, true)) {
                throw new InvalidArgumentException('Exam reminder offsets are invalid.');
            }

            if (in_array($offset, $normalized, true)) {
                throw new InvalidArgumentException('Exam reminder offsets must be unique.');
            }

            $normalized[] = $offset;
        }

        rsort($normalized, SORT_NUMERIC);

        return $normalized;
    }
}

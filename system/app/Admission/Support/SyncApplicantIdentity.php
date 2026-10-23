<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ApplicantIdentity;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

final class SyncApplicantIdentity
{
    /**
     * @param  array<string, mixed>  $changes
     * @return array{
     *   changed: array<string, mixed>,
     *   before: array<string, mixed>,
     *   after: array<string, mixed>,
     *   synced_attempt_contact: array<string, string|null>
     * }
     */
    public function handle(AdmissionAttempt $attempt, array $changes, bool $allowBirthdate = true): array
    {
        $identity = ApplicantIdentity::query()
            ->lockForUpdate()
            ->findOrFail($attempt->applicant_identity_id);

        $before = $this->snapshot($identity);

        $map = [
            'identity_first_name' => 'first_name',
            'identity_middle_name' => 'middle_name',
            'identity_last_name' => 'last_name',
            'identity_suffix' => 'suffix',
            'identity_sex' => 'sex',
            'identity_email' => 'email',
            'identity_phone' => 'phone',
        ];

        if ($allowBirthdate) {
            $map['identity_birthdate'] = 'birthdate';
        }

        $requiredColumns = ['first_name', 'last_name', 'birthdate', 'sex'];
        $changed = [];

        foreach ($map as $input => $column) {
            if (! array_key_exists($input, $changes)) {
                continue;
            }

            $rawValue = $changes[$input];
            $value = is_string($rawValue) ? trim($rawValue) : $rawValue;

            if (in_array($column, $requiredColumns, true) && ($value === null || $value === '')) {
                continue;
            }

            if ($column === 'birthdate' && is_string($value) && $value !== '') {
                $value = substr($value, 0, 10);
            }

            $current = $column === 'birthdate'
                ? ($identity->birthdate instanceof DateTimeInterface
                    ? $identity->birthdate->format('Y-m-d')
                    : substr((string) $identity->birthdate, 0, 10))
                : (string) ($identity->{$column} ?? '');

            if ($current === (string) ($value ?? '')) {
                continue;
            }

            $normalizedValue = ($value === '' || $value === null) ? null : $value;
            $identity->{$column} = $normalizedValue;
            $changed[$column] = $normalizedValue;
        }

        $syncedAttemptContact = [];

        if (array_key_exists('email', $changed)) {
            $contactEmailInput = array_key_exists('contact_email', $changes)
                ? (is_string($changes['contact_email']) ? trim($changes['contact_email']) : $changes['contact_email'])
                : null;
            if (
                ! array_key_exists('contact_email', $changes)
                || (string) ($contactEmailInput ?? '') === (string) ($attempt->contact_email ?? '')
            ) {
                $syncedAttemptContact['contact_email'] = $identity->email;
            }
        } elseif (array_key_exists('contact_email', $changes)) {
            $contactEmail = is_string($changes['contact_email']) ? trim($changes['contact_email']) : $changes['contact_email'];
            $normalizedContactEmail = ($contactEmail === '' || $contactEmail === null) ? null : (string) $contactEmail;
            if (
                (string) ($normalizedContactEmail ?? '') !== (string) ($attempt->contact_email ?? '')
                && (string) ($identity->email ?? '') !== (string) ($normalizedContactEmail ?? '')
            ) {
                $identity->email = $normalizedContactEmail;
                $changed['email'] = $normalizedContactEmail;
            }
        }

        if (array_key_exists('phone', $changed)) {
            $contactPhoneInput = array_key_exists('contact_phone', $changes)
                ? (is_string($changes['contact_phone']) ? trim($changes['contact_phone']) : $changes['contact_phone'])
                : null;
            if (
                ! array_key_exists('contact_phone', $changes)
                || (string) ($contactPhoneInput ?? '') === (string) ($attempt->contact_phone ?? '')
            ) {
                $syncedAttemptContact['contact_phone'] = $identity->phone;
            }
        } elseif (array_key_exists('contact_phone', $changes)) {
            $contactPhone = is_string($changes['contact_phone']) ? trim($changes['contact_phone']) : $changes['contact_phone'];
            $normalizedContactPhone = ($contactPhone === '' || $contactPhone === null) ? null : (string) $contactPhone;
            if (
                (string) ($normalizedContactPhone ?? '') !== (string) ($attempt->contact_phone ?? '')
                && (string) ($identity->phone ?? '') !== (string) ($normalizedContactPhone ?? '')
            ) {
                $identity->phone = $normalizedContactPhone;
                $changed['phone'] = $normalizedContactPhone;
            }
        }

        if ($changed !== []) {
            $birthdateYmd = $identity->birthdate instanceof DateTimeInterface
                ? $identity->birthdate->format('Y-m-d')
                : substr((string) $identity->birthdate, 0, 10);

            $newKey = NormalizedIdentityKey::make(
                (string) $identity->first_name,
                (string) $identity->last_name,
                $birthdateYmd,
                (string) $identity->sex,
                $identity->middle_name,
            );

            if ($identity->practice_fixture_set_id !== null) {
                $newKey = hash('sha256', $identity->practice_fixture_set_id.'|'.$newKey);
            }

            if ($newKey !== (string) $identity->normalized_key) {
                $conflict = ApplicantIdentity::query()
                    ->where('institution_id', $identity->institution_id)
                    ->where('normalized_key', $newKey)
                    ->where('id', '!=', $identity->id)
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'identity_first_name' => 'Another applicant record already matches this legal name, birthdate, and sex.',
                        'version' => 'Identity amendment conflicts with another applicant record.',
                    ]);
                }

                $identity->normalized_key = $newKey;
                $changed['normalized_key'] = $newKey;
            }

            $identity->save();

            $account = ApplicantAccount::query()
                ->with('user')
                ->where('applicant_identity_id', $identity->id)
                ->first();

            $user = $account?->user;
            if ($user !== null) {
                $desiredName = trim($identity->first_name.' '.$identity->last_name);
                if ($desiredName !== '' && $user->name !== $desiredName) {
                    $user->name = $desiredName;
                }

                if ($identity->email !== null && $identity->email !== '' && $user->email !== $identity->email) {
                    $emailTaken = User::query()
                        ->where('email', $identity->email)
                        ->where('id', '!=', $user->id)
                        ->exists();

                    if (! $emailTaken) {
                        $user->email = $identity->email;
                    }
                }

                if ($user->isDirty()) {
                    $user->save();
                }
            }
        }

        $after = $changed !== [] ? $this->snapshot($identity->fresh()) : $before;

        return [
            'changed' => $changed,
            'before' => $before,
            'after' => $after,
            'synced_attempt_contact' => $syncedAttemptContact,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ApplicantIdentity $identity): array
    {
        $data = $identity->only([
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'birthdate',
            'sex',
            'email',
            'phone',
            'normalized_key',
        ]);

        if (($data['birthdate'] ?? null) instanceof DateTimeInterface) {
            $data['birthdate'] = $data['birthdate']->format('Y-m-d');
        }

        return $data;
    }
}

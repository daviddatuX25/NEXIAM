<?php

namespace App\Scheduling;

use App\Models\Admission\AdmissionAttempt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Shared FCFS reservation primitive (consultation change, Phase 0).
 *
 * Owns the race-sensitive core: lock ordering (session → attempt), cycle
 * match, idempotent reuse, one-active-assignment-per-attempt, and the
 * capacity-from-room guard — all inside one DB transaction.
 *
 * Domain consumers supply per-domain predicates/closures for eligibility,
 * availability, capacity, active-assignment lookups, and the creation
 * payload. Audit and after-commit notification stay domain-owned (via
 * `onCreated`, invoked inside the transaction so audit stays atomic with
 * the write).
 */
final class ReservationGuard
{
    /**
     * @param  callable(Model): int  $capacityOf
     * @param  callable(Model, AdmissionAttempt): ?Model  $activeForSession
     * @param  callable(AdmissionAttempt): ?Model  $activeAny
     * @param  callable(Model): int  $activeCount
     * @param  callable(Model, AdmissionAttempt): Model  $create
     * @param  callable(Model, Model, AdmissionAttempt): void  $onCreated
     * @param  callable(AdmissionAttempt): ?RuntimeException  $eligibilityError
     * @param  callable(Model): ?RuntimeException  $availabilityError
     */
    public function handle(
        Model $session,
        AdmissionAttempt $attempt,
        callable $capacityOf,
        callable $activeForSession,
        callable $activeAny,
        callable $activeCount,
        callable $create,
        callable $onCreated,
        callable $eligibilityError,
        callable $availabilityError,
        string $duplicateConflictMessage,
        string $sessionFullMessage,
    ): Model {
        return DB::transaction(function () use (
            $session,
            $attempt,
            $capacityOf,
            $activeForSession,
            $activeAny,
            $activeCount,
            $create,
            $onCreated,
            $eligibilityError,
            $availabilityError,
            $duplicateConflictMessage,
            $sessionFullMessage,
        ): Model {
            $lockedSession = $session->newQuery()->lockForUpdate()->findOrFail($session->getKey());
            $lockedAttempt = $attempt->newQuery()->lockForUpdate()->findOrFail($attempt->getKey());

            $error = $eligibilityError($lockedAttempt);
            if ($error instanceof RuntimeException) {
                throw $error;
            }

            $error = $availabilityError($lockedSession);
            if ($error instanceof RuntimeException) {
                throw $error;
            }

            if ((int) $lockedAttempt->getAttribute('academic_cycle_id') !== (int) $lockedSession->getAttribute('academic_cycle_id')) {
                throw new RuntimeException('Attempt is not in the same academic year as this session.');
            }

            $existing = $activeForSession($lockedSession, $lockedAttempt);
            if ($existing !== null) {
                return $existing;
            }

            $other = $activeAny($lockedAttempt);
            if ($other !== null) {
                throw new RuntimeException($duplicateConflictMessage);
            }

            if ($activeCount($lockedSession) >= $capacityOf($lockedSession)) {
                throw new RuntimeException($sessionFullMessage);
            }

            $assignment = $create($lockedSession, $lockedAttempt);

            $onCreated($assignment, $lockedSession, $lockedAttempt);

            return $assignment;
        });
    }
}

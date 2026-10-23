<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cycle- and session-scoped resolution seam for Applicant No. (`AY26-00001`)
 * and Examinee No. shorthand (`00001`, `1`, `2600001`, `APP-2026-00001`).
 */
final class ApplicantReferenceResolver
{
    /**
     * Constrain an `AdmissionAttempt` query to exact candidate codes for `$rawInput`.
     *
     * @param  Builder<AdmissionAttempt>  $query
     * @return Builder<AdmissionAttempt>
     */
    public static function applyMatch(
        Builder $query,
        string $rawInput,
        ?AcademicCycle $cycle = null,
        string $column = 'reference_code',
    ): Builder {
        $candidates = ApplicantReference::candidateCodes($rawInput, $cycle);
        if ($candidates === []) {
            return $query->whereRaw('1 = 0');
        }

        $qualifiedColumn = str_contains($column, '.')
            ? $column
            : $query->getModel()->qualifyColumn($column);

        return $query->where(function (Builder $sub) use ($qualifiedColumn, $candidates): void {
            foreach ($candidates as $index => $candidate) {
                if ($index === 0) {
                    $sub->whereRaw("UPPER({$qualifiedColumn}) = ?", [$candidate]);
                } else {
                    $sub->orWhereRaw("UPPER({$qualifiedColumn}) = ?", [$candidate]);
                }
            }
        });
    }

    /**
     * Resolve a single `AdmissionAttempt` within `$cycle`.
     * Fails closed (`null`) if zero or more than one attempt matches.
     */
    public static function resolveInCycle(
        AcademicCycle $cycle,
        string $rawInput,
        ?int $institutionId = null,
        bool $forWorkspace = true,
    ): ?AdmissionAttempt {
        $query = $forWorkspace
            ? AdmissionAttempt::query()->forWorkspace()
            : AdmissionAttempt::query();

        $query->where('academic_cycle_id', $cycle->id);

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        } elseif ($cycle->institution_id !== null) {
            $query->where('institution_id', (int) $cycle->institution_id);
        }

        self::applyMatch($query, $rawInput, $cycle);

        $matches = $query->limit(2)->get();
        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    /**
     * Resolve a single `AdmissionAttempt` within an `ExamSession`'s cycle and institution.
     * Fails closed (`null`) if zero or more than one attempt matches.
     */
    public static function resolveInSession(
        ExamSession $session,
        string $rawInput,
        bool $forWorkspace = true,
    ): ?AdmissionAttempt {
        $session->loadMissing('academicCycle');
        $cycle = $session->academicCycle;

        $query = $forWorkspace
            ? AdmissionAttempt::query()->forWorkspace()
            : AdmissionAttempt::query();

        $query->where('academic_cycle_id', $session->academic_cycle_id);
        if ($session->institution_id !== null) {
            $query->where('institution_id', (int) $session->institution_id);
        } elseif ($cycle?->institution_id !== null) {
            $query->where('institution_id', (int) $cycle->institution_id);
        }

        self::applyMatch($query, $rawInput, $cycle);

        $matches = $query->limit(2)->get();
        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    /**
     * Normalize a raw reference or numeric shorthand within `$cycle`:
     * - If an existing attempt in `$cycle` matches `$rawInput`, returns its canonical `reference_code`.
     * - Otherwise, if `$rawInput` is a pure numeric sequence (`1`, `00001`, `2600001`), formats it as `AY<YY>-<05d>`.
     * - Otherwise returns trimmed `$rawInput`.
     */
    public static function normalizeForCycle(
        AcademicCycle $cycle,
        string $rawInput,
        ?int $institutionId = null,
    ): string {
        $trimmed = trim($rawInput);
        if ($trimmed === '') {
            return '';
        }

        $existing = self::resolveInCycle($cycle, $trimmed, $institutionId, false);
        if ($existing !== null) {
            return (string) $existing->reference_code;
        }

        if (ctype_digit($trimmed)) {
            $sequence = ApplicantReference::extractSequence($trimmed, $cycle);
            if ($sequence !== null) {
                return ApplicantReference::format($cycle, $sequence);
            }
        }

        return $trimmed;
    }
}

<?php

namespace App\ProgramHead\Queries;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\QuotaReservation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use Illuminate\Support\Carbon;

/**
 * D-080/D-ROLE-05: assigned-program candidate lists. Identified rows require
 * Registrar acceptance; submitted/pre-accept rows stay aggregate-only.
 * Primary lifecycle precedence: Enrolled → Enrolling → Upcoming → Potential;
 * follow-up is an orthogonal work flag.
 */
final class ProgramHeadCandidateListsQuery
{
    /**
     * @return array<string, mixed>
     */
    public function for(Program $program, ?string $cycleCode): array
    {
        $cycle = $cycleCode === null ? null : AcademicCycle::query()
            ->where('code', $cycleCode)
            ->where('is_active', true)
            ->first();

        $cycleId = $cycle?->id;
        $programId = $program->id;
        $programCode = $program->code;

        $accepted = AdmissionAttempt::query()
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->pluck('id')
            ->all();

        // Potential: preference 1-3 OR active pool option, not committed elsewhere.
        $potentialIds = AdmissionAttempt::query()
            ->whereIn('id', $accepted)
            ->where(function ($q) use ($programCode, $programId) {
                $q->whereIn('program_preference_1_code', [$programCode])
                    ->orWhereIn('program_preference_2_code', [$programCode])
                    ->orWhereIn('program_preference_3_code', [$programCode])
                    ->orWhereHas('programPools.options', fn ($o) => $o->where('program_id', $programId));
            })
            ->whereDoesntHave('programSelections', function ($q) use ($programId) {
                $q->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                    ->where('program_id', '!=', $programId);
            })
            ->pluck('id')
            ->all();

        $potential = $this->rows($potentialIds, fn (AdmissionAttempt $a) => [
            'basis' => $this->basis($a, $programCode, $programId),
            'stage' => AttemptProgramFacts::lifecycleTag($a),
        ]);

        // Upcoming: active selection for this program, single option, no open follow-up.
        $upcomingIds = ProgramSelection::query()
            ->where('program_id', $programId)
            ->where('status', ProgramSelection::STATUS_ACTIVE)
            ->whereIn('admission_attempt_id', $accepted)
            ->whereDoesntHave('attempt.programFollowUps', fn ($q) => $q->whereIn('status', ProgramFollowUp::OPEN_STATUSES))
            ->pluck('admission_attempt_id')
            ->all();

        $upcoming = $this->rows($upcomingIds, fn (AdmissionAttempt $a) => ['stage' => 'upcoming']);

        // Follow-up queue: open targeted follow-ups (may overlap Enrolling).
        $followUpRows = [];
        $followUps = ProgramFollowUp::query()
            ->where('program_id', $programId)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->with('attempt.identity')
            ->orderBy('opened_at')
            ->get();

        foreach ($followUps as $f) {
            $followUpRows[] = [
                'id' => $f->id,
                'attempt_id' => $f->admission_attempt_id,
                'reference_code' => $f->attempt?->reference_code,
                'name' => $f->attempt?->identity?->last_name.', '.$f->attempt?->identity?->first_name,
                'status' => $f->status,
                'reason' => $f->reason,
                'opened_at' => self::isoOrNull($f->opened_at),
                'overlaps_enrolling' => $f->attempt !== null && AttemptProgramFacts::isEnrolling($f->attempt),
            ];
        }

        // Enrolling / Enrolled by stamp program.
        $enrolling = $this->stampRows(EnrollmentStamp::STAMP_ENROLLING, $programId, $cycleId);
        $enrolled = $this->stampRows(EnrollmentStamp::STAMP_ENROLLED, $programId, $cycleId);

        // Quota panel.
        $quota = ProgramQuota::query()
            ->when($cycleId !== null, fn ($q) => $q->where('academic_cycle_id', $cycleId))
            ->where('program_id', $programId)
            ->first();

        $committed = $cycleId === null
            ? 0
            : QuotaReservation::committedForCycle($cycleId, $programId);

        return [
            'program' => ['code' => $programCode, 'name' => $program->name],
            'cycle' => $cycle?->code,
            'potential' => $potential,
            'upcoming' => $upcoming,
            'follow_ups' => $followUpRows,
            'enrolling' => $enrolling,
            'enrolled' => $enrolled,
            'quota' => [
                'configured' => $quota !== null,
                'capacity' => $quota?->slots,
                'remaining' => $quota === null ? 0 : max($quota->slots - $committed, 0),
                'over_capacity' => $quota === null ? 0 : max($committed - $quota->slots, 0),
            ],
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @param  callable(AdmissionAttempt): array<string, mixed>  $tag
     * @return list<array<string, mixed>>
     */
    private function rows(array $ids, callable $tag): array
    {
        if ($ids === []) {
            return [];
        }

        return AdmissionAttempt::query()
            ->whereIn('id', $ids)
            ->with('identity')
            ->orderBy('id')
            ->get()
            ->map(fn (AdmissionAttempt $a) => [
                'attempt_id' => $a->id,
                'reference_code' => $a->reference_code,
                'name' => $a->identity?->last_name.', '.$a->identity?->first_name,
                ...$tag($a),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stampRows(string $stampType, int $programId, ?int $cycleId): array
    {
        $stamps = EnrollmentStamp::query()
            ->where('stamp_type', $stampType)
            ->where(function ($q) use ($programId) {
                $q->where('provisional_program_id', $programId)->orWhere('final_program_id', $programId);
            })
            ->with('attempt.identity')
            ->when($cycleId !== null, fn ($q) => $q->whereHas('attempt', fn ($a) => $a->where('academic_cycle_id', $cycleId)))
            ->orderByDesc('created_at')
            ->get();

        $rows = [];
        foreach ($stamps as $s) {
            $rows[] = [
                'attempt_id' => $s->admission_attempt_id,
                'reference_code' => $s->attempt?->reference_code,
                'name' => $s->attempt?->identity?->last_name.', '.$s->attempt?->identity?->first_name,
                'stamped_at' => self::isoOrNull($s->created_at),
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function basis(AdmissionAttempt $attempt, string $programCode, int $programId): array
    {
        $basis = [];
        foreach (['program_preference_1_code', 'program_preference_2_code', 'program_preference_3_code'] as $i => $column) {
            if ($attempt->{$column} === $programCode) {
                $basis[] = 'preference #'.($i + 1);
            }
        }

        $inPool = ProgramPool::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereHas('options', fn ($o) => $o->where('program_id', $programId))
            ->exists();

        if ($inPool) {
            $basis[] = 'guidance pool';
        }

        return $basis;
    }

    private static function isoOrNull(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }
}

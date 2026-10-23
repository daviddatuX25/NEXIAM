<?php

namespace App\Consultation\Queries;

use App\Admission\Support\ApplicantReference;
use App\Admission\Support\ApplicantReferenceResolver;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ConsultationApplicantLookupQuery
{
    /**
     * @return Collection<int, AdmissionAttempt>
     */
    public function matchingAcceptedAttempts(string $query, int $limit, ?int $institutionId): Collection
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return AdmissionAttempt::query()
            ->forWorkspace()
            ->where('institution_id', $institutionId)
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->where(function ($attempts) use ($escaped, $likeOperator, $query): void {
                $cycle = AcademicCycle::active();
                $attempts->where('reference_code', $likeOperator, "%{$escaped}%");
                if (ApplicantReference::extractSequence($query, $cycle) !== null) {
                    $attempts->orWhere(fn ($q) => ApplicantReferenceResolver::applyMatch($q, $query, $cycle));
                }
                $attempts->orWhereHas('identity', fn ($identity) => $identity
                    ->where('first_name', $likeOperator, "%{$escaped}%")
                    ->orWhere('last_name', $likeOperator, "%{$escaped}%"));
            })
            ->with('identity')
            ->limit($limit)
            ->get();
    }
}

<?php

namespace App\Admission\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Registrar applications queue — filterable, paginated list authority.
 */
final class ListAdmissionAttemptsQuery
{
    /**
     * @return array{
     *   attempts: LengthAwarePaginator,
     *   filters: array{search: ?string, status: ?string, cycle: ?string},
     *   statuses: list<array{value: string, label: string}>,
     *   cycles: list<array{value: string, label: string}>,
     *   status_counts: array<string, int>
     * }
     */
    public function handle(Request $request): array
    {
        $search = $request->string('search')->trim()->toString();
        $search = $search !== '' ? $search : null;
        $status = $request->string('status')->trim()->toString();
        $status = $status !== '' ? $status : null;
        $cycle = $request->string('cycle')->trim()->toString();
        $cycle = $cycle !== '' ? $cycle : null;

        $base = AdmissionAttempt::query()->with([
            'identity:id,first_name,middle_name,last_name,suffix,email,phone',
            'academicCycle:id,code,name',
        ]);

        $statusCounts = [
            'all' => (clone $base)->count(),
            AdmissionAttempt::STATUS_PENDING => (clone $base)->where('status', AdmissionAttempt::STATUS_PENDING)->count(),
            AdmissionAttempt::STATUS_NEEDS_CORRECTION => (clone $base)->where('status', AdmissionAttempt::STATUS_NEEDS_CORRECTION)->count(),
            AdmissionAttempt::STATUS_ACCEPTED => (clone $base)->where('status', AdmissionAttempt::STATUS_ACCEPTED)->count(),
            AdmissionAttempt::STATUS_DISMISSED => (clone $base)->where('status', AdmissionAttempt::STATUS_DISMISSED)->count(),
            AdmissionAttempt::STATUS_ARCHIVED => (clone $base)->where('status', AdmissionAttempt::STATUS_ARCHIVED)->count(),
        ];

        $query = clone $base;

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($cycle !== null) {
            $query->whereHas('academicCycle', fn ($q) => $q->where('code', $cycle));
        }

        if ($search !== null) {
            $like = '%'.$search.'%';
            $driver = $query->getConnection()->getDriverName();
            $operator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($like, $operator): void {
                $q->where('reference_code', $operator, $like)
                    ->orWhere('contact_email', $operator, $like)
                    ->orWhere('program_preference_1_code', $operator, $like)
                    ->orWhere('preferred_program_code', $operator, $like)
                    ->orWhereHas('identity', function ($identity) use ($like, $operator): void {
                        $identity->where('first_name', $operator, $like)
                            ->orWhere('last_name', $operator, $like)
                            ->orWhere('email', $operator, $like)
                            ->orWhere('phone', $operator, $like);
                    });
            });
        }

        $attempts = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (AdmissionAttempt $attempt): array => $this->serialize($attempt));

        $cycles = AcademicCycle::query()
            ->orderByDesc('opens_on')
            ->get(['code', 'name'])
            ->map(fn (AcademicCycle $c): array => [
                'value' => $c->code,
                'label' => $c->name,
            ])
            ->values()
            ->all();

        return [
            'attempts' => $attempts,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'cycle' => $cycle,
            ],
            'statuses' => [
                ['value' => AdmissionAttempt::STATUS_PENDING, 'label' => 'Pending'],
                ['value' => AdmissionAttempt::STATUS_NEEDS_CORRECTION, 'label' => 'Needs correction'],
                ['value' => AdmissionAttempt::STATUS_ACCEPTED, 'label' => 'Accepted'],
                ['value' => AdmissionAttempt::STATUS_DISMISSED, 'label' => 'Dismissed'],
                ['value' => AdmissionAttempt::STATUS_ARCHIVED, 'label' => 'Archived'],
            ],
            'cycles' => $cycles,
            'status_counts' => $statusCounts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AdmissionAttempt $attempt): array
    {
        $identity = $attempt->identity;
        $name = trim(implode(' ', array_filter([
            $identity?->first_name,
            $identity?->middle_name,
            $identity?->last_name,
            $identity?->suffix,
        ])));

        return [
            'id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'status' => $attempt->status,
            'version' => $attempt->version,
            'applicant_name' => $name !== '' ? $name : 'Unknown',
            'contact_email' => $attempt->contact_email ?? $identity?->email,
            'contact_phone' => $attempt->contact_phone ?? $identity?->phone,
            'preferred_program_code' => $attempt->program_preference_1_code
                ?? $attempt->preferred_program_code,
            'program_preferences' => array_values(array_filter([
                $attempt->program_preference_1_code,
                $attempt->program_preference_2_code,
                $attempt->program_preference_3_code,
            ])),
            'applicant_type' => $attempt->applicant_type,
            'cycle_code' => $attempt->academicCycle?->code,
            'cycle_name' => $attempt->academicCycle?->name,
            'submitted_at' => $attempt->created_at?->toIso8601String(),
            'correction_notes' => $attempt->correction_notes,
            'accepted_at' => $attempt->accepted_at?->toIso8601String(),
            'dismissed_at' => $attempt->dismissed_at?->toIso8601String(),
        ];
    }
}

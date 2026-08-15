<?php

namespace App\Examination\Queries;

use App\Examination\Support\ExamSessionPresenter;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Scheduling list: searchable, status- and cycle-filtered session index.
 */
class ExamSessionListQuery
{
    public function __construct(private readonly ExamSessionPresenter $presenter)
    {
    }

    /**
     * @return array{sessions: Collection<int, array<string, mixed>>, filters: array<string, mixed>}
     */
    public function forScheduling(Request $request): array
    {
        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'academic_cycle_id' => $request->integer('academic_cycle_id') ?: null,
        ];

        $query = ExamSession::query()
            ->with([
                'room:id,code,name,capacity',
                'academicCycle:id,school_year,semester,name,is_active',
                'proctorDuties' => fn ($q) => $q->where('status', ProctorDuty::STATUS_ACTIVE)->with('user:id,name'),
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->active(),
            ])
            ->latest('starts_at');

        if ($filters['search']) {
            $search = $filters['search'];
            $query->where(function ($inner) use ($search): void {
                $inner->where('code', 'like', "%{$search}%")
                    ->orWhereHas('room', fn ($room) => $room->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        if ($filters['academic_cycle_id']) {
            $query->where('academic_cycle_id', $filters['academic_cycle_id']);
        } else {
            $activeCycle = AcademicCycle::active();
            if ($activeCycle !== null) {
                $query->where('academic_cycle_id', $activeCycle->id);
                $filters['academic_cycle_id'] = $activeCycle->id;
            }
        }

        return [
            'sessions' => $query->limit(100)->get()->map(
                fn (ExamSession $session) => $this->presenter->listPayload($session)
            ),
            'filters' => $filters,
        ];
    }
}

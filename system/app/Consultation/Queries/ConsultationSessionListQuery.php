<?php

namespace App\Consultation\Queries;

use App\Consultation\Support\ConsultationSessionPresenter;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Scheduling list: searchable, status- and cycle-filtered session index.
 */
class ConsultationSessionListQuery
{
    public function __construct(private readonly ConsultationSessionPresenter $presenter) {}

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

        $query = ConsultationSession::query()
            ->with([
                'room:id,code,name,capacity',
                'academicCycle:id,school_year,semester,name,is_active',
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->active(),
            ])
            ->latest('scheduled_at');

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
                fn (ConsultationSession $session) => $this->presenter->listPayload($session)
            ),
            'filters' => $filters,
        ];
    }
}

<?php

namespace App\Http\Controllers\Proctor;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProctorSessionController extends Controller
{
    /**
     * Proctor My Sessions page — shows sessions assigned to this proctor,
     * grouped by date (Today, Upcoming, Past) per D-14 and D-15.
     * Authorization via ExamSessionPolicy.viewRoster per D-16.
     */
    public function mySessions(Request $request): Response
    {
        $user = $request->user();

        // Only sessions where this user is assigned as proctor
        $sessions = ExamSession::query()
            ->with(['room:id,name,building,capacity', 'proctors:id,name'])
            ->whereHas('proctors', fn ($q) => $q->where('users.id', $user->id))
            ->withCount('applicants')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'date' => $s->date?->format('Y-m-d'),
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'room_name' => $s->room?->name,
                'building' => $s->room?->building,
                'status' => $s->status,
                'applicants_count' => $s->applicants_count,
                'is_within_start_window' => $s->isWithinStartWindow(),
                'is_past_end' => $s->isPastEndTime(),
                'is_past_date' => $s->isPastDate(),
                'can_override_schedule' => true,
            ]);

        $today = $sessions->filter(fn ($s) => Carbon::parse($s['date'])->isToday())->values();
        $upcoming = $sessions->filter(fn ($s) => Carbon::parse($s['date'])->isFuture() && ! Carbon::parse($s['date'])->isToday())->values();
        $past = $sessions->filter(fn ($s) => Carbon::parse($s['date'])->isPast() && ! Carbon::parse($s['date'])->isToday())->values();

        return Inertia::render('Proctor/MySessions', [
            'today' => $today,
            'upcoming' => $upcoming,
            'past' => $past,
        ]);
    }
}

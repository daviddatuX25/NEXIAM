<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\ExamSession;
use App\Models\Room;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Room::query()->orderBy('building')->orderBy('name');

        if ($request->filled('search')) {
            $term = '%'.$request->get('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('building', 'like', $term);
            });
        }

        $rooms = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Rooms/Index', [
            'rooms' => $rooms,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Rooms/Create');
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $room = Room::create([
            'name' => $validated['name'],
            'building' => $validated['building'],
            'floor' => $validated['floor'] ?? null,
            'capacity' => $validated['capacity'],
            'is_active' => true,
        ]);

        app(AuditService::class)->log('room.created', Room::class, $room->id, [], [
            'name' => $validated['name'],
            'building' => $validated['building'],
            'capacity' => $validated['capacity'],
        ]);

        return redirect()->route('admin.rooms.index')->with('success', 'Room created.');
    }

    public function edit(Room $room): Response
    {
        return Inertia::render('Admin/Rooms/Edit', [
            'room' => $room,
        ]);
    }

    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        $room->update($request->validated());

        app(AuditService::class)->log('room.updated', Room::class, $room->id, [], $request->validated());

        return redirect()->route('admin.rooms.index')->with('success', 'Room updated.');
    }

    /**
     * Deactivate room (soft). Block if room has future exam sessions.
     */
    public function destroy(Room $room): RedirectResponse
    {
        $hasFutureSessions = ExamSession::query()
            ->where('room_id', $room->id)
            ->whereDate('date', '>=', now()->toDateString())
            ->exists();

        if ($hasFutureSessions) {
            return redirect()->route('admin.rooms.index')
                ->with('error', 'Cannot delete: this room has exam sessions scheduled in the future.');
        }

        app(AuditService::class)->log('room.deleted', Room::class, $room->id, ['name' => $room->name]);

        $room->delete(); // soft delete

        return redirect()->route('admin.rooms.index')->with('success', 'Room deleted.');
    }

    public function activate(Room $room): RedirectResponse
    {
        $room->update(['is_active' => true]);

        app(AuditService::class)->log('room.activated', Room::class, $room->id);

        return redirect()->route('admin.rooms.index')->with('success', 'Room activated.');
    }

    public function deactivate(Room $room): JsonResponse|RedirectResponse
    {
        $hasFutureSessions = ExamSession::query()
            ->where('room_id', $room->id)
            ->whereDate('date', '>=', now()->toDateString())
            ->exists();

        if ($hasFutureSessions) {
            if (request()->header('X-Inertia')) {
                return response()->json(['errors' => ['room' => ['Cannot deactivate: this room has exam sessions scheduled in the future.']]], 422);
            }

            return redirect()->route('admin.rooms.index')
                ->with('error', 'Cannot deactivate: this room has exam sessions scheduled in the future.');
        }

        $room->update(['is_active' => false]);

        app(AuditService::class)->log('room.deactivated', Room::class, $room->id);

        return redirect()->route('admin.rooms.index')->with('success', 'Room deactivated.');
    }

    public function restore(Room $room): RedirectResponse
    {
        $room->restore();

        return redirect()->route('admin.rooms.index')->with('success', 'Room restored.');
    }
}

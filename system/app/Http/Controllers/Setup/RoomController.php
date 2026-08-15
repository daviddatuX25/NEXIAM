<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StoreRoomRequest;
use App\Http\Requests\Setup\UpdateRoomRequest;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exam room catalog CRUD (institutional setup catalog, shared `setup.manage`).
 */
class RoomController extends Controller
{
    use HasSetupCapabilities;

    public function rooms(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireSetup($authorizer, $request);

        return Inertia::render('Setup/Rooms', [
            'page_state' => 'ready',
            'rooms' => Room::query()->orderBy('code')->get([
                'id', 'code', 'name', 'capacity', 'is_active', 'institution_id',
            ]),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeRoom(StoreRoomRequest $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);

        $room = Room::query()->create([
            'institution_id' => Institution::current()->id,
            'code' => strtoupper($request->validated('code')),
            'name' => $request->validated('name'),
            'capacity' => $request->validated('capacity'),
            'is_active' => (bool) ($request->validated('is_active') ?? true),
        ]);

        $audit->handle('setup.room_upserted', [
            'room_id' => $room->id,
            'code' => $room->code,
            'capacity' => $room->capacity,
            'is_active' => $room->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Room saved.');
    }

    public function updateRoom(
        UpdateRoomRequest $request,
        Room $room,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        $room->update($request->validated());

        $audit->handle('setup.room_upserted', [
            'room_id' => $room->id,
            'code' => $room->code,
            'capacity' => $room->capacity,
            'is_active' => $room->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Room updated.');
    }
}

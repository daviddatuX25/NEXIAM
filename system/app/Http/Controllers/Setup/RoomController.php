<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\CreateRoom;
use App\Foundation\Actions\DeactivateRoom;
use App\Foundation\Actions\DeleteRoomPhoto;
use App\Foundation\Actions\SetRoomDefaultSeatingArrangement;
use App\Foundation\Actions\StoreRoomPhoto;
use App\Foundation\Actions\UpdateRoom;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\SetRoomDefaultSeatingArrangementRequest;
use App\Http\Requests\Setup\StoreRoomRequest;
use App\Http\Requests\Setup\UpdateExamSeatingAvailabilityRequest;
use App\Http\Requests\Setup\UpdateRoomRequest;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\Foundation\SpaceKind;
use App\Setup\Actions\UpdateInstitutionExamFeaturePolicy;
use App\Support\Search\ModularSortScope;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    use HasSetupCapabilities;

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireRoomSetup($authorizer, $request);
        $search = trim((string) $request->query('search', ''));
        $kind = trim((string) $request->query('kind', ''));
        $status = trim((string) $request->query('status', ''));
        $institution = Institution::current();
        $institutionId = (int) $institution->id;
        $rooms = Room::query()
            ->where('institution_id', $institutionId)
            ->when($institution->usesSeatingArrangements(), fn ($query) => $query->withCount([
                'seatingArrangements',
                'seatingArrangements as active_seating_arrangements_count' => fn ($query) => $query->active(),
            ]))
            ->when($kind !== '', fn ($query) => $query->where('space_kind', $kind));

        if ($status === 'active') {
            $rooms->where('is_active', true);
        } elseif ($status === 'inactive') {
            $rooms->where('is_active', false);
        }

        if ($search !== '') {
            TokenizedInnerMatchScope::apply($rooms, $search, [
                fn ($sub, $pattern) => TokenizedInnerMatchScope::orWhereColumnLike($sub, 'code', $pattern),
                fn ($sub, $pattern) => TokenizedInnerMatchScope::orWhereColumnLike($sub, 'name', $pattern),
            ]);
        }

        $allowedSorts = [
            'code' => 'code',
            'name' => 'name',
            'capacity' => 'capacity',
        ];

        $sortResult = ModularSortScope::apply(
            $rooms,
            $request->query('sort'),
            $request->query('dir'),
            $allowedSorts,
            defaultKey: 'code',
            defaultDir: 'asc',
            tieBreakColumn: 'id',
        );

        $paginatedRooms = $rooms->paginate(15)->withQueryString();
        $kindCounts = SpaceKind::countsForInstitution((int) $institutionId);

        return Inertia::render('Setup/Rooms/Index', [
            'page_state' => $paginatedRooms->isEmpty() ? 'empty' : 'ready',
            'rooms' => $paginatedRooms,
            'filters' => [
                'search' => $search,
                'kind' => $kind,
                'status' => $status,
                'sort' => $sortResult['sort'],
                'dir' => $sortResult['dir'],
            ],
            'kind_counts' => $kindCounts,
            'space_kinds' => SpaceKind::catalog(),
            'enable_seating_arrangements' => $institution->usesSeatingArrangements(),
            'exam_feature_policy_version' => $institution->examFeaturePolicyVersion(),
            'capabilities' => $this->capabilities($authorizer, $request),
            'can_manage_seating_policy' => $authorizer->allows($request->user(), CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                'institution_id' => $institutionId,
            ]),
        ]);
    }

    public function create(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireRoomSetup($authorizer, $request);

        return Inertia::render('Setup/Rooms/Create', [
            'space_kinds' => SpaceKind::catalog(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function store(
        StoreRoomRequest $request,
        CapabilityAuthorizer $authorizer,
        CreateRoom $createRoom,
    ): RedirectResponse {
        $this->requireRoomSetup($authorizer, $request);
        $room = $createRoom->handle($request->validated(), $request->user());

        return redirect()->route('setup.rooms.show', $room)->with('status', 'Room created.');
    }

    public function show(Request $request, Room $room, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireRoomSetup($authorizer, $request);
        $this->requireCurrentInstitution($room);
        $institution = Institution::current();
        $usesSeatingArrangements = $institution->usesSeatingArrangements();

        $applicableArrangements = $usesSeatingArrangements && $room->allowsSeatingArrangement()
            ? SeatingArrangement::query()
                ->active()
                ->whereRaw('rows * columns <= ?', [$room->capacity])
                ->where(function ($query) use ($room): void {
                    $query->where('availability_mode', SeatingArrangement::AVAILABILITY_ALL_ROOMS)
                        ->orWhereHas('rooms', fn ($rooms) => $rooms->whereKey($room->id));
                })
                ->orderBy('normalized_name')
                ->get()
            : collect([]);

        return Inertia::render('Setup/Rooms/Show', [
            'room' => $room->load($usesSeatingArrangements ? 'defaultSeatingArrangement' : []),
            'enable_seating_arrangements' => $usesSeatingArrangements,
            'space_kinds' => SpaceKind::catalog(),
            'can_manage_seating_arrangements' => $authorizer->allows(
                $request->user(),
                CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
                ['institution_id' => (int) $institution->id],
            ),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function edit(Request $request, Room $room, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireRoomSetup($authorizer, $request);

        return Inertia::render('Setup/Rooms/Edit', [
            'room' => $room,
            'space_kinds' => SpaceKind::catalog(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function update(
        UpdateRoomRequest $request,
        Room $room,
        CapabilityAuthorizer $authorizer,
        UpdateRoom $updateRoom,
    ): RedirectResponse {
        $this->requireRoomSetup($authorizer, $request);
        $this->requireCurrentInstitution($room);
        $room = $updateRoom->handle($room, $request->validated(), $request->user());

        return redirect()->route('setup.rooms.show', $room)->with('status', 'Room updated.');
    }

    public function uploadPhoto(
        Request $request,
        Room $room,
        CapabilityAuthorizer $authorizer,
        StoreRoomPhoto $action,
    ): RedirectResponse {
        $this->requireRoomSetup($authorizer, $request);
        $this->requireCurrentInstitution($room);
        $request->validate(['photo' => ['required', 'file']]);

        $action->handle($room, $request->file('photo'), $request->user());

        return back()->with('success', 'Room photo updated.');
    }

    public function deletePhoto(
        Request $request,
        Room $room,
        CapabilityAuthorizer $authorizer,
        DeleteRoomPhoto $action,
    ): RedirectResponse {
        $this->requireRoomSetup($authorizer, $request);
        $this->requireCurrentInstitution($room);
        $action->handle($room, $request->user());

        return back()->with('success', 'Room photo removed.');
    }

    public function activate(
        Request $request,
        Room $room,
        CapabilityAuthorizer $authorizer,
        UpdateRoom $updateRoom,
    ): RedirectResponse {
        $this->requireRoomSetup($authorizer, $request);
        $this->requireCurrentInstitution($room);
        $updateRoom->handle($room, [
            'name' => $room->name,
            'capacity' => $room->capacity,
            'is_active' => true,
        ], $request->user());

        return back()->with('status', 'Room activated.');
    }

    public function deactivate(
        Request $request,
        Room $room,
        CapabilityAuthorizer $authorizer,
        DeactivateRoom $deactivateRoom,
    ): RedirectResponse {
        $this->requireRoomSetup($authorizer, $request);
        $this->requireCurrentInstitution($room);
        $deactivateRoom->handle($room, $request->user());

        return back()->with('status', 'Room deactivated.');
    }

    public function setDefaultSeatingArrangement(
        SetRoomDefaultSeatingArrangementRequest $request,
        Room $room,
        CapabilityAuthorizer $authorizer,
        SetRoomDefaultSeatingArrangement $setDefault,
    ): RedirectResponse {
        $this->requireRoomSetup($authorizer, $request);
        $this->requireCurrentInstitution($room);
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, [
            'institution_id' => (int) Institution::current()->id,
        ]), 403);
        $arrangementId = $request->validated('seating_arrangement_id');
        $arrangement = $arrangementId === null
            ? null
            : SeatingArrangement::query()->findOrFail((int) $arrangementId);
        $setDefault->handle($room, $arrangement, $request->user());

        return back()->with('status', $arrangement === null
            ? 'Default seating arrangement cleared.'
            : 'Default seating arrangement updated.');
    }

    public function updateSeatingPolicy(
        UpdateExamSeatingAvailabilityRequest $request,
        UpdateInstitutionExamFeaturePolicy $policy,
    ): RedirectResponse {
        $validated = $request->validated();
        $institution = $policy->updateSeatingAvailability(
            $request->user(),
            (bool) $validated['enable_seating_arrangements'],
            (int) $validated['expected_version'],
        );

        return back()->with('status', $institution->usesSeatingArrangements()
            ? 'Assigned seating is available for new Exam Sessions.'
            : 'New Exam Sessions use simple room capacity.');
    }

    private function requireCurrentInstitution(Room $room): void
    {
        abort_unless($room->institution_id === Institution::current()->id, 404);
    }
}

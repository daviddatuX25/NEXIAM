<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\SaveSeatingArrangement;
use App\Foundation\SeatingArrangementPreview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\SaveSeatingArrangementRequest;
use App\Http\Requests\Setup\ToggleSeatingArrangementRequest;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Support\Search\ModularSortScope;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class SeatingArrangementController extends Controller
{
    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireManagement($authorizer, $request);
        $search = trim($request->string('search')->toString());
        $status = $request->query('status');

        $query = SeatingArrangement::query()
            ->with(['rooms:id,code,name,capacity'])
            ->withCount(['rooms', 'defaultForRooms']);

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== '') {
            TokenizedInnerMatchScope::apply($query, $search, [
                fn ($sub, $pattern) => TokenizedInnerMatchScope::orWhereColumnLike($sub, 'name', $pattern),
            ]);
        }

        $allowedSorts = [
            'name' => 'name',
            'capacity' => fn ($q, $dir) => $q->orderByRaw('(rows * columns) '.$dir),
            'is_active' => 'is_active',
        ];

        $sortResult = ModularSortScope::apply(
            $query,
            $request->query('sort'),
            $request->query('dir'),
            $allowedSorts,
            defaultKey: 'name',
            defaultDir: 'asc',
            tieBreakColumn: 'id',
        );

        $arrangements = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SeatingArrangement $arrangement): array => $this->arrangementPayload($arrangement));

        return Inertia::render('Setup/SeatingArrangements/Index', [
            'page_state' => $arrangements->isEmpty() ? 'empty' : 'ready',
            'arrangements' => $arrangements,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sortResult['sort'],
                'dir' => $sortResult['dir'],
            ],
        ]);
    }

    public function create(
        Request $request,
        CapabilityAuthorizer $authorizer,
        SeatingArrangementPreview $preview,
    ): Response {
        $this->requireManagement($authorizer, $request);
        $arrangement = new SeatingArrangement([
            'rows' => 5,
            'columns' => 6,
            'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
            'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        return Inertia::render('Setup/SeatingArrangements/Create', $this->formProps(
            $arrangement,
            $preview,
            $authorizer,
            $request,
            'create',
        ));
    }

    public function edit(
        Request $request,
        SeatingArrangement $arrangement,
        CapabilityAuthorizer $authorizer,
        SeatingArrangementPreview $preview,
    ): Response {
        $this->requireManagement($authorizer, $request);
        $arrangement->load('rooms:id,code,name,capacity');

        return Inertia::render('Setup/SeatingArrangements/Edit', $this->formProps(
            $arrangement,
            $preview,
            $authorizer,
            $request,
            'edit',
        ));
    }

    public function store(
        SaveSeatingArrangementRequest $request,
        CapabilityAuthorizer $authorizer,
        SaveSeatingArrangement $save,
    ): RedirectResponse {
        $this->requireManagement($authorizer, $request);

        try {
            $arrangement = $save->handle(null, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('page_state', 'conflict');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['arrangement' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return redirect()->route('setup.seating-arrangements.edit', $arrangement)
            ->with('status', 'Seating arrangement created.');
    }

    public function update(
        SaveSeatingArrangementRequest $request,
        SeatingArrangement $arrangement,
        CapabilityAuthorizer $authorizer,
        SaveSeatingArrangement $save,
    ): RedirectResponse {
        $this->requireManagement($authorizer, $request);

        try {
            $arrangement = $save->handle($arrangement, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('page_state', 'conflict');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['arrangement' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return redirect()->route('setup.seating-arrangements.edit', $arrangement)
            ->with('status', 'Seating arrangement updated.');
    }

    public function activate(
        ToggleSeatingArrangementRequest $request,
        SeatingArrangement $arrangement,
        CapabilityAuthorizer $authorizer,
        SaveSeatingArrangement $save,
    ): RedirectResponse {
        return $this->toggle($request, $arrangement, $authorizer, $save, true);
    }

    public function deactivate(
        ToggleSeatingArrangementRequest $request,
        SeatingArrangement $arrangement,
        CapabilityAuthorizer $authorizer,
        SaveSeatingArrangement $save,
    ): RedirectResponse {
        return $this->toggle($request, $arrangement, $authorizer, $save, false);
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(
        SeatingArrangement $arrangement,
        SeatingArrangementPreview $preview,
        CapabilityAuthorizer $authorizer,
        Request $request,
        string $mode,
    ): array {
        return [
            'mode' => $mode,
            'arrangement' => $arrangement->exists ? $this->arrangementPayload($arrangement) : null,
            'rooms' => $this->roomOptions(),
            'patterns' => $this->patterns(),
            'availability_modes' => $this->availabilityModes(),
            'preview' => $preview->forArrangement($arrangement),
            'referenced_facts' => $this->referencedFacts($arrangement),
            'published_facts' => $this->publishedFacts($arrangement),
            'capabilities' => [
                'schedule' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function arrangementPayload(SeatingArrangement $arrangement): array
    {
        return [
            'id' => (int) $arrangement->id,
            'name' => (string) $arrangement->name,
            'rows' => (int) $arrangement->rows,
            'columns' => (int) $arrangement->columns,
            'desk_count' => (int) $arrangement->desk_count,
            'booklet_pattern' => (string) $arrangement->booklet_pattern,
            'availability_mode' => (string) $arrangement->availability_mode,
            'is_active' => (bool) $arrangement->is_active,
            'lock_version' => (int) $arrangement->lock_version,
            'room_ids' => $arrangement->relationLoaded('rooms')
                ? $arrangement->rooms->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                : [],
            'rooms' => $arrangement->relationLoaded('rooms')
                ? $arrangement->rooms->map(fn (Room $room): array => [
                    'id' => (int) $room->id,
                    'code' => (string) $room->code,
                    'name' => (string) $room->name,
                    'capacity' => (int) $room->capacity,
                ])->values()->all()
                : [],
            'rooms_count' => (int) ($arrangement->rooms_count ?? 0),
            'default_rooms_count' => (int) ($arrangement->default_for_rooms_count ?? 0),
        ];
    }

    /**
     * @return list<array{id: int, code: string, name: string, label: string, capacity: int, is_active: bool}>
     */
    private function roomOptions(): array
    {
        return Room::query()
            ->where('institution_id', Institution::current()->id)
            ->examRooms()
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room): array => [
                'id' => (int) $room->id,
                'code' => (string) $room->code,
                'name' => (string) $room->name,
                'label' => sprintf('%s · %s (cap. %d)', $room->code, $room->name, (int) $room->capacity),
                'capacity' => (int) $room->capacity,
                'is_active' => (bool) $room->is_active,
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    private function patterns(): array
    {
        return [
            ['value' => SeatingArrangement::PATTERN_UNIFORM, 'label' => 'Uniform', 'description' => 'Every desk uses booklet intent A.'],
            ['value' => SeatingArrangement::PATTERN_CHECKERBOARD, 'label' => 'Checkerboard', 'description' => 'Adjacent desks alternate A and B across rows and columns.'],
            ['value' => SeatingArrangement::PATTERN_LATIN_FOUR, 'label' => 'Latin four', 'description' => 'A, B, C, and D repeat with a one-step row offset.'],
            ['value' => SeatingArrangement::PATTERN_LINEAR_ALTERNATING, 'label' => 'Linear alternating', 'description' => 'A and B alternate continuously in row-major order.'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function availabilityModes(): array
    {
        return [
            ['value' => SeatingArrangement::AVAILABILITY_ALL_ROOMS, 'label' => 'All compatible rooms'],
            ['value' => SeatingArrangement::AVAILABILITY_SELECTED_ROOMS, 'label' => 'Selected rooms only'],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function referencedFacts(SeatingArrangement $arrangement): array
    {
        if (! $arrangement->exists) {
            return ['exam_sessions' => 0, 'draft_sessions' => 0];
        }

        return [
            'exam_sessions' => ExamSession::query()->where('seating_arrangement_id', $arrangement->id)->count(),
            'draft_sessions' => ExamSession::query()
                ->where('seating_arrangement_id', $arrangement->id)
                ->where('status', ExamSession::STATUS_DRAFT)
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function publishedFacts(SeatingArrangement $arrangement): array
    {
        if (! $arrangement->exists) {
            return ['snapshots' => 0, 'current_snapshots' => 0];
        }

        return [
            'snapshots' => ExamSessionMaterialSnapshot::query()
                ->where('seating_arrangement_id', $arrangement->id)
                ->count(),
            'current_snapshots' => ExamSessionMaterialSnapshot::query()
                ->where('seating_arrangement_id', $arrangement->id)
                ->where('is_current', true)
                ->count(),
        ];
    }

    private function toggle(
        ToggleSeatingArrangementRequest $request,
        SeatingArrangement $arrangement,
        CapabilityAuthorizer $authorizer,
        SaveSeatingArrangement $save,
        bool $active,
    ): RedirectResponse {
        $this->requireManagement($authorizer, $request);
        $arrangement->load('rooms:id');

        try {
            $save->handle($arrangement, [
                'name' => $arrangement->name,
                'rows' => $arrangement->rows,
                'columns' => $arrangement->columns,
                'booklet_pattern' => $arrangement->booklet_pattern,
                'availability_mode' => $arrangement->availability_mode,
                'room_ids' => $arrangement->rooms->pluck('id')->all(),
                'is_active' => $active,
                'lock_version' => $request->integer('lock_version'),
            ], $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('page_state', 'conflict');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['arrangement' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', $active ? 'Seating arrangement activated.' : 'Seating arrangement deactivated.');
    }

    private function requireManagement(CapabilityAuthorizer $authorizer, Request $request): void
    {
        $institution = Institution::current();
        abort_unless($institution->usesSeatingArrangements(), 404);
        abort_unless($authorizer->allows(
            $request->user(),
            CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            ['institution_id' => (int) $institution->id],
        ), 403);
    }
}

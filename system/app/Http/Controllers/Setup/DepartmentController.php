<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StoreDepartmentRequest;
use App\Http\Requests\Setup\UpdateDepartmentRequest;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    use HasSetupCapabilities;

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireProgramSetup($authorizer, $request);

        $officeMappingAvailable = Schema::hasColumn('departments', 'office_room_id');
        $institution = Institution::current();
        $departmentRelations = [
            'dean:id,name,email',
            'programs:id,code,name,department_id,is_active',
        ];
        if ($officeMappingAvailable) {
            $departmentRelations[] = 'officeRoom:id,code,name,building_name,floor_level';
        }
        $departments = Department::query()
            ->where('institution_id', $institution->id)
            ->with($departmentRelations)
            ->withCount('programs')
            ->orderBy('code')
            ->get();

        $eligibleDeans = User::query()
            ->whereHas('staffProfile', fn ($q) => $q
                ->where('institution_id', $institution->id)
                ->where('status', StaffProfile::STATUS_ACTIVE))
            ->get(['id', 'name', 'email']);
        $officeRooms = $officeMappingAvailable
            ? Room::query()
                ->where('institution_id', $institution->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'space_kind', 'building_name', 'floor_level'])
            : collect();

        return Inertia::render('Setup/Departments', [
            'page_state' => 'ready',
            'departments' => $departments,
            'eligible_deans' => $eligibleDeans,
            'office_rooms' => $officeRooms,
            'capabilities' => $this->capabilities($authorizer, $request),
            'office_mapping_available' => $officeMappingAvailable,
        ]);
    }

    public function store(
        StoreDepartmentRequest $request,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireProgramSetup($authorizer, $request);

        $institution = Institution::current();
        $deanUserId = $request->validated('dean_user_id') ?: null;

        $department = Department::query()->create([
            'institution_id' => $institution->id,
            'code' => strtoupper($request->validated('code')),
            'name' => $request->validated('name'),
            'dean_user_id' => $deanUserId,
            'is_active' => (bool) ($request->validated('is_active') ?? true),
        ]);

        if ($deanUserId !== null) {
            StaffProfile::query()
                ->where('institution_id', $institution->id)
                ->where('status', StaffProfile::STATUS_ACTIVE)
                ->where('user_id', $deanUserId)
                ->update(['department_id' => $department->id]);
        }

        $audit->handle('setup.department_upserted', [
            'department_id' => $department->id,
            'code' => $department->code,
            'dean_user_id' => $department->dean_user_id,
            'is_active' => $department->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', "College / Department {$department->code} created.");
    }

    public function update(
        UpdateDepartmentRequest $request,
        Department $department,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireProgramSetup($authorizer, $request);
        $this->requireCurrentInstitution($department);

        $previousDeanId = $department->dean_user_id;
        $newDeanId = $request->validated('dean_user_id') ?: null;

        $updates = [
            'name' => $request->validated('name'),
            'dean_user_id' => $newDeanId,
            'is_active' => (bool) $request->validated('is_active'),
        ];
        if (Schema::hasColumn('departments', 'office_room_id')
            && array_key_exists('office_room_id', $request->validated())) {
            $updates['office_room_id'] = $request->validated('office_room_id') ?: null;
        }
        $department->update($updates);

        // If dean changed, update staff profile
        if ($previousDeanId !== $newDeanId) {
            if ($previousDeanId !== null) {
                StaffProfile::query()
                    ->where('institution_id', (int) Institution::current()->id)
                    ->where('user_id', $previousDeanId)
                    ->where('department_id', $department->id)
                    ->update(['department_id' => null]);
            }
            if ($newDeanId !== null) {
                StaffProfile::query()
                    ->where('institution_id', (int) Institution::current()->id)
                    ->where('status', StaffProfile::STATUS_ACTIVE)
                    ->where('user_id', $newDeanId)
                    ->update(['department_id' => $department->id]);
            }
        }

        $audit->handle('setup.department_upserted', [
            'department_id' => $department->id,
            'code' => $department->code,
            'dean_user_id' => $department->dean_user_id,
            'office_room_id' => $department->office_room_id,
            'is_active' => $department->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', "Department {$department->code} updated.");
    }

    public function toggleActive(
        Department $department,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
        Request $request,
    ): RedirectResponse {
        $this->requireProgramSetup($authorizer, $request);
        $this->requireCurrentInstitution($department);

        $department->update([
            'is_active' => ! $department->is_active,
        ]);

        $audit->handle('setup.department_status_toggled', [
            'department_id' => $department->id,
            'code' => $department->code,
            'is_active' => $department->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', "Department {$department->code} status updated.");
    }

    private function requireCurrentInstitution(Department $department): void
    {
        abort_unless((int) $department->institution_id === (int) Institution::current()->id, 404);
    }
}

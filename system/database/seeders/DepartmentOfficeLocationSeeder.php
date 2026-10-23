<?php

namespace Database\Seeders;

use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DepartmentOfficeLocationSeeder extends Seeder
{
    /** @var array<string, array{name:string, latitude:string, longitude:string}> */
    private const OFFICES = [
        'CAS' => [
            'name' => 'CAS Department Office Service Point',
            'latitude' => '16.93901190822347',
            'longitude' => '120.44463602458315',
        ],
        'CTE' => [
            'name' => 'CTE Department Office Service Point',
            'latitude' => '16.93779076919476',
            'longitude' => '120.44375006009733',
        ],
        'CBME' => [
            'name' => 'CBME Department Office Service Point',
            'latitude' => '16.93751429189841',
            'longitude' => '120.44397923620706',
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('rooms')
            || ! Schema::hasTable('departments')
            || ! Schema::hasColumn('rooms', 'space_kind')
            || ! Schema::hasColumn('rooms', 'latitude')
            || ! Schema::hasColumn('rooms', 'longitude')
            || ! Schema::hasColumn('departments', 'office_room_id')) {
            throw new \RuntimeException('Department office mapping requires migrated rooms spatial columns and departments.office_room_id.');
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        if ($institution === null) {
            throw new \RuntimeException('Department office location seed requires institution SYN-INST-001.');
        }

        foreach (self::OFFICES as $departmentCode => $office) {
            DB::transaction(function () use ($institution, $departmentCode, $office): void {
                $department = Department::query()
                    ->where('institution_id', $institution->id)
                    ->where('code', $departmentCode)
                    ->first();

                if ($department === null) {
                    throw new \RuntimeException("Department {$departmentCode} is required for verified office-location seeding.");
                }
                if (! $department->is_active || $department->office_room_id !== null) {
                    Log::notice('Department office location seed preserved the existing department state.', [
                        'department_code' => $departmentCode,
                        'active' => $department->is_active,
                        'office_room_id' => $department->office_room_id,
                    ]);

                    return;
                }

                $roomCode = 'DEPT-'.$departmentCode.'-OFFICE';
                $room = Room::query()
                    ->where('institution_id', $institution->id)
                    ->where('code', $roomCode)
                    ->lockForUpdate()
                    ->first();

                if ($room === null) {
                    $room = Room::query()->create([
                        'institution_id' => $institution->id,
                        'code' => $roomCode,
                        'name' => $office['name'],
                        'space_kind' => SpaceKind::Office,
                        'capacity' => 1,
                        'latitude' => $office['latitude'],
                        'longitude' => $office['longitude'],
                        'is_active' => true,
                    ]);
                } elseif (! $this->matchesSeededOffice($room, $office)) {
                    Log::notice('Department office location seed skipped a conflicting room code.', [
                        'department_code' => $departmentCode,
                        'room_id' => $room->id,
                    ]);

                    return;
                }

                $alreadyAssigned = Department::query()
                    ->where('institution_id', $institution->id)
                    ->where('office_room_id', $room->id)
                    ->where('id', '<>', $department->id)
                    ->exists();

                if ($alreadyAssigned) {
                    Log::notice('Department office location seed skipped an office room assigned to another department.', [
                        'department_code' => $departmentCode,
                        'room_id' => $room->id,
                    ]);

                    return;
                }

                if (! $alreadyAssigned) {
                    Department::query()
                        ->whereKey($department->id)
                        ->where('institution_id', $institution->id)
                        ->whereNull('office_room_id')
                        ->update(['office_room_id' => $room->id]);
                }
            });
        }
    }

    /** @param array{name:string, latitude:string, longitude:string} $office */
    private function matchesSeededOffice(Room $room, array $office): bool
    {
        return in_array($room->space_kind, [SpaceKind::Office, SpaceKind::ServicePoint], true)
            && $room->name === $office['name']
            && $this->databaseCoordinate((string) $room->latitude) === $this->databaseCoordinate($office['latitude'])
            && $this->databaseCoordinate((string) $room->longitude) === $this->databaseCoordinate($office['longitude'])
            && $room->is_active;
    }

    private function databaseCoordinate(string $coordinate): string
    {
        return number_format((float) $coordinate, 7, '.', '');
    }
}

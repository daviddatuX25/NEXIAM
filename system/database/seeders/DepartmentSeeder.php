<?php

namespace Database\Seeders;

use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first()
            ?? Institution::query()->first();

        if ($institution === null) {
            return;
        }

        $cteDean = User::query()->where('email', 'cte.dean@nexiam.test')->first()
            ?? User::query()->where('email', 'dean.cte@nexiam.test')->first();
        $cbmeDean = User::query()->where('email', 'cbme.dean@nexiam.test')->first()
            ?? User::query()->where('email', 'dean.cbme@nexiam.test')->first();
        $casDean = User::query()->where('email', 'cas.dean@nexiam.test')->first()
            ?? User::query()->where('email', 'dean.cas@nexiam.test')->first()
            ?? User::query()->where('email', 'dean.ccit@nexiam.test')->first();

        $programsByCode = array_column(app(TagudinUndergraduateCatalog::class)->programs(), null, 'code');
        $departments = [
            [
                'code' => 'CTE',
                'name' => 'College of Teacher Education',
                'dean_user_id' => $cteDean?->id,
                'programs' => [
                    'BSEd',
                    'BSEd-Eng',
                    'BSEd-Math',
                    'BEEd',
                    'BPEd',
                ],
            ],
            [
                'code' => 'CBME',
                'name' => 'College of Business Management and Entrepreneurship',
                'dean_user_id' => $cbmeDean?->id,
                'programs' => [
                    'BSBA',
                    'BSEntrep',
                    'BPA',
                ],
            ],
            [
                'code' => 'CAS',
                'name' => 'College of Arts and Sciences',
                'dean_user_id' => $casDean?->id,
                'programs' => [
                    'BSMath',
                    'BA-EL',
                    'BA-Psych',
                    'BASS',
                    'BSIT',
                ],
            ],
        ];

        foreach ($departments as $deptData) {
            $department = Department::query()->updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'code' => $deptData['code'],
                ],
                [
                    'name' => $deptData['name'],
                    'dean_user_id' => $deptData['dean_user_id'],
                    'is_active' => true,
                ],
            );

            foreach ($deptData['programs'] as $programCode) {
                $progData = $programsByCode[$programCode];
                Program::query()->updateOrCreate(
                    [
                        'institution_id' => $institution->id,
                        'code' => $progData['code'],
                    ],
                    [
                        'name' => $progData['name'],
                        'department_id' => $department->id,
                        'is_active' => true,
                        'is_admission_offered' => true,
                    ],
                );
            }

            // If this department has an assigned dean, sync the staff profile
            if ($deptData['dean_user_id'] !== null) {
                StaffProfile::query()
                    ->where('user_id', $deptData['dean_user_id'])
                    ->update(['department_id' => $department->id]);
            }
        }

        Program::query()
            ->where('institution_id', $institution->id)
            ->whereIn('code', app(TagudinUndergraduateCatalog::class)->retiredCodes())
            ->update(['is_admission_offered' => false]);
    }
}

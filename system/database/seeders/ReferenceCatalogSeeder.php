<?php

namespace Database\Seeders;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use Illuminate\Database\Seeder;

class ReferenceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

        foreach ([
            ['code' => 'BSN', 'name' => 'Bachelor of Science in Nursing'],
            ['code' => 'BSPSY', 'name' => 'Bachelor of Science in Psychology'],
        ] as $program) {
            Program::query()->updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'code' => $program['code'],
                ],
                [
                    'name' => $program['name'],
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['code' => 'RM-101', 'name' => 'Main Hall 101', 'capacity' => 40],
            ['code' => 'RM-LabA', 'name' => 'Guidance Lab A', 'capacity' => 24],
        ] as $room) {
            Room::query()->updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'code' => $room['code'],
                ],
                [
                    'name' => $room['name'],
                    'capacity' => $room['capacity'],
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['code' => 'VERBAL', 'name' => 'Verbal Reasoning', 'version' => 1],
            ['code' => 'QUANT', 'name' => 'Quantitative Reasoning', 'version' => 1],
        ] as $area) {
            AptitudeArea::query()->updateOrCreate(
                [
                    'code' => $area['code'],
                    'version' => $area['version'],
                ],
                [
                    'name' => $area['name'],
                    'is_active' => true,
                ],
            );
        }

        ApplicationWindow::query()->updateOrCreate(
            [
                'academic_cycle_id' => $cycle->id,
                'code' => 'SYN-WINDOW-2026-A',
            ],
            [
                'name' => 'Synthetic Intake Window A',
                'opens_at' => '2026-02-01 00:00:00',
                'closes_at' => '2026-04-30 23:59:59',
                'status' => 'published',
            ],
        );
    }
}

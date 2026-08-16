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
            ['code' => 'VERBAL', 'name' => 'Verbal Reasoning', 'version' => 1, 'max_items' => 100],
            ['code' => 'QUANT', 'name' => 'Quantitative Reasoning', 'version' => 1, 'max_items' => 100],
            ['code' => 'PROFILE_VERBAL', 'name' => 'Profile Verbal Reasoning', 'version' => 1, 'max_items' => 25],
            ['code' => 'PROFILE_QUANT', 'name' => 'Profile Quantitative Reasoning', 'version' => 1, 'max_items' => 25],
            ['code' => 'LOGIC', 'name' => 'Logical Reasoning', 'version' => 1, 'max_items' => 25],
            ['code' => 'SPATIAL', 'name' => 'Spatial Reasoning', 'version' => 1, 'max_items' => 25],
            ['code' => 'MEMORY', 'name' => 'Memory Recall', 'version' => 1, 'max_items' => 25],
            ['code' => 'LANGUAGE', 'name' => 'Language Usage', 'version' => 1, 'max_items' => 25],
        ] as $area) {
            $aptitude = AptitudeArea::query()->updateOrCreate(
                [
                    'code' => $area['code'],
                    'version' => $area['version'],
                ],
                [
                    'name' => $area['name'],
                    'scoring_method' => AptitudeArea::SCORING_METHOD_CONVERSION_TABLE,
                    'max_items' => $area['max_items'],
                    'is_active' => true,
                ],
            );

            // Developer-ready conversion tables covering 0..100 so any seeded
            // raw score converts (demo scores in GradingDemoSeeder use 85).
            if ($aptitude->conversionRows()->count() === 0) {
                foreach ([
                    ['min_score' => 0, 'max_score' => 49, 'output_value' => 'Below Average'],
                    ['min_score' => 50, 'max_score' => 74, 'output_value' => 'Average'],
                    ['min_score' => 75, 'max_score' => 89, 'output_value' => 'Above Average'],
                    ['min_score' => 90, 'max_score' => 100, 'output_value' => 'Excellent'],
                ] as $row) {
                    $aptitude->conversionRows()->create($row);
                }
            }
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

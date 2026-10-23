<?php

namespace Database\Seeders;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use Illuminate\Database\Seeder;

class ReferenceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

        $rooms = [
            [
                'code' => 'ITBR-104',
                'name' => 'ITBR 104',
                'capacity' => 30,
                'space_kind' => SpaceKind::Room,
                'latitude' => 16.9389720,
                'longitude' => 120.4446960,
                'building_name' => 'IT Building',
                'floor_level' => '1st Floor',
                'directions_hint' => 'Center front of land grounds, 1st floor of the IT Building.',
                'photo_path' => null,
            ],
            [
                'code' => 'ITBR-105',
                'name' => 'ITBR 105',
                'capacity' => 40,
                'space_kind' => SpaceKind::Room,
                'latitude' => 16.9389570,
                'longitude' => 120.4447570,
                'building_name' => 'IT Building',
                'floor_level' => '1st Floor',
                'directions_hint' => 'First floor of the IT Building, just in front of land grounds.',
                'photo_path' => null,
            ],
            [
                'code' => 'CC-01',
                'name' => 'Covered Court',
                'capacity' => 150,
                'space_kind' => SpaceKind::AssemblyVenue,
                'latitude' => 16.9381384,
                'longitude' => 120.4438426,
                'building_name' => 'Campus Sports Complex',
                'floor_level' => 'Ground Level',
                'directions_hint' => 'Behind the main administration building, adjacent to the grandstand bleachers.',
                'photo_path' => null,
            ],
            [
                'code' => 'REG-WIN-1',
                'name' => 'Registrar Window 1',
                'capacity' => 1,
                'space_kind' => SpaceKind::ServicePoint,
                'latitude' => 16.9377033,
                'longitude' => 120.4440507,
                'building_name' => 'Administration Building',
                'floor_level' => 'Ground Floor',
                'directions_hint' => 'Main administration building lobby, first counter on the right.',
                'photo_path' => null,
            ],
        ];

        if (app()->environment('testing')) {
            $rooms = [
                ...$rooms,
                [
                    'code' => 'RM-101',
                    'name' => 'Main Hall 101',
                    'capacity' => 40,
                    'space_kind' => SpaceKind::Room,
                    'latitude' => 16.9389570,
                    'longitude' => 120.4447570,
                    'building_name' => 'Academic Building',
                    'floor_level' => 'Ground Floor',
                    'directions_hint' => 'Enter the Academic Building through the main entrance; Room 101 is the first classroom on the right.',
                    'photo_path' => null,
                ],
                [
                    'code' => 'RM-LabA',
                    'name' => 'Guidance Lab A',
                    'capacity' => 24,
                    'space_kind' => SpaceKind::Room,
                    'latitude' => 16.9389720,
                    'longitude' => 120.4446960,
                    'building_name' => 'IT Building',
                    'floor_level' => '1st Floor',
                    'directions_hint' => 'Enter the IT Building lobby and follow the corridor to Computer Lab A beside the student work area.',
                    'photo_path' => null,
                ],
                [
                    'code' => 'ITBR-106',
                    'name' => 'ITBR 106 (Computer Lab)',
                    'capacity' => 40,
                    'space_kind' => SpaceKind::Room,
                    'latitude' => 16.9389570,
                    'longitude' => 120.4447570,
                    'building_name' => 'IT Building',
                    'floor_level' => '2nd Floor',
                    'directions_hint' => 'Enter main lobby, climb Staircase B to 2nd floor, second door on the left.',
                    'photo_path' => null,
                ],
            ];
        }

        foreach ($rooms as $room) {
            Room::query()->updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'code' => $room['code'],
                ],
                [
                    'name' => $room['name'],
                    'space_kind' => $room['space_kind'],
                    'capacity' => $room['capacity'],
                    'latitude' => $room['latitude'] ?? null,
                    'longitude' => $room['longitude'] ?? null,
                    'building_name' => $room['building_name'] ?? null,
                    'floor_level' => $room['floor_level'] ?? null,
                    'directions_hint' => $room['directions_hint'] ?? null,
                    'photo_path' => $room['photo_path'] ?? null,
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['code' => 'VERBAL', 'name' => 'Verbal Aptitude', 'version' => 1, 'max_items' => 100],
            ['code' => 'QUANT', 'name' => 'Numerical Aptitude', 'version' => 1, 'max_items' => 100],
            ['code' => 'PROFILE_VERBAL', 'name' => 'Profile Verbal Reasoning', 'version' => 1, 'max_items' => 25],
            ['code' => 'PROFILE_QUANT', 'name' => 'Profile Quantitative Reasoning', 'version' => 1, 'max_items' => 25],
            ['code' => 'LOGIC', 'name' => 'General Ability', 'version' => 1, 'max_items' => 25],
            ['code' => 'SPATIAL', 'name' => 'Spatial Aptitude', 'version' => 1, 'max_items' => 25],
            ['code' => 'MEMORY', 'name' => 'Perceptual Aptitude', 'version' => 1, 'max_items' => 25],
            ['code' => 'LANGUAGE', 'name' => 'Manual Dexterity', 'version' => 1, 'max_items' => 25],
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
                'name' => 'Regular Admissions Intake Window',
                'opens_at' => '2026-08-01 00:00:00',
                'closes_at' => '2026-12-31 23:59:59',
                'status' => 'published',
            ],
        );
    }
}

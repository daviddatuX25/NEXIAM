<?php

namespace Database\Seeders;

use App\Models\AptitudeArea;
use Illuminate\Database\Seeder;

class AptitudeAreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['name' => 'General Ability',      'code' => 'GA',  'max_items' => 25, 'display_order' => 1, 'formula' => '(x / max_items) * 100'],
            ['name' => 'Verbal Aptitude',      'code' => 'VA',  'max_items' => 25, 'display_order' => 2, 'formula' => '(x / max_items) * 100'],
            ['name' => 'Numerical Aptitude',   'code' => 'NAP', 'max_items' => 25, 'display_order' => 3, 'formula' => '(x / max_items) * 100'],
            ['name' => 'Spatial Aptitude',     'code' => 'SPA', 'max_items' => 25, 'display_order' => 4, 'formula' => '(x / max_items) * 100'],
            ['name' => 'Perceptual Aptitude',  'code' => 'PA',  'max_items' => 25, 'display_order' => 5, 'formula' => '(x / max_items) * 100'],
            ['name' => 'Manual Dexterity',     'code' => 'MD',  'max_items' => 20, 'display_order' => 6, 'formula' => '(x / max_items) * 100'],
        ];

        foreach ($areas as $area) {
            AptitudeArea::firstOrCreate(
                ['code' => $area['code']],
                array_merge($area, [
                    'description' => null,
                    'is_active' => true,
                    'scoring_method' => 'formula',
                ])
            );
        }
    }
}

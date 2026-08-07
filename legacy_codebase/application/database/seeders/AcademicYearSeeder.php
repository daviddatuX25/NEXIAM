<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::updateOrCreate(
            ['academic_year' => '2025-2026', 'semester' => '1'],
            [
                'is_active' => true,
                'application_start_date' => now()->subDays(7)->toDateString(),
                'application_end_date' => now()->addDays(30)->toDateString(),
            ]
        );

        if (AcademicYear::where('is_active', true)->count() > 1) {
            AcademicYear::where('id', '!=', $academicYear->id)->update(['is_active' => false]);
        }
    }
}

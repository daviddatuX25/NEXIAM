<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        if (Course::exists()) {
            return;
        }

        $courses = [
            ['name' => 'Bachelor of Secondary Education', 'code' => 'BSEd'],
            ['name' => 'Bachelor of Elementary Education', 'code' => 'BEEd'],
            ['name' => 'Bachelor of Physical Education', 'code' => 'BPEd'],
            ['name' => 'Bachelor of Science in Mathematics', 'code' => 'BSMath'],
            ['name' => 'Bachelor of Arts in Psychology', 'code' => 'BAPsych'],
            ['name' => 'Bachelor of Science in Psychology', 'code' => 'BSPsych'],
            ['name' => 'Bachelor of Science in Information Technology', 'code' => 'BSIT'],
            ['name' => 'Bachelor of Science in Business Administration', 'code' => 'BSBA'],
            ['name' => 'Bachelor of Science in Entrepreneurship', 'code' => 'BSEntrep'],
            ['name' => 'Bachelor of Public Administration', 'code' => 'BPA'],
            ['name' => 'Bachelor of Arts in English Language', 'code' => 'BAEL'],
            ['name' => 'Bachelor of Arts in Social Science', 'code' => 'BASS'],
        ];

        foreach ($courses as $c) {
            Course::firstOrCreate(
                ['code' => $c['code']],
                ['name' => $c['name'], 'is_active' => true]
            );
        }
    }
}

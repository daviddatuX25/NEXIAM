<?php

namespace Database\Seeders;

use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeGradeField;
use Illuminate\Database\Seeder;

class IntakeGradeFieldSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

        $defaults = [
            ['code' => 'GWA', 'label' => 'GWA', 'field_kind' => IntakeGradeField::KIND_GWA, 'sort_order' => 10],
            ['code' => 'ENG', 'label' => 'English', 'field_kind' => IntakeGradeField::KIND_SUBJECT, 'sort_order' => 20],
            ['code' => 'FIL', 'label' => 'Filipino', 'field_kind' => IntakeGradeField::KIND_SUBJECT, 'sort_order' => 30],
            ['code' => 'MATH', 'label' => 'Mathematics', 'field_kind' => IntakeGradeField::KIND_SUBJECT, 'sort_order' => 40],
            ['code' => 'SCI', 'label' => 'Science', 'field_kind' => IntakeGradeField::KIND_SUBJECT, 'sort_order' => 50],
        ];

        foreach ($defaults as $row) {
            IntakeGradeField::query()->updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'code' => $row['code'],
                ],
                [
                    'label' => $row['label'],
                    'field_kind' => $row['field_kind'],
                    'is_required' => false,
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                    'min_value' => 0,
                    'max_value' => 100,
                ],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\RatingScale;
use Illuminate\Database\Seeder;

class RatingScaleSeeder extends Seeder
{
    public function run(): void
    {
        RatingScale::updateOrCreate(['name' => 'ISPSC Standard'], [
            'ranges' => [
                ['min' => 90, 'max' => 100, 'label' => 'Outstanding'],
                ['min' => 75, 'max' => 89, 'label' => 'Above Average'],
                ['min' => 50, 'max' => 74, 'label' => 'Average'],
                ['min' => 25, 'max' => 49, 'label' => 'Below Average'],
                ['min' => 0, 'max' => 24, 'label' => 'Needs Improvement'],
            ],
            'is_default' => true,
        ]);
    }
}

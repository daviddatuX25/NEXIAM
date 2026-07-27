<?php

namespace Database\Factories;

use App\Models\ResultSheetTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultSheetTemplate>
 */
class ResultSheetTemplateFactory extends Factory
{
    protected $model = ResultSheetTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'mode' => ResultSheetTemplate::MODE_HTML,
            'paper_size' => ResultSheetTemplate::PAPER_A4,
            'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
            'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
            'content' => '<div>{{applicant_name}}</div>',
            'document_path' => null,
            'watermark_text' => null,
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}

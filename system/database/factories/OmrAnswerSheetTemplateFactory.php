<?php

namespace Database\Factories;

use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OmrAnswerSheetTemplate>
 */
class OmrAnswerSheetTemplateFactory extends Factory
{
    protected $model = OmrAnswerSheetTemplate::class;

    public function definition(): array
    {
        return [
            'institution_id' => Institution::query()->value('id') ?? Institution::query()->create(['name' => 'Default Campus', 'code' => 'MAIN'])->id,
            'code' => fake()->unique()->bothify('OMR-####-????'),
            'name' => fake()->words(3, true),
            'is_synthetic' => false,
        ];
    }
}

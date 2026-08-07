<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('DEPT???'));

        return [
            'name' => fake()->company().' Department',
            'code' => $code,
        ];
    }
}

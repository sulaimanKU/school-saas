<?php

namespace Database\Factories\Academic;

use App\Modules\Academics\Models\AcademicClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicClassFactory extends Factory
{
    protected $model = AcademicClass::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->bothify('Grade ##'), 'code' => fake()->unique()->bothify('C###'), 'grade_level' => 7, 'display_order' => 7, 'active' => true];
    }
}

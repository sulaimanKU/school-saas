<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Operations\Models\ExamType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamTypeFactory extends Factory
{
    protected $model = ExamType::class;

    public function definition(): array
    {
        return ['name' => 'Custom test', 'code' => fake()->unique()->bothify('EX-####')];
    }
}

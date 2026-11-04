<?php

namespace Database\Factories\Academic;

use App\Modules\Academics\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true), 'code' => fake()->unique()->bothify('S###'), 'active' => true];
    }
}

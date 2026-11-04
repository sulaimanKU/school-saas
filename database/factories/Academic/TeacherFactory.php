<?php

namespace Database\Factories\Academic;

use App\Modules\Academics\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return ['employee_code' => fake()->unique()->bothify('EMP-####'), 'full_name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'phone' => null, 'status' => 'active'];
    }
}

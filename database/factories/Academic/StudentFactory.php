<?php

namespace Database\Factories\Academic;

use App\Modules\Academics\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return ['admission_number' => fake()->unique()->bothify('TEST-######'), 'full_name' => fake()->name(), 'date_of_birth' => '2014-01-15', 'gender' => 'unspecified', 'guardian_name' => fake()->name(), 'guardian_phone' => '03000000000', 'admitted_on' => '2026-08-01', 'status' => 'active'];
    }
}

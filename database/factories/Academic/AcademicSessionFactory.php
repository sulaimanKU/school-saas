<?php

namespace Database\Factories\Academic;

use App\Modules\Academics\Models\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicSessionFactory extends Factory
{
    protected $model = AcademicSession::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->bothify('Session ####'), 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'active' => false, 'is_current' => false];
    }
}

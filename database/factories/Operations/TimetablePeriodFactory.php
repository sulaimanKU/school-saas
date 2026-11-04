<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Operations\Models\TimetablePeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimetablePeriodFactory extends Factory
{
    protected $model = TimetablePeriod::class;

    public function definition(): array
    {
        return ['name' => 'Test period', 'code' => fake()->unique()->bothify('P-####'), 'starts_at' => '08:00', 'ends_at' => '09:00', 'kind' => 'teaching'];
    }
}

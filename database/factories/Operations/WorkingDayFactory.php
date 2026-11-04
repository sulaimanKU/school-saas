<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Operations\Models\WorkingDay;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkingDayFactory extends Factory
{
    protected $model = WorkingDay::class;

    public function definition(): array
    {
        return ['weekday' => 1, 'is_working' => true, 'opens_at' => '08:00', 'closes_at' => '15:00'];
    }
}

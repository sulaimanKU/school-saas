<?php

namespace Database\Factories;

use App\Modules\Platform\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return ['code' => fake()->unique()->slug(), 'name' => fake()->word(), 'monthly_price_minor' => 1500000, 'currency' => 'PKR', 'school_capacity' => 400, 'is_demo' => false];
    }
}

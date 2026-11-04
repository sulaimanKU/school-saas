<?php

namespace Database\Factories;

use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Models\SubscriptionPlan;
use App\Modules\Schools\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return ['school_id' => School::factory(), 'subscription_plan_id' => SubscriptionPlan::factory(), 'status' => 'trial', 'starts_at' => '2026-08-01', 'ends_at' => '2027-07-31'];
    }
}

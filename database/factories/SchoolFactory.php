<?php

namespace Database\Factories;

use App\Modules\Schools\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        return ['slug' => fake()->unique()->slug(3), 'name' => fake()->company().' School', 'city' => fake()->city(), 'status' => 'active', 'is_demo' => false];
    }
}

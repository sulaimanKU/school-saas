<?php

namespace Database\Factories;

use App\Modules\Platform\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition(): array
    {
        return ['code' => fake()->unique()->slug(), 'name' => fake()->words(2, true), 'description' => 'Feature definition.'];
    }
}

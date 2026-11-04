<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Operations\Models\GradingScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradingSchemeFactory extends Factory
{
    protected $model = GradingScheme::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->bothify('Scheme ####'), 'ranking_enabled' => false, 'logo_path' => '/images/school-emblem.svg'];
    }
}

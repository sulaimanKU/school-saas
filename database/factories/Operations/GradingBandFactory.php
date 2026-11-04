<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Operations\Models\GradingBand;
use App\Modules\Academics\Operations\Models\GradingScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradingBandFactory extends Factory
{
    protected $model = GradingBand::class;

    public function definition(): array
    {
        return ['grading_scheme_id' => GradingScheme::factory(), 'label' => 'Pass', 'minimum_percent' => 0, 'maximum_percent' => 100, 'is_pass' => true];
    }
}

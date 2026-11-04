<?php

namespace Database\Factories;

use App\Modules\Schools\Models\School;
use App\Modules\Schools\Models\SchoolSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolSettingFactory extends Factory
{
    protected $model = SchoolSetting::class;

    public function definition(): array
    {
        return ['school_id' => School::factory(), 'key' => fake()->unique()->slug(), 'value' => ['timezone' => 'Asia/Karachi']];
    }
}

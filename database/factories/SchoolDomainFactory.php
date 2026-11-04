<?php

namespace Database\Factories;

use App\Modules\Schools\Models\School;
use App\Modules\Schools\Models\SchoolDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolDomainFactory extends Factory
{
    protected $model = SchoolDomain::class;

    public function definition(): array
    {
        return ['school_id' => School::factory(), 'hostname' => fake()->unique()->domainName(), 'verified_at' => null];
    }
}

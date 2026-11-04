<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Schools\Models\School;
use App\Modules\Schools\Models\SchoolMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolMembershipFactory extends Factory
{
    protected $model = SchoolMembership::class;

    public function definition(): array
    {
        return ['school_id' => School::factory(), 'user_id' => User::factory(), 'role' => 'staff', 'status' => 'pending'];
    }
}

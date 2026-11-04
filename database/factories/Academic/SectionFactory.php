<?php

namespace Database\Factories\Academic;

use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return ['academic_class_id' => AcademicClass::factory(), 'name' => 'Section A', 'code' => 'A', 'capacity' => 30, 'active' => true];
    }
}

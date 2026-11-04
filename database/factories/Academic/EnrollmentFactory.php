<?php

namespace Database\Factories\Academic;

use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        $section = Section::factory()->create();

        return ['student_id' => Student::factory(), 'academic_session_id' => AcademicSession::factory(), 'academic_class_id' => $section->academic_class_id, 'section_id' => $section->id, 'started_on' => '2026-08-01', 'status' => 'active'];
    }
}

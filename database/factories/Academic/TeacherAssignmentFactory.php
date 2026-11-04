<?php

namespace Database\Factories\Academic;

use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Teacher;
use App\Modules\Academics\Models\TeacherAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherAssignmentFactory extends Factory
{
    protected $model = TeacherAssignment::class;

    public function definition(): array
    {
        $section = Section::factory()->create();

        return ['teacher_id' => Teacher::factory(), 'academic_session_id' => AcademicSession::factory(), 'academic_class_id' => $section->academic_class_id, 'section_id' => $section->id, 'kind' => 'form_teacher', 'subject_id' => null];
    }
}

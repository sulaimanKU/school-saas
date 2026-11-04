<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Operations\Models\ExamMark;
use App\Modules\Academics\Operations\Models\ExamSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamMarkFactory extends Factory
{
    protected $model = ExamMark::class;

    public function definition(): array
    {
        $s = ExamSchedule::factory()->create();
        $e = Enrollment::factory()->create(['academic_session_id' => $s->academic_session_id, 'academic_class_id' => $s->academic_class_id, 'section_id' => $s->section_id]);

        return ['exam_id' => $s->exam_id, 'exam_schedule_id' => $s->id, 'subject_id' => $s->subject_id, 'student_id' => $e->student_id, 'enrollment_id' => $e->id, 'status' => 'scored', 'obtained_marks' => 70];
    }
}

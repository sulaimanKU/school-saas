<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Operations\Models\Exam;
use App\Modules\Academics\Operations\Models\ExamSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamScheduleFactory extends Factory
{
    protected $model = ExamSchedule::class;

    public function definition(): array
    {
        $exam = Exam::factory()->create();
        $section = Section::factory()->create();
        $subject = Subject::factory()->create();
        ClassSubject::create(['academic_class_id' => $section->academic_class_id, 'subject_id' => $subject->id, 'classification' => 'compulsory']);

        return ['exam_id' => $exam->id, 'academic_session_id' => $exam->academic_session_id, 'academic_class_id' => $section->academic_class_id, 'section_id' => $section->id, 'subject_id' => $subject->id, 'exam_date' => '2026-09-01', 'starts_at' => '09:00', 'ends_at' => '10:00', 'maximum_marks' => 100, 'passing_marks' => 50];
    }
}

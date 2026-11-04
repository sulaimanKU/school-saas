<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherAssignment;
use App\Modules\Academics\Operations\Models\TimetableEntry;
use App\Modules\Academics\Operations\Models\TimetablePeriod;
use App\Modules\Academics\Operations\Models\WorkingDay;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimetableEntryFactory extends Factory
{
    protected $model = TimetableEntry::class;

    public function definition(): array
    {
        $a = TeacherAssignment::factory()->create();
        $subject = Subject::factory()->create();
        ClassSubject::create(['academic_class_id' => $a->academic_class_id, 'subject_id' => $subject->id, 'classification' => 'compulsory']);
        $a->update(['kind' => 'teaching', 'subject_id' => $subject->id]);
        $p = TimetablePeriod::factory()->create();
        WorkingDay::firstOrCreate(['weekday' => 1], ['is_working' => true, 'opens_at' => '08:00', 'closes_at' => '15:00']);

        return ['academic_session_id' => $a->academic_session_id, 'section_id' => $a->section_id, 'teacher_assignment_id' => $a->id, 'timetable_period_id' => $p->id, 'weekday' => 1];
    }
}

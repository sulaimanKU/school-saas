<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Operations\Models\AttendanceSession;
use App\Modules\Academics\Operations\Models\StudentAttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentAttendanceRecordFactory extends Factory
{
    protected $model = StudentAttendanceRecord::class;

    public function definition(): array
    {
        $a = AttendanceSession::factory()->create();
        $section = Section::findOrFail($a->section_id);
        $e = Enrollment::factory()->create(['academic_session_id' => $a->academic_session_id, 'academic_class_id' => $section->academic_class_id, 'section_id' => $section->id]);

        return ['attendance_session_id' => $a->id, 'academic_session_id' => $a->academic_session_id, 'attendance_date' => $a->attendance_date, 'student_id' => $e->student_id, 'enrollment_id' => $e->id, 'status' => 'present'];
    }
}

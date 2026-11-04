<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Operations\Models\AttendanceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceSessionFactory extends Factory
{
    protected $model = AttendanceSession::class;

    public function definition(): array
    {
        return ['academic_session_id' => AcademicSession::factory(), 'section_id' => Section::factory(), 'attendance_date' => '2026-09-01'];
    }
}

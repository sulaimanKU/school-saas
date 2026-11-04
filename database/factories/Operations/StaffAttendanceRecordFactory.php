<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Models\Teacher;
use App\Modules\Academics\Operations\Models\StaffAttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffAttendanceRecordFactory extends Factory
{
    protected $model = StaffAttendanceRecord::class;

    public function definition(): array
    {
        return ['teacher_id' => Teacher::factory(), 'attendance_date' => '2026-09-01', 'status' => 'present'];
    }
}

<?php

namespace App\Modules\Academics\Operations\Models;

use App\Modules\Academics\Models\AcademicRecord;
use App\Modules\Academics\Models\Teacher;

class StaffAttendanceRecord extends AcademicRecord
{
    protected $table = 'staff_attendance_records';

    protected static function newFactory()
    {
        $class = 'Database\\Factories\\Operations\\'.class_basename(static::class).'Factory';

        return $class::new();
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_working' => 'boolean', 'ranking_enabled' => 'boolean', 'is_pass' => 'boolean', 'snapshot' => 'array', 'actor_context' => 'array', 'changes' => 'array']);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}

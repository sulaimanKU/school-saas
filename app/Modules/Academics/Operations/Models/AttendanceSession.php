<?php

namespace App\Modules\Academics\Operations\Models;

use App\Modules\Academics\Models\AcademicRecord;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Section;

class AttendanceSession extends AcademicRecord
{
    protected $table = 'attendance_sessions';

    protected static function newFactory()
    {
        $class = 'Database\\Factories\\Operations\\'.class_basename(static::class).'Factory';

        return $class::new();
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_working' => 'boolean', 'ranking_enabled' => 'boolean', 'is_pass' => 'boolean', 'snapshot' => 'array', 'actor_context' => 'array', 'changes' => 'array']);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}

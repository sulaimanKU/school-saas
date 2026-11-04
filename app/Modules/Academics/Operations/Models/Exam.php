<?php

namespace App\Modules\Academics\Operations\Models;

use App\Modules\Academics\Models\AcademicRecord;
use App\Modules\Academics\Models\AcademicSession;

class Exam extends AcademicRecord
{
    protected $table = 'exams';

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

    public function examType()
    {
        return $this->belongsTo(ExamType::class);
    }

    public function gradingScheme()
    {
        return $this->belongsTo(GradingScheme::class);
    }

    public function schedules()
    {
        return $this->hasMany(ExamSchedule::class);
    }

    public function marks()
    {
        return $this->hasMany(ExamMark::class);
    }
}

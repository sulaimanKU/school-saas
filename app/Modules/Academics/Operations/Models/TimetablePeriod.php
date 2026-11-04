<?php

namespace App\Modules\Academics\Operations\Models;

use App\Modules\Academics\Models\AcademicRecord;

class TimetablePeriod extends AcademicRecord
{
    protected $table = 'timetable_periods';

    protected static function newFactory()
    {
        $class = 'Database\\Factories\\Operations\\'.class_basename(static::class).'Factory';

        return $class::new();
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_working' => 'boolean', 'ranking_enabled' => 'boolean', 'is_pass' => 'boolean', 'snapshot' => 'array', 'actor_context' => 'array', 'changes' => 'array']);
    }
}

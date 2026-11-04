<?php

namespace App\Modules\Academics\Operations\Models;

use App\Modules\Academics\Models\AcademicRecord;

class GradingScheme extends AcademicRecord
{
    protected $table = 'grading_schemes';

    protected static function newFactory()
    {
        $class = 'Database\\Factories\\Operations\\'.class_basename(static::class).'Factory';

        return $class::new();
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_working' => 'boolean', 'ranking_enabled' => 'boolean', 'is_pass' => 'boolean', 'snapshot' => 'array', 'actor_context' => 'array', 'changes' => 'array']);
    }

    public function bands()
    {
        return $this->hasMany(GradingBand::class)->orderBy('minimum_percent');
    }
}

<?php

namespace App\Modules\Academics\Operations\Models;

use App\Modules\Academics\Models\AcademicRecord;

class AcademicAuditLog extends AcademicRecord
{
    protected $table = 'academic_audit_logs';

    protected static function newFactory()
    {
        $class = 'Database\\Factories\\Operations\\'.class_basename(static::class).'Factory';

        return $class::new();
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_working' => 'boolean', 'ranking_enabled' => 'boolean', 'is_pass' => 'boolean', 'snapshot' => 'array', 'actor_context' => 'array', 'changes' => 'array']);
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn () => throw new \LogicException('Append-only academic record'));
        static::deleting(fn () => throw new \LogicException('Append-only academic record'));
    }
}

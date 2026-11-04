<?php

namespace App\Modules\Academics\Operations;

use App\Modules\Academics\Operations\Models\AcademicAuditLog;

final class AuditTrail
{
    public function record(string $operation, string $entity, ?int $id, array $before, array $after, ?string $reason = null): void
    {
        $allowed = ['status', 'obtained_marks', 'starts_at', 'ends_at', 'weekday', 'attendance_date', 'exam_date', 'maximum_marks', 'passing_marks', 'publication_version', 'teacher_assignment_id', 'section_id', 'academic_session_id', 'is_working', 'opens_at', 'closes_at', 'kind', 'ranking_enabled'];
        AcademicAuditLog::create(['operation' => $operation, 'entity_type' => $entity, 'entity_id' => $id, 'actor_context' => ['mode' => 'local-development', 'authenticated' => false], 'changes' => ['before' => array_intersect_key($before, array_flip($allowed)), 'after' => array_intersect_key($after, array_flip($allowed))], 'reason' => $reason]);
    }
}

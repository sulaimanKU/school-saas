<?php

namespace App\Modules\Academics\Operations;

use App\Modules\Academics\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;

final class EnrollmentRoster
{
    public function query(int $session, int $section, string $date): Builder
    {
        return Enrollment::where('academic_session_id', $session)->where('section_id', $section)->where('started_on', '<=', $date)->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>', $date)->orWhere(fn ($q) => $q->where('ended_on', $date)->whereIn('status', ['promoted', 'completed'])))->with(['student', 'academicClass', 'section'])->orderBy('student_id');
    }
}

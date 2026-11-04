<?php

namespace App\Modules\Academics\Operations;

use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeacherAssignment;
use App\Modules\Academics\Operations\Models\TimetableEntry;
use App\Modules\Academics\Operations\Models\TimetablePeriod;
use App\Modules\Academics\Operations\Models\WorkingDay;
use Illuminate\Validation\ValidationException;

final class TimetableService
{
    public function validate(array $data, ?int $id = null): void
    {
        $period = TimetablePeriod::findOrFail($data['timetable_period_id']);
        $session = AcademicSession::findOrFail($data['academic_session_id']);
        $section = Section::findOrFail($data['section_id']);
        if (! $session->active || ! $section->active) {
            $this->fail('Select an active session and section.');
        }
        $day = WorkingDay::where('weekday', $data['weekday'])->first();
        if (! $day || ! $day->is_working || $period->starts_at < $day->opens_at || $period->ends_at > $day->closes_at) {
            $this->fail('Entry falls outside configured school working hours.');
        }
        $assignment = ! empty($data['teacher_assignment_id']) ? TeacherAssignment::with('teacher')->findOrFail($data['teacher_assignment_id']) : null;
        if ($period->kind === 'teaching') {
            if (! $assignment || $assignment->kind !== 'teaching' || $assignment->teacher->status !== 'active' || $assignment->academic_session_id != $session->id || $assignment->section_id != $section->id) {
                $this->fail('Select a teaching assignment for this session and section.');
            }
        } elseif ($assignment) {
            $this->fail('Break and non-teaching periods cannot have subject teacher assignments.');
        }
        $entries = TimetableEntry::where('academic_session_id', $session->id)->where('weekday', $data['weekday'])->when($id, fn ($q) => $q->whereKeyNot($id))->with(['timetablePeriod', 'teacherAssignment'])->get();
        foreach ($entries as $entry) {
            if ($entry->timetablePeriod->starts_at < $period->ends_at && $entry->timetablePeriod->ends_at > $period->starts_at && ($entry->section_id == $section->id || ($assignment && $entry->teacherAssignment?->teacher_id === $assignment->teacher_id))) {
                $this->fail('Teacher or section has an overlapping timetable entry.');
            }
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['timetable_period_id' => $message]);
    }
}

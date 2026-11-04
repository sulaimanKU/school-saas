<?php

namespace App\Modules\Academics\Operations;

use App\Modules\Academics\Operations\Models\Exam;
use App\Modules\Academics\Operations\Models\ExamSchedule;
use App\Modules\Academics\Operations\Models\ExamType;
use App\Modules\Academics\Operations\Models\GradingScheme;
use App\Modules\Academics\Operations\Models\TimetableEntry;
use App\Modules\Academics\Operations\Models\TimetablePeriod;
use App\Modules\Academics\Operations\Models\WorkingDay;

final class OperationsCatalog
{
    public const MODELS = ['working-days' => WorkingDay::class, 'periods' => TimetablePeriod::class, 'timetable' => TimetableEntry::class, 'grading' => GradingScheme::class, 'exam-types' => ExamType::class, 'exams' => Exam::class, 'schedules' => ExamSchedule::class];

    public const TITLES = ['student-attendance' => 'Student Attendance', 'staff-attendance' => 'Staff Attendance', 'working-days' => 'School Working Days', 'periods' => 'School Periods', 'timetable' => 'Timetable Management', 'teacher-timetable' => 'Teacher Timetable', 'grading' => 'Grading Configuration', 'exam-types' => 'Examination Types', 'exams' => 'Examination Management', 'schedules' => 'Exam Scheduling', 'marks' => 'Marks Entry', 'results' => 'Result Management', 'reports' => 'Academic Reports', 'audit' => 'Academic Audit Trail', 'report-card' => 'Student Report Card'];

    public static function model(string $resource): string
    {
        return self::MODELS[$resource] ?? abort(404);
    }

    public static function fields(string $resource): array
    {
        $f = fn ($key, $label, $type = 'text', $source = null, $options = null) => array_filter(compact('key', 'label', 'type', 'source', 'options'), fn ($v) => $v !== null);

        return match ($resource) {
            'working-days' => [$f('weekday', 'Weekday (1 Monday – 7 Sunday)', 'number'), $f('is_working', 'Working day', 'checkbox'), $f('opens_at', 'School opens', 'time'), $f('closes_at', 'School closes', 'time')],
            'periods' => [$f('name', 'Period name'), $f('code', 'Period code'), $f('starts_at', 'Start time', 'time'), $f('ends_at', 'End time', 'time'), $f('kind', 'Period type', 'select', null, ['teaching', 'break', 'non_teaching'])],
            'timetable' => [$f('academic_session_id', 'Academic session', 'select', 'sessions'), $f('section_id', 'Section', 'select', 'sections'), $f('weekday', 'Weekday (1–7)', 'number'), $f('timetable_period_id', 'Period', 'select', 'periods'), $f('teacher_assignment_id', 'Teaching assignment (blank for break)', 'select', 'assignments')],
            'grading' => [$f('name', 'Scheme name'), $f('ranking_enabled', 'Enable ranking (competition ties: 1, 1, 3)', 'checkbox'), $f('logo_path', 'School logo path', 'text')],
            'exam-types' => [$f('name', 'Type name'), $f('code', 'Type code')],
            'exams' => [$f('name', 'Examination name'), $f('academic_session_id', 'Academic session', 'select', 'sessions'), $f('exam_type_id', 'Exam type', 'select', 'examTypes'), $f('grading_scheme_id', 'Grading scheme', 'select', 'schemes'), $f('status', 'Status', 'select', null, ['draft', 'scheduled', 'completed'])],
            'schedules' => [$f('exam_id', 'Examination', 'select', 'exams'), $f('academic_class_id', 'Class', 'select', 'classes'), $f('section_id', 'Section', 'select', 'sections'), $f('subject_id', 'Subject', 'select', 'subjects'), $f('teacher_id','Invigilator (optional)','select','teachers'), $f('exam_date','Examination date','date'), $f('starts_at','Start time','time'), $f('duration_minutes','Duration in minutes','number'), $f('maximum_marks','Maximum marks','number'), $f('passing_marks','Passing marks','number')],
        };
    }
}

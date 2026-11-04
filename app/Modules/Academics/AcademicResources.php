<?php

namespace App\Modules\Academics;

use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\Teacher;
use App\Modules\Academics\Models\TeacherAssignment;

final class AcademicResources
{
    public const MODELS = ['sessions' => AcademicSession::class, 'classes' => AcademicClass::class, 'sections' => Section::class, 'subjects' => Subject::class, 'class-subjects' => ClassSubject::class, 'teachers' => Teacher::class, 'teacher-assignments' => TeacherAssignment::class, 'students' => Student::class, 'enrollments' => Enrollment::class];

    public const TITLES = ['sessions' => 'Academic Sessions', 'classes' => 'Classes', 'sections' => 'Sections', 'subjects' => 'Subjects', 'class-subjects' => 'Class Subjects', 'teachers' => 'Teacher Directory', 'teacher-assignments' => 'Teacher Assignments', 'students' => 'Student Directory', 'enrollments' => 'Student Enrollments'];

    public static function model(string $resource): string
    {
        return self::MODELS[$resource] ?? abort(404);
    }

    public static function fields(string $resource): array
    {
        $active = ['key' => 'active', 'label' => 'Active', 'type' => 'checkbox'];
        $text = fn ($key, $label, $type = 'text', $options = null) => array_filter(['key' => $key, 'label' => $label, 'type' => $type, 'options' => $options], fn ($v) => $v !== null);
        $select = fn ($key, $label, $source) => ['key' => $key, 'label' => $label, 'type' => 'select', 'source' => $source];

        return match ($resource) {
            'sessions' => [$text('name', 'Session name'), $text('starts_on', 'Start date', 'date'), $text('ends_on', 'End date', 'date'), $active, $text('is_current', 'Current session', 'checkbox')],
            'classes' => [$text('name', 'Class name'), $text('code', 'Class code'), $text('grade_level', 'Grade level', 'number'), $text('display_order', 'Display order', 'number'), $active],
            'sections' => [$select('academic_class_id', 'Class', 'classes'), $text('name', 'Section name'), $text('code', 'Section code'), $text('capacity', 'Capacity (optional)', 'number'), $active],
            'subjects' => [$text('name', 'Subject name'), $text('code', 'Subject code'), $active],
            'class-subjects' => [$select('academic_class_id', 'Class', 'classes'), $select('subject_id', 'Subject', 'subjects'), $text('classification', 'Classification', 'select', ['compulsory', 'elective'])],
            'teachers' => [$text('employee_code', 'Employee code'), $text('full_name', 'Full name'), $text('email', 'Email (optional)', 'email'), $text('phone', 'Phone (optional)', 'tel'), $text('status', 'Employment status', 'select', ['active', 'on_leave', 'inactive'])],
            'teacher-assignments' => [$select('academic_session_id', 'Session', 'sessions'), $select('academic_class_id', 'Class', 'classes'), $select('section_id', 'Section', 'sections'), $select('teacher_id', 'Teacher', 'teachers'), $text('kind', 'Assignment kind', 'select', ['teaching', 'form_teacher']), $select('subject_id', 'Subject (teaching only)', 'subjects')],
            'students' => [$text('full_name', 'Student full name'), $text('date_of_birth', 'Date of birth', 'date'), $text('gender', 'Gender', 'select', ['male', 'female', 'other', 'unspecified']), $text('guardian_name', 'Guardian name'), $text('guardian_phone', 'Guardian phone', 'tel'), $text('admitted_on', 'Admission date', 'date'), $text('status', 'Student status', 'select', ['active', 'inactive', 'graduated', 'archived'])],
            'enrollments' => [$select('student_id', 'Student', 'students'), $select('academic_session_id', 'Session', 'sessions'), $select('academic_class_id', 'Class', 'classes'), $select('section_id', 'Section', 'sections'), $text('started_on', 'Effective date', 'date')],
        };
    }
}

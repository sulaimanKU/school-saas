<?php

namespace App\Modules\Academics;

use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\AcademicRecord;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\Teacher;
use App\Modules\Academics\Models\TeacherAssignment;
use App\Modules\Academics\Operations\Models\AttendanceSession;
use App\Modules\Academics\Operations\Models\ExamMark;
use App\Modules\Academics\Operations\Models\ExamSchedule;
use App\Modules\Academics\Operations\Models\StudentAttendanceRecord;
use App\Modules\Academics\Operations\Models\TimetableEntry;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcademicService
{
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** One school lock serializes session activation, allocation, capacity and assignment writes. */
    public function transaction(callable $operation): mixed
    {
        return DB::transaction(function () use ($operation) {
            School::whereKey(app(TenantContext::class)->school()->id)->lockForUpdate()->firstOrFail();

            return $operation();
        }, 3);
    }

    public function save(string $resource, array $data, ?AcademicRecord $record = null): AcademicRecord
    {
        return $this->transaction(function () use ($resource, $data, $record) {
            if ($resource === 'enrollments') {
                if ($record) {
                    $this->fail('record', 'Enrollment history is immutable. Use promotion, transfer or withdrawal.');
                }

                return $this->enroll($data);
            }
            if ($resource === 'sessions') {
                if ($record) {
                    foreach ([AttendanceSession::class => 'attendance_date', ExamSchedule::class => 'exam_date'] as $model => $dateColumn) {
                        if ($model::where('academic_session_id', $record->id)->where(fn ($q) => $q->where($dateColumn, '<', $data['starts_on'])->orWhere($dateColumn, '>', $data['ends_on']))->exists()) {
                            $this->fail('ends_on', 'These dates would exclude recorded attendance or scheduled examinations.');
                        }
                    }
                }
                if ($data['is_current'] && ! $data['active']) {
                    $this->fail('is_current', 'The current session must be active.');
                }
                $overlap = AcademicSession::where('active', true)->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on']);
                if ($record) {
                    $overlap->whereKeyNot($record->id);
                }
                if ($data['active'] && $overlap->exists()) {
                    $this->fail('starts_on', 'Active academic periods must not overlap.');
                }
                if ($record && $record->enrollments()->where(fn ($q) => $q->where('started_on', '<', $data['starts_on'])->orWhere('started_on', '>', $data['ends_on'])->orWhere('ended_on', '>', $data['ends_on']))->exists()) {
                    $this->fail('starts_on', 'These dates would exclude existing enrollment history.');
                }
                if ($data['is_current']) {
                    AcademicSession::where('is_current', true)->when($record, fn ($q) => $q->whereKeyNot($record->id))->update(['is_current' => false]);
                }
            }
            if ($resource === 'sections') {
                $this->active(AcademicClass::class, $data['academic_class_id'], 'academic_class_id');
                if ($record && ($data['capacity'] ?? null) && $record->enrollments()->where('status', 'active')->select('academic_session_id')->groupBy('academic_session_id')->havingRaw('count(*) > ?', [$data['capacity']])->exists()) {
                    $this->fail('capacity', 'Capacity is below current enrollment.');
                }
            }
            if ($resource === 'class-subjects') {
                $this->active(AcademicClass::class, $data['academic_class_id'], 'academic_class_id');
                $this->active(Subject::class, $data['subject_id'], 'subject_id');
                if (ClassSubject::where('academic_class_id', $data['academic_class_id'])->where('subject_id', $data['subject_id'])->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
                    $this->fail('subject_id', 'Subject is already assigned to this class.');
                }
            }
            if ($resource === 'teacher-assignments') {
                if ($record && TimetableEntry::where('teacher_assignment_id', $record->id)->exists()) {
                    foreach (['teacher_id', 'academic_session_id', 'academic_class_id', 'section_id', 'subject_id', 'kind'] as $key) {
                        if ((string) ($data[$key] ?? '') !== (string) ($record->$key ?? '')) {
                            $this->fail('record', 'Scheduled assignments are locked. Remove timetable entries or create a replacement assignment.');
                        }
                    }
                }
                $this->destination($data);
                $teacher = Teacher::findOrFail($data['teacher_id']);
                if ($teacher->status !== 'active') {
                    $this->fail('teacher_id', 'Select an active teacher.');
                }
                if ($data['kind'] === 'teaching') {
                    $this->active(Subject::class, $data['subject_id'], 'subject_id');
                    if (! ClassSubject::where('academic_class_id', $data['academic_class_id'])->where('subject_id', $data['subject_id'])->exists()) {
                        $this->fail('subject_id', 'Assign the subject to this class first.');
                    }
                } else {
                    $data['subject_id'] = null;
                }
                $conflict = TeacherAssignment::where('academic_session_id', $data['academic_session_id'])->where('section_id', $data['section_id'])->where('kind', $data['kind'])->when($data['kind'] === 'teaching', fn ($q) => $q->where('subject_id', $data['subject_id']))->when($record, fn ($q) => $q->whereKeyNot($record->id));
                if ($conflict->exists()) {
                    $this->fail('teacher_id', 'This section already has that subject teacher or form teacher.');
                }
                if ($data['kind'] === 'form_teacher' && TeacherAssignment::where('academic_session_id', $data['academic_session_id'])->where('teacher_id', $data['teacher_id'])->where('kind', 'form_teacher')->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
                    $this->fail('teacher_id', 'This teacher is already the form teacher of another section in this session.');
                }
            }
            if ($resource === 'students') {
                if ($record && $record->enrollments()->where('started_on', '<', $data['admitted_on'])->exists()) {
                    $this->fail('admitted_on', 'Admission date cannot follow an existing enrollment.');
                }
                if ($record && $data['status'] !== 'active' && $record->enrollments()->where('status', 'active')->exists()) {
                    $this->fail('status', 'Withdraw or complete active enrollments before changing student status.');
                }
                if (! $record) {
                    $school = app(TenantContext::class)->school()->id;
                    DB::table('admission_counters')->insertOrIgnore(['school_id' => $school, 'next_number' => 1]);
                    $counter = DB::table('admission_counters')->where('school_id', $school)->lockForUpdate()->first();
                    $number = $counter->next_number;
                    DB::table('admission_counters')->where('school_id', $school)->update(['next_number' => $number + 1]);
                    $record = new Student;
                    $record->admission_number = 'ADM-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
                }
            }
            $model = $record ?? new (AcademicResources::model($resource));
            $model->fill($data);
            $model->save();

            return $model;
        });
    }

    private function active(string $model, int $id, string $field): AcademicRecord
    {
        $record = $model::findOrFail($id);
        if (! $record->active) {
            $this->fail($field, 'Select an active record.');
        }

        return $record;
    }

    private function destination(array $data): array
    {
        $session = $this->active(AcademicSession::class, $data['academic_session_id'], 'academic_session_id');
        $class = $this->active(AcademicClass::class, $data['academic_class_id'], 'academic_class_id');
        $section = $this->active(Section::class, $data['section_id'], 'section_id');
        if ($section->academic_class_id !== $class->id) {
            $this->fail('section_id', 'Section does not belong to the selected class.');
        }

        return [$session, $class, $section];
    }

    private function enroll(array $data, ?Enrollment $previous = null): Enrollment
    {
        [$session,$class,$section] = $this->destination($data);
        $student = Student::findOrFail($data['student_id']);
        if ($student->status !== 'active') {
            $this->fail('student_id', 'Only active students can be enrolled.');
        }
        if ($data['started_on'] < $session->starts_on || $data['started_on'] > $session->ends_on || $data['started_on'] < $student->admitted_on) {
            $this->fail('started_on', 'Effective date must be within the session and on or after admission.');
        }
        if (Enrollment::where('student_id', $student->id)->where('academic_session_id', $session->id)->where('status', 'active')->exists()) {
            $this->fail('student_id', 'Student already has an active enrollment in this session.');
        }
        if (Enrollment::where('student_id', $student->id)->where('academic_session_id', $session->id)->where('ended_on', '>', $data['started_on'])->exists()) {
            $this->fail('started_on', 'This date overlaps preserved enrollment history.');
        }
        if ($section->capacity && Enrollment::where('academic_session_id', $session->id)->where('section_id', $section->id)->where('status', 'active')->count() >= $section->capacity) {
            $this->fail('section_id', 'The selected section is full.');
        }

        return Enrollment::create([...$data, 'previous_enrollment_id' => $previous?->id, 'status' => 'active', 'ended_on' => null]);
    }

    public function change(Enrollment $enrollment, array $data): ?Enrollment
    {
        return $this->transaction(function () use ($enrollment, $data) {
            $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollment->id);
            if ($enrollment->status !== 'active') {
                $this->fail('operation', 'Only an active enrollment can change.');
            }
            if ($data['started_on'] < $enrollment->started_on || ($data['operation'] !== 'promote' && $data['started_on'] > $enrollment->academicSession->ends_on)) {
                $this->fail('started_on', 'Change date must follow enrollment and remain within its session.');
            }
            $operation = $data['operation'];
            if ($operation !== 'promote') {
                $attendance = StudentAttendanceRecord::where('enrollment_id', $enrollment->id)->where('attendance_date', '>=', $data['started_on'])->exists();
                $marks = ExamMark::where('enrollment_id', $enrollment->id)->whereHas('examSchedule', fn ($q) => $q->where('exam_date', '>=', $data['started_on']))->exists();
                if ($attendance || $marks) {
                    $this->fail('started_on', 'This change would invalidate recorded attendance or examination history. Choose a later effective date.');
                }
            }
            if ($operation === 'transfer' && ($data['academic_session_id'] != $enrollment->academic_session_id || $data['academic_class_id'] != $enrollment->academic_class_id || $data['section_id'] == $enrollment->section_id)) {
                $this->fail('section_id', 'A transfer changes section within the same class and session.');
            }
            if ($operation === 'promote') {
                $session = AcademicSession::findOrFail($data['academic_session_id']);
                $class = AcademicClass::findOrFail($data['academic_class_id']);
                if ($session->starts_on <= $enrollment->academicSession->starts_on || $class->grade_level <= $enrollment->academicClass->grade_level) {
                    $this->fail('academic_session_id', 'Promotion requires a later session and higher grade.');
                }
                // Promotion closes the previous record at its session end, while the new one starts in the next session.
            }
            $ended = $operation === 'promote' ? $enrollment->academicSession->ends_on : $data['started_on'];
            $enrollment->update(['status' => match ($operation) {
                'transfer' => 'transferred','promote' => 'promoted','withdraw' => 'withdrawn'
            }, 'ended_on' => $ended]);
            if ($operation === 'withdraw') {
                return null;
            }

            return $this->enroll(['student_id' => $enrollment->student_id, 'academic_session_id' => $data['academic_session_id'], 'academic_class_id' => $data['academic_class_id'], 'section_id' => $data['section_id'], 'started_on' => $data['started_on']], $enrollment);
        });
    }

    public function delete(AcademicRecord $record): void
    {
        $this->transaction(function () use ($record) {
            if ($record instanceof Enrollment) {
                $this->fail('record', 'Enrollment history cannot be deleted. Use withdrawal.');
            }
            if ($record instanceof Student) {
                if ($record->enrollments()->where('status', 'active')->exists()) {
                    $this->fail('record', 'Withdraw active enrollments before archiving this student.');
                }
                $record->update(['status' => 'archived']);
                $record->delete();

                return;
            }
            $record->delete(); // Composite RESTRICT foreign keys protect all dependent academic records.
        });
    }
}

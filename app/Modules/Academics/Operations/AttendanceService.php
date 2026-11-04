<?php

namespace App\Modules\Academics\Operations;

use App\Modules\Academics\AcademicService;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Teacher;
use App\Modules\Academics\Operations\Models\AttendanceSession;
use App\Modules\Academics\Operations\Models\StaffAttendanceRecord;
use App\Modules\Academics\Operations\Models\StudentAttendanceRecord;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class AttendanceService
{
    public function roster(int $session, int $section, string $date): array
    {
        $period = AcademicSession::findOrFail($session);
        Section::findOrFail($section);
        if ($date < $period->starts_on || $date > $period->ends_on) {
            throw ValidationException::withMessages(['attendance_date' => 'Date must be within the selected academic session.']);
        }
        $records = StudentAttendanceRecord::where('academic_session_id', $session)->where('attendance_date', $date)->get()->keyBy('student_id');

        return app(EnrollmentRoster::class)->query($session, $section, $date)->get()->map(fn ($e) => ['student_id' => $e->student_id, 'enrollment_id' => $e->id, 'name' => $e->student->full_name, 'admission_number' => $e->student->admission_number, 'status' => $records->get($e->student_id)?->status ?? ''])->all();
    }

    public function students(array $data): void
    {
        app(AcademicService::class)->transaction(function () use ($data) {
            $this->notFuture($data['attendance_date']);
            $roster = collect($this->roster($data['academic_session_id'], $data['section_id'], $data['attendance_date']))->keyBy('student_id');
            $session = AttendanceSession::firstOrCreate(['academic_session_id' => $data['academic_session_id'], 'section_id' => $data['section_id'], 'attendance_date' => $data['attendance_date']]);
            foreach ($data['records'] as $row) {
                if (! $roster->has($row['student_id'])) {
                    throw ValidationException::withMessages(['records' => 'Student was not enrolled in this section on the selected date.']);
                }
                if (empty($row['status'])) {
                    continue;
                }
                $enrollment = $roster[$row['student_id']]['enrollment_id'];
                $existing = StudentAttendanceRecord::where('academic_session_id', $data['academic_session_id'])->where('student_id', $row['student_id'])->where('attendance_date', $data['attendance_date'])->first();
                if ($existing && ($existing->enrollment_id !== $enrollment || $existing->attendance_session_id !== $session->id)) {
                    throw ValidationException::withMessages(['records' => 'A historical attendance record already exists for this student on this date.']);
                }
                $before = $existing?->toArray() ?? [];
                $record = StudentAttendanceRecord::updateOrCreate(['academic_session_id' => $data['academic_session_id'], 'student_id' => $row['student_id'], 'attendance_date' => $data['attendance_date']], ['attendance_session_id' => $session->id, 'enrollment_id' => $enrollment, 'status' => $row['status']]);
                app(AuditTrail::class)->record($existing ? 'attendance.corrected' : 'attendance.recorded', 'student_attendance', $record->id, $before, $record->toArray());
            }
        });
    }

    public function staff(array $data): void
    {
        app(AcademicService::class)->transaction(function () use ($data) {
            $this->notFuture($data['attendance_date']);
            foreach ($data['records'] as $row) {
                $teacher = Teacher::findOrFail($row['teacher_id']);
                $existing = StaffAttendanceRecord::where('teacher_id', $teacher->id)->where('attendance_date', $data['attendance_date'])->first();
                if ($teacher->status !== 'active' && ! $existing) {
                    throw ValidationException::withMessages(['records' => 'Only active teachers can be marked without prior attendance history.']);
                }
                if (empty($row['status'])) {
                    continue;
                }
                $record = StaffAttendanceRecord::updateOrCreate(['teacher_id' => $teacher->id, 'attendance_date' => $data['attendance_date']], ['status' => $row['status']]);
                app(AuditTrail::class)->record($existing ? 'staff_attendance.corrected' : 'staff_attendance.recorded', 'staff_attendance', $record->id, $existing?->toArray() ?? [], $record->toArray());
            }
        });
    }

    private function notFuture(string $date): void
    {
        if ($date > CarbonImmutable::now('Asia/Karachi')->toDateString()) {
            throw ValidationException::withMessages(['attendance_date' => 'Future attendance is not permitted.']);
        }
    }

    public static function summary(array $counts): array
    {
        $present = ($counts['present'] ?? 0) + ($counts['late'] ?? 0);
        $denominator = $present + ($counts['absent'] ?? 0);

        return ['counts' => $counts, 'recorded' => array_sum($counts) - ($counts['unmarked'] ?? 0), 'unmarked' => $counts['unmarked'] ?? 0, 'percentage' => $denominator ? round($present / $denominator * 100,2) : null, 'denominator' => $denominator];
    }
}

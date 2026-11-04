<?php

namespace App\Modules\Academics\Operations;

use Illuminate\Support\Facades\DB;

final class OperationsDashboard
{
    public function build(int $school, ?int $session): array
    {
        $date = now('Asia/Karachi')->toDateString();
        $attendance = DB::table('student_attendance_records')->where('school_id', $school)->where('attendance_date', $date)->when($session, fn ($q) => $q->where('academic_session_id', $session))->when(! $session, fn ($q) => $q->whereRaw('1=0'))->select('status')->selectRaw('count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();
        $expected = $session ? DB::table('enrollments')->where('school_id', $school)->where('academic_session_id', $session)->where('started_on', '<=', $date)->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>', $date)->orWhere(fn ($q) => $q->where('ended_on', $date)->whereIn('status', ['promoted', 'completed'])))->distinct()->count('student_id') : 0;
        $staff = DB::table('staff_attendance_records')->where('school_id', $school)->where('attendance_date', $date)->select('status')->selectRaw('count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();
        $classes = DB::table('student_attendance_records as a')->join('attendance_sessions as r', fn ($j) => $j->on('r.id', '=', 'a.attendance_session_id')->on('r.school_id', '=', 'a.school_id'))->join('sections as s', fn ($j) => $j->on('s.id', '=', 'r.section_id')->on('s.school_id', '=', 'a.school_id'))->join('academic_classes as c', fn ($j) => $j->on('c.id', '=', 's.academic_class_id')->on('c.school_id', '=', 'a.school_id'))->where('a.school_id', $school)->where('a.attendance_date', $date)->when($session, fn ($q) => $q->where('a.academic_session_id', $session))->when(! $session, fn ($q) => $q->whereRaw('1=0'))->select('c.name', 'a.status')->selectRaw('count(*) as total')->groupBy('c.name', 'a.status')->get()->groupBy('name')->map(fn ($group, $name) => ['name' => $name, ...AttendanceService::summary($group->pluck('total', 'status')->map(fn ($v) => (int) $v)->all())])->values();
        $upcoming = DB::table('exam_schedules as s')->join('exams as e', fn ($j) => $j->on('e.id', '=', 's.exam_id')->on('e.school_id', '=', 's.school_id'))->join('subjects as u', fn ($j) => $j->on('u.id', '=', 's.subject_id')->on('u.school_id', '=', 's.school_id'))->where('s.school_id', $school)->where('s.exam_date', '>=', $date)->whereIn('e.status', ['scheduled', 'completed'])->when($session, fn ($q) => $q->where('s.academic_session_id', $session))->orderBy('s.exam_date')->limit(5)->get(['e.name', 'u.name as subject', 's.exam_date', 's.starts_at']);
        $query = DB::table('published_report_cards as r')->join('exams as e', fn ($j) => $j->on('e.id', '=', 'r.exam_id')->on('e.school_id', '=', 'r.school_id')->on('e.publication_version', '=', 'r.version'))->where('r.school_id', $school)->when($session, fn ($q) => $q->where('e.academic_session_id', $session));
        $performance = ['pass' => 0, 'fail' => 0, 'exempt' => 0, 'average_percentage' => null];
        $sum = 0;
        $scored = 0;
        foreach ((clone $query)->select('r.id', 'r.snapshot')->orderBy('r.id')->cursor() as $row) {
            $snapshot = json_decode($row->snapshot, true);
            $performance[$snapshot['outcome']]++;
            if ($snapshot['percentage'] !== null) {
                $sum += $snapshot['percentage'];
                $scored++;
            }
        }
        if ($scored) {
            $performance['average_percentage'] = round($sum / $scored, 2);
        }
        $recent = (clone $query)->orderByDesc('r.id')->limit(5)->get(['r.id', 'r.snapshot'])->map(function ($r) {
            $s = json_decode($r->snapshot, true);

            return ['id' => $r->id, 'student' => $s['student_name'], 'exam' => $s['exam_name'], 'version' => $s['version'], 'percentage' => $s['percentage']];
        });

        return ['date' => $date, 'attendance' => [...AttendanceService::summary($attendance), 'eligible' => $expected, 'unmarked' => max(0, $expected - array_sum($attendance))], 'staff' => AttendanceService::summary($staff), 'class_attendance' => $classes, 'upcoming_exams' => $upcoming, 'performance' => $performance, 'published' => $recent, 'activity' => DB::table('academic_audit_logs')->where('school_id',$school)->orderByDesc('id')->limit(8)->get(['operation', 'entity_type', 'created_at'])];
    }
}

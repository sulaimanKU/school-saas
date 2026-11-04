<?php

namespace App\Modules\Academics;

use App\Modules\Academics\Operations\OperationsDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AcademicDashboardData
{
    /** Read model with explicit trusted school predicates; independent of mutable request context. */
    public function build(int $school, ?int $sessionId = null): array
    {
        $sessions = DB::table('academic_sessions')->where('school_id', $school)->orderByDesc('starts_on')->get();
        $session = $sessionId ? $sessions->firstWhere('id', $sessionId) : $sessions->firstWhere('is_current', true);
        if ($sessionId && ! $session) {
            abort(404);
        }
        $students = DB::table('students')->where('school_id', $school)->whereNull('deleted_at')->where('status', 'active');
        if ($session) {
            $students->whereExists(fn ($q) => $q->selectRaw('1')->from('enrollments')->where('school_id', $school)->whereColumn('enrollments.student_id', 'students.id')->where('academic_session_id', $session->id)->where('status', 'active'));
        } else {
            $students->whereRaw('1=0');
        }
        $gender = (clone $students)->select('gender')->selectRaw('count(*) as total')->groupBy('gender')->pluck('total', 'gender');
        $distribution = $session ? DB::table('enrollments as e')->join('students as s', fn ($q) => $q->on('s.id', '=', 'e.student_id')->on('s.school_id', '=', 'e.school_id'))->join('academic_classes as c', fn ($q) => $q->on('c.id', '=', 'e.academic_class_id')->on('c.school_id', '=', 'e.school_id'))->where('e.school_id', $school)->where('e.academic_session_id', $session->id)->where('e.status', 'active')->whereNull('s.deleted_at')->where('s.status', 'active')->select('c.id', 'c.name', 'c.display_order')->selectRaw("count(*) as students, sum(case when s.gender='male' then 1 else 0 end) as boys, sum(case when s.gender='female' then 1 else 0 end) as girls")->groupBy('c.id', 'c.name', 'c.display_order')->orderBy('c.display_order')->get() : collect();
        $today = CarbonImmutable::now('Asia/Karachi');
        $months = [];
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $today->startOfMonth()->subMonths($i);
            $months[] = $month->format('M');
            $end = $month->endOfMonth()->toDateString();
            $trend[] = DB::table('students')->where('school_id', $school)->whereNull('deleted_at')->when($session, fn ($q) => $q->whereExists(fn ($e) => $e->selectRaw('1')->from('enrollments')->where('school_id', $school)->whereColumn('enrollments.student_id', 'students.id')->where('academic_session_id', $session->id)->where('started_on', '<=', $end)->where(fn ($period) => $period->whereNull('ended_on')->orWhere('ended_on', '>=', $end))))->when(! $session, fn ($q) => $q->whereRaw('1=0'))->count();
        }
        $new = DB::table('students')->where('school_id', $school)->whereNull('deleted_at')->whereBetween('admitted_on', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()])->when($session, fn ($q) => $q->whereBetween('admitted_on', [$session->starts_on, $session->ends_on]))->when(! $session, fn ($q) => $q->whereRaw('1=0'))->count();

        return ['source' => 'database', 'session' => $session, 'sessions' => $sessions, 'students' => (clone $students)->count(),
            'classes' => DB::table('academic_classes')->where('school_id', $school)->where('active', true)->count(),
            'teachers' => DB::table('teachers')->where('school_id', $school)->where('status', 'active')->count(),
            'gender' => ['boys' => (int) ($gender['male'] ?? 0), 'girls' => (int) ($gender['female'] ?? 0), 'other' => (int) ($gender['other'] ?? 0) + (int) ($gender['unspecified'] ?? 0)],
            'operations' => app(OperationsDashboard::class)->build($school, $session?->id), 'distribution' => $distribution, 'new_admissions' => $new, 'months' => $months, 'trend' => $trend];
    }
}

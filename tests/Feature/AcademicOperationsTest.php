<?php

namespace Tests\Feature;

use App\Modules\Academics\AcademicDashboardData;
use App\Modules\Academics\AcademicService;
use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\Teacher;
use App\Modules\Academics\Models\TeacherAssignment;
use App\Modules\Academics\Operations\AttendanceService;
use App\Modules\Academics\Operations\AuditTrail;
use App\Modules\Academics\Operations\ExamService;
use App\Modules\Academics\Operations\Models\AcademicAuditLog;
use App\Modules\Academics\Operations\Models\AttendanceSession;
use App\Modules\Academics\Operations\Models\Exam;
use App\Modules\Academics\Operations\Models\ExamMark;
use App\Modules\Academics\Operations\Models\ExamSchedule;
use App\Modules\Academics\Operations\Models\ExamType;
use App\Modules\Academics\Operations\Models\GradingBand;
use App\Modules\Academics\Operations\Models\GradingScheme;
use App\Modules\Academics\Operations\Models\PublishedReportCard;
use App\Modules\Academics\Operations\Models\StaffAttendanceRecord;
use App\Modules\Academics\Operations\Models\StudentAttendanceRecord;
use App\Modules\Academics\Operations\Models\TimetableEntry;
use App\Modules\Academics\Operations\Models\TimetablePeriod;
use App\Modules\Academics\Operations\Models\WorkingDay;
use App\Modules\Academics\Operations\OperationsCatalog;
use App\Modules\Academics\Operations\OperationsService;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Carbon\Carbon;
use Database\Seeders\LocalDemoSeeder;
use Database\Seeders\OperationsDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcademicOperationsTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/preview/schools/demo-maplewood/academic/operations';

    private function local(): void
    {
        $this->app->instance('env', 'local');
        config(['preview.enabled' => true, 'preview.academic_crud' => true]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutVite();
        $this->seed(LocalDemoSeeder::class);
        $this->context('demo-maplewood');
        $this->travelTo(Carbon::parse('2026-11-04 12:00:00', 'Asia/Karachi'));
    }

    private function context(string $slug): void
    {
        app(TenantContext::class)->set(School::where('slug', $slug)->firstOrFail());
    }

    private function setupRoster(): array
    {
        $session = app(AcademicService::class)->save('sessions', ['name' => '2026 Academic', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'active' => true, 'is_current' => true]);
        $class = AcademicClass::factory()->create(['grade_level' => 7]);
        $a = Section::factory()->create(['academic_class_id' => $class->id]);
        $b = Section::factory()->create(['academic_class_id' => $class->id, 'code' => 'B']);
        $subject = Subject::factory()->create();
        ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id, 'classification' => 'compulsory']);
        $teacher = Teacher::factory()->create();
        $assign = TeacherAssignment::create(['academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => $a->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'kind' => 'teaching']);
        $students = Student::factory()->count(2)->create(['admitted_on' => '2026-08-01']);
        $enrollments = [];
        foreach ($students as $student) {
            $enrollments[] = app(AcademicService::class)->save('enrollments', ['academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => $a->id, 'student_id' => $student->id, 'started_on' => '2026-08-01']);
        }

        return compact('session', 'class', 'a', 'b', 'subject', 'teacher', 'assign', 'students', 'enrollments');
    }

    private function exam(array $f): array
    {
        $scheme = app(OperationsService::class)->save('grading', ['name' => 'Test grading', 'ranking_enabled' => true, 'logo_path' => '/images/school-emblem.svg', 'bands' => [['label' => 'F', 'minimum_percent' => 0, 'maximum_percent' => 50, 'is_pass' => false], ['label' => 'Pass', 'minimum_percent' => 50, 'maximum_percent' => 100, 'is_pass' => true]]]);
        $type = ExamType::factory()->create();
        $exam = app(OperationsService::class)->save('exams', ['name' => 'Midterm', 'academic_session_id' => $f['session']->id, 'exam_type_id' => $type->id, 'grading_scheme_id' => $scheme->id, 'status' => 'draft']);
        $payload = ['exam_id' => $exam->id, 'academic_class_id' => $f['class']->id, 'section_id' => $f['a']->id, 'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id, 'exam_date' => '2026-11-03', 'starts_at' => '09:00', 'duration_minutes' => 60, 'maximum_marks' => 100, 'passing_marks' => 50];
        $this->post($this->base.'/schedules', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $schedule = ExamSchedule::latest('id')->firstOrFail();

        return compact('scheme', 'type', 'exam', 'schedule', 'payload');
    }

    private function submitMarks(array $f, array $e, float $score = 80): array
    {
        return ['exam_schedule_id' => $e['schedule']->id, 'records' => $f['students']->map(fn ($s) => ['student_id' => $s->id, 'status' => 'scored', 'obtained_marks' => $score, 'remarks' => 'Good progress.'])->all()];
    }

    public function test_attendance_submission_correction_duplicates_and_unmarked(): void
    {
        $this->local();
        $f = $this->setupRoster();
        $url = $this->base.'/student-attendance';
        $query = '?academic_session_id='.$f['session']->id.'&section_id='.$f['a']->id.'&attendance_date=2026-11-03';
        $this->get($url.$query)->assertInertia(fn (Assert $p) => $p->component('Operations', false)->has('roster', 2)->where('roster.0.status', ''));
        $data = ['academic_session_id' => $f['session']->id, 'section_id' => $f['a']->id, 'attendance_date' => '2026-11-03', 'records' => [['student_id' => $f['students'][0]->id, 'status' => 'present'], ['student_id' => $f['students'][1]->id, 'status' => '']]];
        $this->post($url.'/submit', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, StudentAttendanceRecord::count());
        $data['records'][0]['status'] = 'late';
        $this->post($url.'/submit', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, StudentAttendanceRecord::count());
        $this->assertSame('late', StudentAttendanceRecord::first()->status);
        $this->assertTrue(AcademicAuditLog::where('operation', 'attendance.corrected')->exists());
        $this->postJson($url.'/submit', [...$data, 'records' => [$data['records'][0], $data['records'][0]]])->assertUnprocessable();
        $this->postJson($url.'/submit', [...$data, 'attendance_date' => '2025-01-01'])->assertUnprocessable();
        $this->postJson($url.'/submit', [...$data, 'attendance_date' => '2026-12-01'])->assertUnprocessable();
        $this->assertSame(100.0, AttendanceService::summary(['present' => 1, 'late' => 1, 'excused' => 8])['percentage']);
    }

    public function test_transferred_attendance_preserves_original_enrollment_and_rejects_wrong_roster(): void
    {
        $this->local();
        $f = $this->setupRoster();
        $student = $f['students'][0];
        $data = ['academic_session_id' => $f['session']->id, 'section_id' => $f['a']->id, 'attendance_date' => '2026-10-01', 'records' => [['student_id' => $student->id, 'status' => 'absent']]];
        app(AttendanceService::class)->students($data);
        app(AcademicService::class)->change($f['enrollments'][0], ['operation' => 'transfer', 'academic_session_id' => $f['session']->id, 'academic_class_id' => $f['class']->id, 'section_id' => $f['b']->id, 'started_on' => '2026-10-02']);
        $this->postJson($this->base.'/student-attendance/submit', [...$data, 'attendance_date' => '2026-10-03'])->assertUnprocessable();
        $this->post($this->base.'/student-attendance/submit', [...$data, 'section_id' => $f['b']->id, 'attendance_date' => '2026-10-03'])->assertRedirect()->assertSessionHasNoErrors();
        $old = StudentAttendanceRecord::where('attendance_date', '2026-10-01')->firstOrFail();
        $this->assertSame($f['enrollments'][0]->id, $old->enrollment_id);
        $this->assertCount(1, app(AttendanceService::class)->roster($f['session']->id, $f['b']->id, '2026-10-03'));
        $this->postJson('/preview/schools/demo-maplewood/academic/enrollments/'.$f['enrollments'][1]->id.'/change', ['operation' => 'transfer', 'academic_session_id' => $f['session']->id, 'academic_class_id' => $f['class']->id, 'section_id' => $f['b']->id, 'started_on' => '2026-10-01'])->assertRedirect();
    }

    public function test_staff_attendance_and_monthly_export(): void
    {
        $this->local();
        $teacher = Teacher::factory()->create();
        $data = ['attendance_date' => '2026-11-03', 'records' => [['teacher_id' => $teacher->id, 'status' => 'leave']]];
        $this->post($this->base.'/staff-attendance/submit', $data)->assertRedirect()->assertSessionHasNoErrors();
        $data['records'][0]['status'] = 'present';
        $this->post($this->base.'/staff-attendance/submit', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, StaffAttendanceRecord::count());
        $this->get($this->base.'/reports?report_type=staff&from=2026-11-01&to=2026-11-04')->assertInertia(fn (Assert $p) => $p->component('Operations', false)->where('reports.total', 1));
        $response = $this->get($this->base.'/reports/export?report_type=staff&from=2026-11-01&to=2026-11-04');
        $response->assertOk()->assertDownload('academic-report.csv');
        $this->assertStringContainsString($teacher->employee_code, $response->streamedContent());
    }

    public function test_timetable_detects_real_time_conflicts_breaks_and_working_hours(): void
    {
        $this->local();
        $f = $this->setupRoster();
        $ops = app(OperationsService::class);
        $ops->save('working-days', ['weekday' => 1, 'is_working' => true, 'opens_at' => '08:00', 'closes_at' => '15:00']);
        $p = $ops->save('periods', ['name' => 'First', 'code' => 'P1', 'starts_at' => '08:00', 'ends_at' => '09:00', 'kind' => 'teaching']);
        $over = $ops->save('periods', ['name' => 'Overlap', 'code' => 'P2', 'starts_at' => '08:30', 'ends_at' => '09:30', 'kind' => 'teaching']);
        $data = ['academic_session_id' => $f['session']->id, 'section_id' => $f['a']->id, 'timetable_period_id' => $p->id, 'teacher_assignment_id' => $f['assign']->id, 'weekday' => 1];
        $this->post($this->base.'/timetable', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson($this->base.'/timetable', [...$data, 'timetable_period_id' => $over->id])->assertUnprocessable();
        $other = TeacherAssignment::create(['academic_session_id' => $f['session']->id, 'academic_class_id' => $f['class']->id, 'section_id' => $f['b']->id, 'teacher_id' => $f['teacher']->id, 'subject_id' => $f['subject']->id, 'kind' => 'teaching']);
        $this->postJson($this->base.'/timetable', [...$data, 'section_id' => $f['b']->id, 'teacher_assignment_id' => $other->id, 'timetable_period_id' => $over->id])->assertUnprocessable();
        $this->postJson($this->base.'/timetable', [...$data, 'weekday' => 7])->assertUnprocessable();
        $break = $ops->save('periods', ['name' => 'Break', 'code' => 'B', 'starts_at' => '09:30', 'ends_at' => '09:45', 'kind' => 'break']);
        $this->post($this->base.'/timetable', [...$data, 'timetable_period_id' => $break->id, 'teacher_assignment_id' => null])->assertRedirect()->assertSessionHasNoErrors();
        $this->putJson($this->base.'/periods/'.$p->id, ['name' => 'First', 'code' => 'P1', 'starts_at' => '09:00', 'ends_at' => '10:00', 'kind' => 'teaching'])->assertUnprocessable();
        $this->assertSame(2, TimetableEntry::count());
        $this->assertTrue(AcademicAuditLog::where('operation', 'timetable.created')->exists());
    }

    public function test_exam_scheduling_marks_validation_and_no_fabricated_missing_marks(): void
    {
        $this->local();
        $f = $this->setupRoster();
        $e = $this->exam($f);
        $this->postJson($this->base.'/schedules', $e['payload'])->assertUnprocessable();
        $invalid = $this->submitMarks($f, $e, 101);
        $this->postJson($this->base.'/marks/submit', $invalid)->assertUnprocessable();
        $this->assertSame(0, ExamMark::count());
        $data = $this->submitMarks($f, $e);
        $data['records'][1]['status'] = 'absent';
        $data['records'][1]['obtained_marks'] = null;
        $this->post($this->base.'/marks/submit', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, ExamMark::count());
        $reports = app(ExamService::class)->reports($e['exam']);
        $this->assertSame('pass', $reports[0]['outcome']);
        $this->assertSame('fail', $reports[1]['outcome']);
        $data['records'][0]['obtained_marks'] = 90;
        $this->post($this->base.'/marks/submit', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, ExamMark::count());
        $this->assertTrue(AcademicAuditLog::where('operation', 'marks.corrected')->exists());
    }

    public function test_publication_snapshots_corrections_and_grading_changes(): void
    {
        $this->local();
        $f = $this->setupRoster();
        $e = $this->exam($f);
        $exam = $e['exam'];
        $exam->update(['status' => 'completed']);
        $this->postJson($this->base.'/results/'.$exam->id.'/publish', ['confirm' => true])->assertUnprocessable();
        $data = $this->submitMarks($f, $e);
        $this->post($this->base.'/marks/submit', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->get($this->base.'/results/'.$exam->id.'/students/'.$f['students'][0]->id)->assertInertia(fn (Assert $p) => $p->component('ReportCard', false)->where('snapshot.publication', 'draft'));
        $this->post($this->base.'/results/'.$exam->id.'/publish', ['confirm' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, PublishedReportCard::count());
        $card = PublishedReportCard::firstOrFail();
        $original = $card->snapshot;
        $this->postJson($this->base.'/marks/submit', $data)->assertUnprocessable();
        $bands = [['label' => 'Changed F', 'minimum_percent' => 0, 'maximum_percent' => 90, 'is_pass' => false], ['label' => 'Changed pass', 'minimum_percent' => 90, 'maximum_percent' => 100, 'is_pass' => true]];
        app(OperationsService::class)->save('grading', ['name' => $e['scheme']->name, 'ranking_enabled' => false, 'logo_path' => '/images/school-emblem.svg', 'bands' => $bands], $e['scheme']);
        $this->assertSame($original, $card->fresh()->snapshot);
        $this->get($this->base.'/report-cards/'.$card->id)->assertInertia(fn (Assert $p) => $p->component('ReportCard', false)->where('snapshot.grade', 'Pass')->where('snapshot.version', 1));
        $this->postJson($this->base.'/results/'.$exam->id.'/reopen', ['confirm' => true, 'reason' => 'short'])->assertUnprocessable();
        $this->post($this->base.'/results/'.$exam->id.'/reopen', ['confirm' => true, 'reason' => 'Correcting a documented local entry mistake.'])->assertRedirect()->assertSessionHasNoErrors();
        $data['records'][0]['obtained_marks'] = 95;
        $this->post($this->base.'/marks/submit', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($this->base.'/results/'.$exam->id.'/publish', ['confirm' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(4, PublishedReportCard::count());
        $this->assertSame($original, $card->fresh()->snapshot);
        $this->assertSame(2, $exam->fresh()->publication_version);
        $this->assertTrue(AcademicAuditLog::where('operation', 'results.reopened')->exists());
    }

    public function test_operations_routes_are_local_only_and_tenant_scoped(): void
    {
        $this->local();
        $f = $this->setupRoster();
        $e = $this->exam($f);
        $id = $e['exam']->id;
        $this->context('demo-crescent');
        $other = ExamType::factory()->create();
        $this->context('demo-maplewood');
        $this->putJson($this->base.'/exam-types/'.$other->id, ['name' => 'Attack', 'code' => 'A'])->assertNotFound();
        $this->deleteJson($this->base.'/exam-types/'.$other->id)->assertNotFound();
        $this->postJson($this->base.'/exams', ['name' => 'Bad', 'academic_session_id' => $f['session']->id, 'exam_type_id' => $other->id, 'grading_scheme_id' => $e['scheme']->id, 'status' => 'draft'])->assertUnprocessable();
        $this->postJson($this->base.'/staff-attendance/submit', ['school_id' => 999, 'attendance_date' => '2026-11-03', 'records' => [['teacher_id' => $f['teacher']->id, 'status' => 'present']]])->assertUnprocessable();
        foreach (['production', 'staging', 'testing'] as $env) {
            $this->app->instance('env', $env);
            foreach (array_keys(OperationsCatalog::TITLES) as $page) {
                if ($page !== 'report-card') {
                    $this->get($this->base.'/'.$page)->assertNotFound();
                }
            }
            $this->postJson($this->base.'/marks/submit', [])->assertNotFound();
            $this->postJson($this->base.'/results/'.$id.'/publish', ['confirm' => true])->assertNotFound();
            $this->putJson($this->base.'/exams/'.$id, [])->assertNotFound();
            $this->deleteJson($this->base.'/exams/'.$id)->assertNotFound();
            $this->get($this->base.'/reports/export')->assertNotFound();
        }
        $this->app->instance('env', 'local');
        config(['preview.academic_crud' => false]);
        $this->get($this->base.'/marks')->assertNotFound();
    }

    public function test_operations_pages_seeding_audit_and_dashboard_accuracy(): void
    {
        $this->local();
        $f = $this->setupRoster();
        $this->seed(OperationsDemoSeeder::class);
        $this->seed(OperationsDemoSeeder::class);
        $this->context('demo-maplewood');
        $this->assertSame(7, WorkingDay::count());
        $this->assertSame(4, TimetablePeriod::count());
        $this->assertSame(0, ExamMark::count());
        foreach (array_keys(OperationsCatalog::TITLES) as $page) {
            if ($page !== 'report-card') {
                $this->get($this->base.'/'.$page)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Operations', false));
            }
        }
        app(AttendanceService::class)->students(['academic_session_id' => $f['session']->id, 'section_id' => $f['a']->id, 'attendance_date' => '2026-11-04', 'records' => [['student_id' => $f['students'][0]->id, 'status' => 'present']]]);
        $dashboard = app(AcademicDashboardData::class)->build(app(TenantContext::class)->school()->id);
        $this->assertSame(1, $dashboard['operations']['attendance']['recorded']);
        $this->assertSame(1, $dashboard['operations']['attendance']['unmarked']);
        $this->assertSame(100.0, $dashboard['operations']['attendance']['percentage']);
        $audit = AcademicAuditLog::where('operation', 'attendance.recorded')->firstOrFail();
        $this->assertFalse($audit->actor_context['authenticated']);
        $this->assertStringNotContainsString('guardian', json_encode($audit->changes));
    }

    public function test_postgresql_marks_and_snapshot_guards_reject_raw_updates(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL trigger guards.');
        }
        $this->local();
        $f = $this->setupRoster();
        $e = $this->exam($f);
        app(ExamService::class)->marks($this->submitMarks($f, $e));
        $e['exam']->update(['status' => 'completed']);
        app(ExamService::class)->publish($e['exam']);
        $this->expectException(QueryException::class);
        DB::table('published_report_cards')->where('school_id', app(TenantContext::class)->school()->id)->update(['snapshot' => '{}']);
    }

    public static function rawGuards(): array
    {
        return [['marks-overflow'], ['published-marks'], ['invalid-attendance'], ['timetable-conflict'], ['immutable-audit']];
    }

    #[DataProvider('rawGuards')]
    public function test_postgresql_guards_protect_direct_database_writes(string $case): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL guards.');
        }
        $this->local();
        $f = $this->setupRoster();
        $school = app(TenantContext::class)->school()->id;
        if (in_array($case, ['marks-overflow', 'published-marks'])) {
            $e = $this->exam($f);
            app(ExamService::class)->marks($this->submitMarks($f, $e));
            if ($case === 'published-marks') {
                $e['exam']->update(['status' => 'completed']);
                app(ExamService::class)->publish($e['exam']);
            }
            $this->expectException(QueryException::class);
            DB::table('exam_marks')->where('school_id', $school)->update(['obtained_marks' => $case === 'marks-overflow' ? 101 : 70]);
        } elseif ($case === 'invalid-attendance') {
            $a = AttendanceSession::create(['academic_session_id' => $f['session']->id, 'section_id' => $f['b']->id, 'attendance_date' => '2026-11-03']);
            $this->expectException(QueryException::class);
            DB::table('student_attendance_records')->insert(['school_id' => $school, 'academic_session_id' => $f['session']->id, 'attendance_session_id' => $a->id, 'enrollment_id' => $f['enrollments'][0]->id, 'student_id' => $f['students'][0]->id, 'attendance_date' => '2026-11-03', 'status' => 'present']);
        } elseif ($case === 'timetable-conflict') {
            $ops = app(OperationsService::class);
            $ops->save('working-days', ['weekday' => 1, 'is_working' => true, 'opens_at' => '08:00', 'closes_at' => '15:00']);
            $p = $ops->save('periods', ['name' => 'P', 'code' => 'P', 'starts_at' => '08:00', 'ends_at' => '09:00', 'kind' => 'teaching']);
            $other = $ops->save('periods', ['name' => 'Other', 'code' => 'O', 'starts_at' => '08:30', 'ends_at' => '09:30', 'kind' => 'teaching']);
            $data = ['academic_session_id' => $f['session']->id, 'section_id' => $f['a']->id, 'teacher_assignment_id' => $f['assign']->id, 'timetable_period_id' => $p->id, 'weekday' => 1];
            $ops->save('timetable', $data);
            $this->expectException(QueryException::class);
            DB::table('timetable_entries')->insert(['school_id' => $school, ...$data, 'timetable_period_id' => $other->id]);
        } else {
            app(AuditTrail::class)->record('test.created', 'test', 1, [], ['status' => 'test']);
            $this->expectException(QueryException::class);
            DB::table('academic_audit_logs')->where('school_id', $school)->update(['operation' => 'tampered']);
        }
    }

    public function test_cross_school_attendance_marks_reports_and_all_operation_models(): void
    {
        $this->local();
        $f = $this->setupRoster();
        $e = $this->exam($f);
        app(ExamService::class)->marks($this->submitMarks($f, $e));
        $e['exam']->update(['status' => 'completed']);
        app(ExamService::class)->publish($e['exam']);
        $card = PublishedReportCard::firstOrFail();
        $this->context('demo-crescent');
        $other = Student::factory()->create();
        foreach ([Exam::class, ExamSchedule::class, ExamMark::class, GradingScheme::class, GradingBand::class, PublishedReportCard::class, AcademicAuditLog::class] as $model) {
            $this->assertSame(0, $model::count());
        }
        $otherBase = str_replace('demo-maplewood', 'demo-crescent', $this->base);
        $this->get($otherBase.'/report-cards/'.$card->id)->assertNotFound();
        $this->postJson($otherBase.'/marks/submit', $this->submitMarks($f, $e))->assertUnprocessable();
        $this->get($otherBase.'/reports?exam_id='.$e['exam']->id)->assertSessionHasErrors('exam_id');
        $this->context('demo-maplewood');
        $this->postJson($this->base.'/student-attendance/submit', ['academic_session_id' => $f['session']->id, 'section_id' => $f['a']->id, 'attendance_date' => '2026-11-03', 'records' => [['student_id' => $other->id, 'status' => 'present']]])->assertUnprocessable();
        $this->get($this->base.'/reports?report_type=performance')->assertInertia(fn (Assert $p) => $p->component('Operations', false)->where('reports.total', 2)->where('reports.pass_rate', 100));
    }

    public function test_marks_bulk_validation_is_atomic_and_exemption_excludes_denominator(): void
    {
        $this->local();
        $f = $this->setupRoster();
        $e = $this->exam($f);
        $data = $this->submitMarks($f, $e);
        $data['records'][1]['obtained_marks'] = 101;
        $this->postJson($this->base.'/marks/submit', $data)->assertUnprocessable();
        $this->assertSame(0, ExamMark::count());
        $data = $this->submitMarks($f, $e);
        $data['records'][1]['status'] = 'exempt';
        $data['records'][1]['obtained_marks'] = null;
        $this->post($this->base.'/marks/submit', $data)->assertRedirect()->assertSessionHasNoErrors();
        $reports = app(ExamService::class)->reports($e['exam']);
        $exempt = collect($reports)->firstWhere('student_id', $f['students'][1]->id);
        $this->assertSame('exempt', $exempt['outcome']);
        $this->assertNull($exempt['rank']);
    }

    public function test_daily_report_exports_unmarked_separately_and_neutralizes_csv_formulas(): void
    {
        $this->local();
        $f = $this->setupRoster();
        $f['students'][0]->update(['full_name' => '=Formula Test']);
        app(AttendanceService::class)->students(['academic_session_id' => $f['session']->id, 'section_id' => $f['a']->id, 'attendance_date' => '2026-11-03', 'records' => [['student_id' => $f['students'][0]->id, 'status' => 'present']]]);
        $query = '?report_type=daily&academic_session_id='.$f['session']->id.'&section_id='.$f['a']->id.'&from=2026-11-03&to=2026-11-03';
        $this->get($this->base.'/reports'.$query)->assertInertia(fn (Assert $p) => $p->component('Operations', false)->where('reports.total', 2)->where('reports.summary.recorded', 1)->where('reports.summary.unmarked', 1)->missing('reports.summary.counts.absent'));
        $response = $this->get($this->base.'/reports/export'.$query);
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Formula Test", $csv);
        $this->assertStringContainsString('unmarked', $csv);
        $this->assertSame(1, StudentAttendanceRecord::count());
    }

    public function test_session_edits_and_backdated_transfers_cannot_invalidate_attendance(): void
    {
        $this->local();
        $f = $this->setupRoster();
        app(AttendanceService::class)->students(['academic_session_id' => $f['session']->id, 'section_id' => $f['a']->id, 'attendance_date' => '2026-11-03', 'records' => [['student_id' => $f['students'][0]->id, 'status' => 'present']]]);
        $this->putJson('/preview/schools/demo-maplewood/academic/sessions/'.$f['session']->id, ['name' => $f['session']->name, 'starts_on' => '2026-08-01', 'ends_on' => '2026-10-31', 'active' => true, 'is_current' => true])->assertUnprocessable()->assertJsonValidationErrors('ends_on');
        $this->postJson('/preview/schools/demo-maplewood/academic/enrollments/'.$f['enrollments'][0]->id.'/change', ['operation' => 'transfer', 'academic_session_id' => $f['session']->id, 'academic_class_id' => $f['class']->id, 'section_id' => $f['b']->id, 'started_on' => '2026-11-03'])->assertUnprocessable()->assertJsonValidationErrors('started_on');
        $this->assertSame('active', $f['enrollments'][0]->fresh()->status);
        $this->assertSame('2027-07-31', $f['session']->fresh()->ends_on);
    }
}

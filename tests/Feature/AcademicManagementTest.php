<?php

namespace Tests\Feature;

use App\Modules\Academics\AcademicDashboardData;
use App\Modules\Academics\AcademicResources;
use App\Modules\Academics\AcademicService;
use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\Teacher;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Carbon\Carbon;
use Database\Seeders\AcademicDemoSeeder;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademicManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/preview/schools/demo-maplewood/academic';

    private function local(): void
    {
        $this->app->instance('env', 'local');
        config(['preview.enabled' => true, 'preview.academic_crud' => true]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutVite();
        $this->seed(LocalDemoSeeder::class);
        $this->context('demo-maplewood');
    }

    private function context(string $slug): void
    {
        app(TenantContext::class)->set(School::where('slug', $slug)->firstOrFail());
    }

    private function create(string $resource, array $data)
    {
        return app(AcademicService::class)->save($resource, $data);
    }

    private function fixtures(): array
    {
        $session = $this->create('sessions', ['name' => '2026–2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'active' => true, 'is_current' => true]);
        $class = AcademicClass::factory()->create(['grade_level' => 7]);
        $a = Section::factory()->create(['academic_class_id' => $class->id]);
        $b = Section::factory()->create(['academic_class_id' => $class->id, 'code' => 'B']);
        $student = $this->create('students', $this->admission());

        return [$session, $class, $a, $b, $student];
    }

    private function admission(): array
    {
        return ['full_name' => 'Academic Test Student', 'date_of_birth' => '2013-02-10', 'gender' => 'female', 'guardian_name' => 'Test Guardian', 'guardian_phone' => '03000000000', 'admitted_on' => '2026-08-01', 'status' => 'active'];
    }

    private function enrollment($session, $class, $section, $student): array
    {
        return ['student_id' => $student->id, 'academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => $section->id, 'started_on' => '2026-08-01'];
    }

    public function test_sessions_overlap_current_activation_and_dependency_protection(): void
    {
        $this->local();
        [$session,$class,$a,$b,$student] = $this->fixtures();
        $this->postJson($this->base.'/sessions', ['name' => 'Overlap', 'starts_on' => '2027-01-01', 'ends_on' => '2028-01-01', 'active' => true, 'is_current' => false])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->post($this->base.'/sessions', ['name' => '2027–2028', 'starts_on' => '2027-08-01', 'ends_on' => '2028-07-31', 'active' => true, 'is_current' => true])->assertRedirect();
        $this->assertFalse($session->fresh()->is_current);
        $this->assertSame(1, AcademicSession::where('is_current', true)->count());
        $this->create('enrollments', $this->enrollment($session, $class, $a, $student));
        $this->deleteJson($this->base.'/sessions/'.$session->id)->assertUnprocessable();
        $this->putJson($this->base.'/sessions/'.$session->id, ['name' => $session->name, 'starts_on' => $session->starts_on, 'ends_on' => $session->ends_on, 'active' => false, 'is_current' => true])->assertUnprocessable()->assertJsonValidationErrors('is_current');
    }

    public function test_classes_sections_subjects_and_teacher_assignment_crud(): void
    {
        $this->local();
        [$session,$class,$a] = $this->fixtures();
        $this->post($this->base.'/classes', ['name' => 'Grade Nine', 'code' => 'g9', 'grade_level' => 9, 'display_order' => 9, 'active' => true])->assertRedirect();
        $nine = AcademicClass::where('code', 'G9')->firstOrFail();
        $this->put($this->base.'/classes/'.$nine->id, ['name' => 'Grade 9', 'code' => 'G9', 'grade_level' => 9, 'display_order' => 10, 'active' => false])->assertRedirect();
        $this->assertFalse($nine->fresh()->active);
        $this->delete($this->base.'/classes/'.$nine->id)->assertRedirect();
        $this->post($this->base.'/sections', ['academic_class_id' => $class->id, 'name' => 'Section C', 'code' => 'C', 'capacity' => 25, 'active' => true])->assertRedirect();
        $this->postJson($this->base.'/sections', ['academic_class_id' => $class->id, 'name' => 'Duplicate', 'code' => 'A', 'capacity' => 25, 'active' => true])->assertUnprocessable();
        $subject = Subject::factory()->create();
        $teacher = Teacher::factory()->create();
        $assignment = ['academic_class_id' => $class->id, 'subject_id' => $subject->id, 'classification' => 'elective'];
        $this->post($this->base.'/class-subjects', $assignment)->assertRedirect();
        $this->postJson($this->base.'/class-subjects', $assignment)->assertUnprocessable();
        $teaching = ['academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => $a->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'kind' => 'teaching'];
        $this->post($this->base.'/teacher-assignments', $teaching)->assertRedirect();
        $this->postJson($this->base.'/teacher-assignments', $teaching)->assertUnprocessable();
        $this->post($this->base.'/teacher-assignments', [...$teaching, 'kind' => 'form_teacher', 'subject_id' => null])->assertRedirect();
        $this->postJson($this->base.'/teacher-assignments', [...$teaching, 'kind' => 'form_teacher', 'subject_id' => null])->assertUnprocessable();
        $this->deleteJson($this->base.'/subjects/'.$subject->id)->assertUnprocessable();
        $this->get($this->base.'/teachers/'.$teacher->id)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Academic', false)->has('assignments', 2));
    }

    public function test_admission_allocates_unique_school_scoped_numbers_and_archives(): void
    {
        $this->local();
        for ($i = 0; $i < 4; $i++) {
            $this->post($this->base.'/students', [...$this->admission(), 'full_name' => 'Student '.$i])->assertRedirect();
        }
        $this->assertSame(4, Student::distinct()->count('admission_number'));
        $student = Student::firstOrFail();
        $this->assertSame('ADM-000001', $student->admission_number);
        $this->postJson($this->base.'/students', [...$this->admission(), 'school_id' => 999])->assertUnprocessable()->assertJsonValidationErrors('school_id');
        $this->postJson($this->base.'/students', [...$this->admission(), 'admission_number' => 'MANUAL'])->assertUnprocessable();
        $this->postJson($this->base.'/students', [...$this->admission(), 'date_of_birth' => '2030-01-01'])->assertUnprocessable();
        $this->put($this->base.'/students/'.$student->id, [...$this->admission(), 'full_name' => 'Updated student'])->assertRedirect();
        $this->delete($this->base.'/students/'.$student->id)->assertRedirect();
        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->get($this->base.'/students/'.$student->id)->assertOk();
        $this->get($this->base.'/students?status=archived')->assertInertia(fn (Assert $p) => $p->component('Academic', false)->where('rows.total', 1));
        $this->context('demo-crescent');
        $other = $this->create('students', $this->admission());
        $this->assertSame('ADM-000001', $other->admission_number);
    }

    public function test_transfer_and_promotion_preserve_history_and_rollback_failures(): void
    {
        $this->local();
        [$session,$class,$a,$b,$student] = $this->fixtures();
        $this->post($this->base.'/enrollments', $this->enrollment($session, $class, $a, $student))->assertRedirect();
        $enrollment = Enrollment::firstOrFail();
        $this->postJson($this->base.'/enrollments', $this->enrollment($session, $class, $a, $student))->assertUnprocessable();
        $this->deleteJson($this->base.'/students/'.$student->id)->assertUnprocessable();
        $transfer = ['operation' => 'transfer', 'academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => $b->id, 'started_on' => '2026-09-01'];
        $this->post($this->base.'/enrollments/'.$enrollment->id.'/change', $transfer)->assertRedirect();
        $this->assertSame('transferred', $enrollment->fresh()->status);
        $next = Enrollment::where('status', 'active')->firstOrFail();
        $this->assertSame($enrollment->id, $next->previous_enrollment_id);
        $this->postJson($this->base.'/enrollments/'.$next->id.'/change', $transfer)->assertUnprocessable();
        $this->assertSame('active', $next->fresh()->status);
        $target = $this->create('sessions', ['name' => 'Next year', 'starts_on' => '2027-08-01', 'ends_on' => '2028-07-31', 'active' => true, 'is_current' => false]);
        $grade = AcademicClass::factory()->create(['grade_level' => 8]);
        $section = Section::factory()->create(['academic_class_id' => $grade->id]);
        $promote = ['operation' => 'promote', 'academic_session_id' => $target->id, 'academic_class_id' => $grade->id, 'section_id' => $section->id, 'started_on' => '2027-08-01'];
        $this->post($this->base.'/enrollments/'.$next->id.'/change', $promote)->assertRedirect();
        $this->assertSame('promoted', $next->fresh()->status);
        $this->assertCount(3, $student->enrollments);
        $this->deleteJson($this->base.'/classes/'.$class->id)->assertUnprocessable();
        $this->deleteJson($this->base.'/enrollments/'.$enrollment->id)->assertUnprocessable();
    }

    public function test_tenant_scope_bindings_validation_and_cross_school_writes(): void
    {
        $this->local();
        [$session,$class,$a,$b,$student] = $this->fixtures();
        $aSchool = app(TenantContext::class)->school()->id;
        $this->context('demo-crescent');
        $other = AcademicClass::factory()->create();
        $otherStudent = $this->create('students', $this->admission());
        $this->assertNull(AcademicClass::find($class->id));
        $this->assertCount(1, AcademicClass::all());
        foreach (['classes' => $other, 'students' => $otherStudent] as $resource => $record) {
            $url = $this->base.'/'.$resource.'/'.$record->id;
            $this->get($url)->assertNotFound();
            $this->get($url.'/edit')->assertNotFound();
            $data = $resource === 'classes' ? ['name' => 'Attack', 'code' => 'ATTACK', 'grade_level' => 8, 'display_order' => 1, 'active' => true] : $this->admission();
            $this->putJson($url, $data)->assertNotFound();
            $this->deleteJson($url)->assertNotFound();
        }
        $this->postJson($this->base.'/sections', ['name' => 'Bad', 'code' => 'BAD', 'academic_class_id' => $other->id, 'capacity' => 20, 'active' => true])->assertUnprocessable()->assertJsonValidationErrors('academic_class_id');
        $this->postJson($this->base.'/enrollments', [...$this->enrollment($session, $class, $a, $student), 'student_id' => $otherStudent->id])->assertUnprocessable();
        $this->assertDatabaseMissing('academic_classes', ['school_id' => $aSchool, 'code' => 'ATTACK']);
        $this->context('demo-crescent');
        $this->assertNotNull(AcademicClass::find($other->id));
    }

    public function test_local_only_academic_routes_reject_every_non_local_environment_and_disabled_flags(): void
    {
        $this->local();
        foreach (['production', 'staging', 'testing'] as $env) {
            $this->app->instance('env', $env);
            foreach (array_keys(AcademicResources::MODELS) as $resource) {
                $this->get($this->base.'/'.$resource)->assertNotFound();
                $this->postJson($this->base.'/'.$resource, [])->assertNotFound();
                $this->putJson($this->base.'/'.$resource.'/1', [])->assertNotFound();
                $this->deleteJson($this->base.'/'.$resource.'/1')->assertNotFound();
            }
        }
        $this->app->instance('env', 'local');
        config(['preview.academic_crud' => false]);
        $this->get($this->base.'/students')->assertNotFound();
        config(['preview.academic_crud' => true, 'preview.enabled' => false]);
        $this->get($this->base.'/students')->assertNotFound();
    }

    public function test_capacity_and_section_class_consistency(): void
    {
        $this->local();
        [$session,$class,$a,$b,$student] = $this->fixtures();
        $a->update(['capacity' => 1]);
        $this->create('enrollments', $this->enrollment($session, $class, $a, $student));
        $another = $this->create('students', $this->admission());
        $this->postJson($this->base.'/enrollments', $this->enrollment($session, $class, $a, $another))->assertUnprocessable()->assertJsonValidationErrors('section_id');
        $other = AcademicClass::factory()->create();
        $this->postJson($this->base.'/enrollments', $this->enrollment($session, $other, $b, $another))->assertUnprocessable()->assertJsonValidationErrors('section_id');
    }

    public function test_live_dashboard_metrics_filter_school_and_session(): void
    {
        $this->local();
        [$session,$class,$a,$b,$student] = $this->fixtures();
        $this->create('enrollments', $this->enrollment($session, $class, $a, $student));
        Teacher::factory()->create();
        $this->context('demo-crescent');
        Student::factory()->count(3)->create();
        $school = School::where('slug', 'demo-maplewood')->firstOrFail();
        $data = app(AcademicDashboardData::class)->build($school->id);
        $this->assertSame(1, $data['students']);
        $this->assertSame(1, $data['classes']);
        $this->assertSame(1, $data['teachers']);
        $this->assertSame(1, $data['gender']['girls']);
        $this->assertEquals(1, $data['distribution']->sum('students'));
        $this->get('/preview/schools/demo-maplewood/dashboard')->assertInertia(fn (Assert $p) => $p->component('Preview', false)->where('liveAcademic.students', 1));
        $this->get('/preview/schools/demo-maplewood/dashboard?session_id=999999')->assertNotFound();
    }

    public function test_academic_seeder_is_repeatable_and_directories_paginate(): void
    {
        $this->local();
        $this->seed(AcademicDemoSeeder::class);
        $this->seed(AcademicDemoSeeder::class);
        $this->context('demo-maplewood');
        $this->assertSame(12, Student::count());
        $this->assertSame(12, Enrollment::count());
        Student::factory()->count(8)->create();
        $this->get($this->base.'/students')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Academic', false)->has('rows.data', 15)->where('rows.total', 20));
        $this->get($this->base.'/students?q=Demo%20Academic%20Student%2001')->assertInertia(fn (Assert $p) => $p->component('Academic', false)->where('rows.total', 1));
        foreach (array_keys(AcademicResources::MODELS) as $resource) {
            $this->get($this->base.'/'.$resource)->assertOk();
            $this->get($this->base.'/'.$resource.'/create')->assertOk();
        }
    }

    public function test_database_composite_foreign_keys_reject_cross_school_relationships(): void
    {
        $this->local();
        $class = AcademicClass::factory()->create();
        $school = app(TenantContext::class)->school()->id;
        $this->context('demo-crescent');
        $this->expectException(QueryException::class);
        DB::table('sections')->insert(['school_id' => app(TenantContext::class)->school()->id, 'academic_class_id' => $class->id, 'name' => 'Bad', 'code' => 'BAD', 'active' => true]);
    }

    public function test_failed_transfer_rolls_back_history_when_destination_is_full(): void
    {
        $this->local();
        [$session,$class,$a,$b,$student] = $this->fixtures();
        $b->update(['capacity' => 1]);
        $other = $this->create('students', $this->admission());
        $this->create('enrollments', $this->enrollment($session, $class, $b, $other));
        $source = $this->create('enrollments', $this->enrollment($session, $class, $a, $student));
        $this->postJson($this->base.'/enrollments/'.$source->id.'/change', ['operation' => 'transfer', 'academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => $b->id, 'started_on' => '2026-09-01'])->assertUnprocessable()->assertJsonValidationErrors('section_id');
        $this->assertSame('active', $source->fresh()->status);
        $this->assertNull($source->fresh()->ended_on);
        $this->assertSame(2, Enrollment::count());
    }

    public function test_form_teacher_cannot_be_assigned_to_two_sections_in_one_session(): void
    {
        $this->local();
        [$session,$class,$a,$b] = $this->fixtures();
        $teacher = Teacher::factory()->create();
        $data = ['academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => $a->id, 'teacher_id' => $teacher->id, 'kind' => 'form_teacher', 'subject_id' => null];
        $this->post($this->base.'/teacher-assignments', $data)->assertRedirect();
        $this->postJson($this->base.'/teacher-assignments', [...$data, 'section_id' => $b->id])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
    }

    public function test_every_academic_model_requires_context_and_excludes_other_schools(): void
    {
        $this->local();
        $this->context('demo-crescent');
        foreach (AcademicResources::MODELS as $resource => $model) {
            $record = $model::factory()->create();
            $this->get($this->base.'/'.$resource.'/'.$record->id)->assertNotFound();
            $this->get($this->base.'/'.$resource.'/'.$record->id.'/edit')->assertNotFound();
            $this->putJson($this->base.'/'.$resource.'/'.$record->id, [])->assertNotFound();
            $this->deleteJson($this->base.'/'.$resource.'/'.$record->id)->assertNotFound();
            $this->context('demo-crescent');
            $this->assertNotNull($model::find($record->id));
        }
        app()->forgetScopedInstances();
        $this->expectException(\LogicException::class);
        Student::count();
    }

    public function test_direct_postgresql_session_overlap_is_rejected(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL overlap trigger.');
        }
        $this->local();
        $this->fixtures();
        $this->expectException(QueryException::class);
        DB::table('academic_sessions')->insert(['school_id' => app(TenantContext::class)->school()->id, 'name' => 'Conflicting raw SQL', 'starts_on' => '2027-01-01', 'ends_on' => '2028-01-01', 'active' => true, 'is_current' => false]);
    }

    public function test_real_schools_are_never_exposed_to_local_academic_crud(): void
    {
        $this->local();
        School::where('slug', 'demo-maplewood')->update(['is_demo' => false]);
        $this->get($this->base.'/students')->assertNotFound();
        $this->postJson($this->base.'/students', $this->admission())->assertNotFound();
    }

    public function test_complete_directory_crud_for_all_editable_academic_catalogs(): void
    {
        $this->local();
        [$session,$class,$a] = $this->fixtures();
        $subject = Subject::factory()->create();
        $teachingSubject = Subject::factory()->create();
        ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $teachingSubject->id, 'classification' => 'compulsory']);
        $teacher = Teacher::factory()->create();
        $replacement = Teacher::factory()->create();
        $payloads = [
            'sessions' => ['name' => 'Future inactive session', 'starts_on' => '2040-08-01', 'ends_on' => '2041-07-31', 'active' => false, 'is_current' => false],
            'classes' => ['name' => 'CRUD class', 'code' => 'CRUD', 'grade_level' => 10, 'display_order' => 10, 'active' => true],
            'sections' => ['name' => 'CRUD section', 'code' => 'CRUD', 'academic_class_id' => $class->id, 'capacity' => 12, 'active' => true],
            'subjects' => ['name' => 'CRUD subject', 'code' => 'CRUD', 'active' => true],
            'teachers' => ['full_name' => 'CRUD teacher', 'employee_code' => 'CRUD', 'email' => 'teacher@example.test', 'phone' => '03000000000', 'status' => 'active'],
            'class-subjects' => ['academic_class_id' => $class->id, 'subject_id' => $subject->id, 'classification' => 'compulsory'],
            'teacher-assignments' => ['academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => $a->id, 'teacher_id' => $teacher->id, 'subject_id' => $teachingSubject->id, 'kind' => 'teaching'],
        ];
        foreach ($payloads as $resource => $data) {
            $model = AcademicResources::model($resource);
            $this->post($this->base.'/'.$resource, $data)->assertRedirect()->assertSessionHasNoErrors();
            $record = $model::latest('id')->firstOrFail();
            $this->get($this->base.'/'.$resource.'/'.$record->id)->assertOk();
            $this->get($this->base.'/'.$resource.'/'.$record->id.'/edit')->assertOk();
            if (isset($data['name'])) {
                $data['name'] .= ' updated';
            }
            if ($resource === 'teachers') {
                $data['full_name'] = 'Updated teacher';
            }
            if ($resource === 'class-subjects') {
                $data['classification'] = 'elective';
            }
            if ($resource === 'teacher-assignments') {
                $data['teacher_id'] = $replacement->id;
            }
            $this->put($this->base.'/'.$resource.'/'.$record->id, $data)->assertRedirect()->assertSessionHasNoErrors();
            foreach ($data as $key => $value) {
                $this->assertEquals($value, $record->fresh()->$key);
            }
            $this->delete($this->base.'/'.$resource.'/'.$record->id)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertNull($model::find($record->id));
        }
    }

    public function test_month_end_trends_preserve_the_last_day_of_a_promoted_session(): void
    {
        $this->local();
        [$session,$class,$a,$b,$student] = $this->fixtures();
        $source = $this->create('enrollments', $this->enrollment($session, $class, $a, $student));
        $next = $this->create('sessions', ['name' => 'Next session', 'starts_on' => '2027-08-01', 'ends_on' => '2028-07-31', 'active' => true, 'is_current' => false]);
        $higher = AcademicClass::factory()->create(['grade_level' => 8]);
        $section = Section::factory()->create(['academic_class_id' => $higher->id]);
        app(AcademicService::class)->change($source, ['operation' => 'promote', 'academic_session_id' => $next->id, 'academic_class_id' => $higher->id, 'section_id' => $section->id, 'started_on' => '2027-08-01']);
        $this->travelTo(Carbon::parse('2027-08-05', 'Asia/Karachi'));
        $result = app(AcademicDashboardData::class)->build(app(TenantContext::class)->school()->id, $session->id);
        $this->assertSame(1, $result['trend'][4]);
        $this->assertSame(0, $result['trend'][5]);
    }
}

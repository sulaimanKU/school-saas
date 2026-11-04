<?php

namespace Database\Seeders;

use App\Modules\Academics\AcademicService;
use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\Teacher;
use App\Modules\Academics\Models\TeacherAssignment;
use App\Modules\Platform\Preview\DemoCatalog;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

final class AcademicDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! config('preview.enabled') || ! config('preview.academic_crud')) {
            throw new RuntimeException('Academic demo seeding requires both local preview flags.');
        }
        $service = app(AcademicService::class);
        foreach (School::where('is_demo', true)->whereIn('slug', array_keys(DemoCatalog::SCHOOLS))->get() as $school) {
            app(TenantContext::class)->set($school);
            $service->transaction(function () use ($service) {
                $session = AcademicSession::firstOrCreate(['name' => '2026–2027 Demo Academic'], ['starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'active' => true, 'is_current' => ! AcademicSession::where('is_current', true)->exists()]);
                $classes = [];
                $sections = [];
                foreach ([7, 8] as $grade) {
                    $class = AcademicClass::firstOrCreate(['code' => 'DEMO-G'.$grade], ['name' => 'Grade '.$grade, 'grade_level' => $grade, 'display_order' => $grade, 'active' => true]);
                    $classes[] = $class;
                    foreach (['A', 'B'] as $code) {
                        $sections[] = Section::firstOrCreate(['academic_class_id' => $class->id, 'code' => $code], ['name' => 'Section '.$code, 'capacity' => 30, 'active' => true]);
                    }
                }
                foreach (['MATH' => 'Mathematics', 'ENG' => 'English'] as $code => $name) {
                    $subject = Subject::firstOrCreate(['code' => 'DEMO-'.$code], ['name' => $name, 'active' => true]);
                    $teacher = Teacher::firstOrCreate(['employee_code' => 'DEMO-'.$code], ['full_name' => 'Demo '.$name.' Teacher', 'email' => strtolower($code).'@example.test', 'status' => 'active']);
                    foreach ($classes as $class) {
                        ClassSubject::firstOrCreate(['academic_class_id' => $class->id, 'subject_id' => $subject->id], ['classification' => 'compulsory']);
                    }
                    foreach ($sections as $section) {
                        TeacherAssignment::firstOrCreate(['academic_session_id' => $session->id, 'section_id' => $section->id, 'kind' => 'teaching', 'subject_id' => $subject->id], ['academic_class_id' => $section->academic_class_id, 'teacher_id' => $teacher->id]);
                    }
                }
                for ($i = 1; $i <= 12; $i++) {
                    $name = 'Demo Academic Student '.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                    $student = Student::withTrashed()->where('full_name', $name)->first();
                    if ($student) {
                        continue;
                    } // Never restore, edit, or re-enroll an existing/archived record.
                    $student = $service->save('students', ['full_name' => $name, 'date_of_birth' => '2013-'.str_pad((string) (($i % 12) + 1), 2, '0', STR_PAD_LEFT).'-10', 'gender' => $i % 2 ? 'male' : 'female', 'guardian_name' => 'Demo Guardian '.$i, 'guardian_phone' => '03000000000', 'admitted_on' => '2026-08-01', 'status' => 'active']);
                    $section = $sections[($i - 1) % count($sections)];
                    $service->save('enrollments', ['student_id' => $student->id, 'academic_session_id' => $session->id, 'academic_class_id' => $section->academic_class_id, 'section_id' => $section->id, 'started_on' => '2026-08-01']);
                }
            });
        }
    }
}

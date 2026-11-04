<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $t) {
            $this->base($t);
            $t->string('name', 100);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('active')->default(true);
            $t->boolean('is_current')->default(false);
            $t->unique(['school_id', 'name']);
        });
        Schema::create('academic_classes', function (Blueprint $t) {
            $this->base($t);
            $t->string('name', 100);
            $t->string('code', 30);
            $t->unsignedSmallInteger('grade_level');
            $t->unsignedSmallInteger('display_order')->default(0);
            $t->boolean('active')->default(true);
            $t->unique(['school_id', 'code']);
        });
        Schema::create('sections', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('academic_class_id');
            $t->string('name', 100);
            $t->string('code', 30);
            $t->unsignedInteger('capacity')->nullable();
            $t->boolean('active')->default(true);
            $t->unique(['school_id', 'academic_class_id', 'code']);
            $t->unique(['school_id', 'academic_class_id', 'id']);
            $this->reference($t, 'academic_class_id', 'academic_classes');
        });
        Schema::create('subjects', function (Blueprint $t) {
            $this->base($t);
            $t->string('name', 100);
            $t->string('code', 30);
            $t->boolean('active')->default(true);
            $t->unique(['school_id', 'code']);
        });
        Schema::create('class_subject', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('academic_class_id');
            $t->unsignedBigInteger('subject_id');
            $t->enum('classification', ['compulsory', 'elective'])->default('compulsory');
            $t->unique(['school_id', 'academic_class_id', 'subject_id']);
            $this->reference($t, 'academic_class_id', 'academic_classes');
            $this->reference($t, 'subject_id', 'subjects');
        });
        Schema::create('teachers', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('employee_code', 30);
            $t->string('full_name', 150);
            $t->string('email')->nullable();
            $t->string('phone', 30)->nullable();
            $t->enum('status', ['active', 'on_leave', 'inactive'])->default('active');
            $t->unique(['school_id', 'employee_code']);
        });
        Schema::create('teacher_assignments', function (Blueprint $t) {
            $this->base($t);
            foreach (['academic_session_id', 'academic_class_id', 'section_id', 'teacher_id'] as $column) {
                $t->unsignedBigInteger($column);
            }
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->enum('kind', ['teaching', 'form_teacher']);
            $this->reference($t, 'academic_session_id', 'academic_sessions');
            $this->reference($t, 'teacher_id', 'teachers');
            $t->foreign(['school_id', 'academic_class_id', 'section_id'], 'assignment_section_fk')->references(['school_id', 'academic_class_id', 'id'])->on('sections')->restrictOnDelete();
            $t->foreign(['school_id', 'academic_class_id', 'subject_id'], 'assignment_subject_fk')->references(['school_id', 'academic_class_id', 'subject_id'])->on('class_subject')->restrictOnDelete();
            $t->index(['school_id', 'section_id']);
        });
        Schema::create('admission_counters', function (Blueprint $t) {
            $t->foreignId('school_id')->primary()->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('next_number')->default(1);
        });
        Schema::create('students', function (Blueprint $t) {
            $this->base($t);
            $t->string('admission_number', 30);
            $t->string('full_name', 150);
            $t->date('date_of_birth');
            $t->enum('gender', ['male', 'female', 'other', 'unspecified']);
            $t->string('guardian_name', 150);
            $t->string('guardian_phone', 30);
            $t->date('admitted_on');
            $t->enum('status', ['active', 'inactive', 'graduated', 'archived'])->default('active');
            $t->softDeletes();
            $t->unique(['school_id', 'admission_number']);
            $t->index(['school_id', 'full_name']);
            $t->index(['school_id', 'admitted_on']);
        });
        Schema::create('enrollments', function (Blueprint $t) {
            $this->base($t);
            foreach (['student_id', 'academic_session_id', 'academic_class_id', 'section_id'] as $column) {
                $t->unsignedBigInteger($column);
            }
            $t->date('started_on');
            $t->date('ended_on')->nullable();
            $t->enum('status', ['active', 'transferred', 'promoted', 'completed', 'withdrawn'])->default('active');
            $t->unsignedBigInteger('previous_enrollment_id')->nullable();
            $this->reference($t, 'student_id', 'students');
            $this->reference($t, 'academic_session_id', 'academic_sessions');
            $t->foreign(['school_id', 'academic_class_id', 'section_id'], 'enrollment_section_fk')->references(['school_id', 'academic_class_id', 'id'])->on('sections')->restrictOnDelete();
            $this->reference($t, 'previous_enrollment_id', 'enrollments');
            $t->index(['school_id', 'academic_session_id', 'status']);
            $t->index(['school_id', 'section_id', 'status']);
        });
        DB::statement('CREATE UNIQUE INDEX sessions_one_current ON academic_sessions (school_id) WHERE is_current = '.(DB::getDriverName() === 'pgsql' ? 'true' : '1'));
        DB::statement("CREATE UNIQUE INDEX enrollments_one_active ON enrollments (school_id, student_id, academic_session_id) WHERE status = 'active'");
        DB::statement("CREATE UNIQUE INDEX teaching_one_subject ON teacher_assignments (school_id, academic_session_id, section_id, subject_id) WHERE kind = 'teaching'");
        DB::statement("CREATE UNIQUE INDEX one_form_teacher ON teacher_assignments (school_id, academic_session_id, section_id) WHERE kind = 'form_teacher'");
        if (DB::getDriverName() === 'pgsql') {
            foreach ([
                'academic_sessions' => ['session_dates' => 'ends_on >= starts_on', 'current_is_active' => 'NOT is_current OR active'],
                'sections' => ['positive_capacity' => 'capacity IS NULL OR capacity > 0'],
                'academic_classes' => ['grade_range' => 'grade_level BETWEEN 0 AND 20 AND display_order >= 0'],
                'students' => ['student_dates' => 'date_of_birth <= admitted_on'],
                'teacher_assignments' => ['assignment_kind' => "(kind = 'teaching' AND subject_id IS NOT NULL) OR (kind = 'form_teacher' AND subject_id IS NULL)"],
                'enrollments' => ['enrollment_dates' => 'ended_on IS NULL OR ended_on >= started_on', 'enrollment_state' => "(status = 'active' AND ended_on IS NULL) OR (status <> 'active' AND ended_on IS NOT NULL)"],
            ] as $table => $checks) {
                foreach ($checks as $name => $condition) {
                    DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$condition})");
                }
            }
            // Serialize writes per school in the service; this trigger also protects direct SQL overlap writes.
            DB::unprepared(<<<'SQL'
CREATE FUNCTION academic_session_overlap_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 PERFORM id FROM schools WHERE id = NEW.school_id FOR UPDATE;
 IF NEW.active AND EXISTS (SELECT 1 FROM academic_sessions s WHERE s.school_id=NEW.school_id AND s.id<>NEW.id AND s.active AND s.starts_on<=NEW.ends_on AND s.ends_on>=NEW.starts_on) THEN
  RAISE EXCEPTION 'Active academic periods overlap' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END; $$;
CREATE TRIGGER academic_session_overlap BEFORE INSERT OR UPDATE ON academic_sessions FOR EACH ROW EXECUTE FUNCTION academic_session_overlap_guard();
SQL);
        }
    }

    private function base(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('school_id')->constrained()->restrictOnDelete();
        $t->timestamps();
        $t->unique(['school_id', 'id']);
    }

    private function reference(Blueprint $t, string $column, string $table): void
    {
        $t->foreign(['school_id', $column])->references(['school_id', 'id'])->on($table)->restrictOnDelete();
        $t->index(['school_id', $column]);
    }

    public function down(): void
    {
        foreach (['enrollments', 'students', 'admission_counters', 'teacher_assignments', 'teachers', 'class_subject', 'subjects', 'sections', 'academic_classes', 'academic_sessions'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS academic_session_overlap_guard()');
        }
    }
};

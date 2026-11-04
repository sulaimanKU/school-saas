<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function base(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('school_id')->constrained()->restrictOnDelete();
        $t->timestamps();
        $t->unique(['school_id', 'id']);
    }

    private function ref(Blueprint $t, string $column, string $table): void
    {
        $t->foreign(['school_id', $column])->references(['school_id', 'id'])->on($table)->restrictOnDelete();
        $t->index(['school_id', $column]);
    }

    public function up(): void
    {
        Schema::table('enrollments', fn (Blueprint $t) => $t->unique(['school_id', 'student_id', 'id'], 'enrollment_student_identity'));
        Schema::table('teacher_assignments', fn (Blueprint $t) => $t->unique(['school_id', 'academic_session_id', 'section_id', 'id'], 'assignment_session_identity'));
        Schema::create('attendance_sessions', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('academic_session_id');
            $t->unsignedBigInteger('section_id');
            $t->date('attendance_date');
            $this->ref($t, 'academic_session_id', 'academic_sessions');
            $this->ref($t, 'section_id', 'sections');
            $t->unique(['school_id', 'academic_session_id', 'section_id', 'attendance_date'], 'attendance_roster_date');
            $t->unique(['school_id', 'academic_session_id', 'attendance_date', 'id'], 'attendance_session_identity');
        });
        Schema::create('student_attendance_records', function (Blueprint $t) {
            $this->base($t);
            foreach (['attendance_session_id', 'academic_session_id', 'student_id', 'enrollment_id'] as $c) {
                $t->unsignedBigInteger($c);
            }
            $t->date('attendance_date');
            $t->enum('status', ['present', 'absent', 'late', 'excused']);
            $t->foreign(['school_id', 'academic_session_id', 'attendance_date', 'attendance_session_id'], 'student_attendance_session_fk')->references(['school_id', 'academic_session_id', 'attendance_date', 'id'])->on('attendance_sessions')->restrictOnDelete();
            $t->foreign(['school_id', 'student_id', 'enrollment_id'], 'student_attendance_enrollment_fk')->references(['school_id', 'student_id', 'id'])->on('enrollments')->restrictOnDelete();
            $t->unique(['school_id', 'academic_session_id', 'student_id', 'attendance_date'], 'one_student_attendance_daily');
            $t->index(['school_id', 'attendance_date']);
        });
        Schema::create('staff_attendance_records', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('teacher_id');
            $this->ref($t, 'teacher_id', 'teachers');
            $t->date('attendance_date');
            $t->enum('status', ['present', 'absent', 'late', 'leave', 'holiday']);
            $t->unique(['school_id', 'teacher_id', 'attendance_date']);
        });
        Schema::create('school_working_days', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedSmallInteger('weekday');
            $t->boolean('is_working')->default(true);
            $t->time('opens_at');
            $t->time('closes_at');
            $t->unique(['school_id', 'weekday']);
        });
        Schema::create('timetable_periods', function (Blueprint $t) {
            $this->base($t);
            $t->string('name', 100);
            $t->string('code', 30);
            $t->time('starts_at');
            $t->time('ends_at');
            $t->enum('kind', ['teaching', 'break', 'non_teaching']);
            $t->unique(['school_id', 'code']);
        });
        Schema::create('timetable_entries', function (Blueprint $t) {
            $this->base($t);
            foreach (['academic_session_id', 'section_id', 'timetable_period_id'] as $c) {
                $t->unsignedBigInteger($c);
            }
            $t->unsignedBigInteger('teacher_assignment_id')->nullable();
            $t->unsignedSmallInteger('weekday');
            $this->ref($t, 'academic_session_id', 'academic_sessions');
            $this->ref($t, 'section_id', 'sections');
            $this->ref($t, 'timetable_period_id', 'timetable_periods');
            $t->foreign(['school_id', 'academic_session_id', 'section_id', 'teacher_assignment_id'], 'timetable_assignment_fk')->references(['school_id', 'academic_session_id', 'section_id', 'id'])->on('teacher_assignments')->restrictOnDelete();
            $t->unique(['school_id', 'academic_session_id', 'section_id', 'weekday', 'timetable_period_id'], 'timetable_slot_unique');
        });
        Schema::create('grading_schemes', function (Blueprint $t) {
            $this->base($t);
            $t->string('name', 100);
            $t->boolean('ranking_enabled')->default(false);
            $t->string('logo_path')->default('/images/school-emblem.svg');
            $t->unique(['school_id', 'name']);
        });
        Schema::create('grading_bands', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('grading_scheme_id');
            $this->ref($t, 'grading_scheme_id', 'grading_schemes');
            $t->string('label', 20);
            $t->decimal('minimum_percent', 5, 2);
            $t->decimal('maximum_percent', 5, 2);
            $t->boolean('is_pass');
            $t->unique(['school_id', 'grading_scheme_id', 'minimum_percent'], 'grading_band_minimum');
        });
        Schema::create('exam_types', function (Blueprint $t) {
            $this->base($t);
            $t->string('name', 100);
            $t->string('code', 30);
            $t->unique(['school_id', 'code']);
        });
        Schema::create('exams', function (Blueprint $t) {
            $this->base($t);
            $t->string('name', 150);
            foreach (['academic_session_id', 'exam_type_id', 'grading_scheme_id'] as $c) {
                $t->unsignedBigInteger($c);
            }
            $this->ref($t, 'academic_session_id', 'academic_sessions');
            $this->ref($t, 'exam_type_id', 'exam_types');
            $this->ref($t, 'grading_scheme_id', 'grading_schemes');
            $t->enum('status', ['draft', 'scheduled', 'completed', 'published'])->default('draft');
            $t->unsignedInteger('publication_version')->default(0);
            $t->unique(['school_id', 'academic_session_id', 'name']);
            $t->unique(['school_id', 'academic_session_id', 'id'], 'exam_session_identity');
        });
        Schema::create('exam_schedules', function (Blueprint $t) {
            $this->base($t);
            foreach (['exam_id', 'academic_session_id', 'academic_class_id', 'section_id', 'subject_id'] as $c) {
                $t->unsignedBigInteger($c);
            }
            $t->unsignedBigInteger('teacher_id')->nullable();
            $t->date('exam_date');
            $t->time('starts_at');
            $t->time('ends_at');
            $t->decimal('maximum_marks', 8, 2);
            $t->decimal('passing_marks', 8, 2);
            $t->foreign(['school_id', 'academic_session_id', 'exam_id'], 'schedule_exam_fk')->references(['school_id', 'academic_session_id', 'id'])->on('exams')->restrictOnDelete();
            $t->foreign(['school_id', 'academic_class_id', 'section_id'], 'schedule_section_fk')->references(['school_id', 'academic_class_id', 'id'])->on('sections')->restrictOnDelete();
            $t->foreign(['school_id', 'academic_class_id', 'subject_id'], 'schedule_subject_fk')->references(['school_id', 'academic_class_id', 'subject_id'])->on('class_subject')->restrictOnDelete();
            $this->ref($t, 'teacher_id', 'teachers');
            $t->unique(['school_id', 'exam_id', 'section_id', 'subject_id'], 'one_exam_subject_section');
            $t->unique(['school_id', 'exam_id', 'subject_id', 'id'], 'schedule_mark_identity');
        });
        Schema::create('exam_marks', function (Blueprint $t) {
            $this->base($t);
            foreach (['exam_schedule_id', 'exam_id', 'subject_id', 'student_id', 'enrollment_id'] as $c) {
                $t->unsignedBigInteger($c);
            }
            $t->enum('status', ['scored', 'absent', 'exempt']);
            $t->decimal('obtained_marks', 8, 2)->nullable();
            $t->string('remarks', 500)->nullable();
            $t->foreign(['school_id', 'exam_id', 'subject_id', 'exam_schedule_id'], 'mark_schedule_fk')->references(['school_id', 'exam_id', 'subject_id', 'id'])->on('exam_schedules')->restrictOnDelete();
            $t->foreign(['school_id', 'student_id', 'enrollment_id'], 'mark_enrollment_fk')->references(['school_id', 'student_id', 'id'])->on('enrollments')->restrictOnDelete();
            $t->unique(['school_id', 'exam_id', 'subject_id', 'student_id'], 'one_student_exam_subject');
        });
        Schema::create('published_report_cards', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('exam_id');
            $t->unsignedBigInteger('student_id');
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->timestamp('published_at');
            $this->ref($t, 'exam_id', 'exams');
            $this->ref($t, 'student_id', 'students');
            $t->unique(['school_id', 'exam_id', 'student_id', 'version'], 'report_publication_version');
        });
        Schema::create('academic_audit_logs', function (Blueprint $t) {
            $this->base($t);
            $t->string('operation', 80);
            $t->string('entity_type', 80);
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('actor_context');
            $t->json('changes');
            $t->string('reason', 500)->nullable();
            $t->index(['school_id', 'created_at']);
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach ([
                'school_working_days' => ['work_day_bounds' => 'weekday BETWEEN 1 AND 7 AND closes_at > opens_at'],
                'timetable_periods' => ['period_time_bounds' => 'ends_at > starts_at'],
                'timetable_entries' => ['timetable_day_bounds' => 'weekday BETWEEN 1 AND 7'],
                'grading_bands' => ['band_bounds' => 'minimum_percent >= 0 AND maximum_percent <= 100 AND maximum_percent > minimum_percent'],
                'exam_schedules' => ['schedule_bounds' => 'ends_at > starts_at AND maximum_marks > 0 AND passing_marks >= 0 AND passing_marks <= maximum_marks'],
                'exam_marks' => ['marks_status_bounds' => "(status = 'scored' AND obtained_marks IS NOT NULL AND obtained_marks >= 0) OR (status IN ('absent','exempt') AND obtained_marks IS NULL)"],
            ] as $table => $checks) {
                foreach ($checks as $name => $condition) {
                    DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$condition})");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['academic_audit_logs', 'published_report_cards', 'exam_marks', 'exam_schedules', 'exams', 'exam_types', 'grading_bands', 'grading_schemes', 'timetable_entries', 'timetable_periods', 'school_working_days', 'staff_attendance_records', 'student_attendance_records', 'attendance_sessions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('teacher_assignments',fn (Blueprint $t) => $t->dropUnique('assignment_session_identity'));
        Schema::table('enrollments',fn (Blueprint $t) => $t->dropUnique('enrollment_student_identity'));
    }
};

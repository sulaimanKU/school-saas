<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
CREATE FUNCTION academic_used_period_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF (NEW.starts_at,NEW.ends_at,NEW.kind) IS DISTINCT FROM (OLD.starts_at,OLD.ends_at,OLD.kind) AND EXISTS(SELECT 1 FROM timetable_entries t WHERE t.school_id=OLD.school_id AND t.timetable_period_id=OLD.id) THEN RAISE EXCEPTION 'Used timetable period timing is locked' USING ERRCODE='23514'; END IF; RETURN NEW;
END; $$;
CREATE TRIGGER used_period_guard BEFORE UPDATE ON timetable_periods FOR EACH ROW EXECUTE FUNCTION academic_used_period_guard();
CREATE FUNCTION academic_used_assignment_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF (NEW.teacher_id,NEW.subject_id,NEW.academic_session_id,NEW.section_id,NEW.kind) IS DISTINCT FROM (OLD.teacher_id,OLD.subject_id,OLD.academic_session_id,OLD.section_id,OLD.kind) AND EXISTS(SELECT 1 FROM timetable_entries t WHERE t.school_id=OLD.school_id AND t.teacher_assignment_id=OLD.id) THEN RAISE EXCEPTION 'Scheduled teaching assignment is locked' USING ERRCODE='23514'; END IF; RETURN NEW;
END; $$;
CREATE TRIGGER used_assignment_guard BEFORE UPDATE ON teacher_assignments FOR EACH ROW EXECUTE FUNCTION academic_used_assignment_guard();
CREATE FUNCTION academic_assessed_schedule_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF (NEW.exam_id,NEW.section_id,NEW.subject_id,NEW.exam_date,NEW.starts_at,NEW.ends_at,NEW.maximum_marks,NEW.passing_marks) IS DISTINCT FROM (OLD.exam_id,OLD.section_id,OLD.subject_id,OLD.exam_date,OLD.starts_at,OLD.ends_at,OLD.maximum_marks,OLD.passing_marks) AND EXISTS(SELECT 1 FROM exam_marks m WHERE m.school_id=OLD.school_id AND m.exam_schedule_id=OLD.id) THEN RAISE EXCEPTION 'Assessed schedule context is locked' USING ERRCODE='23514'; END IF; RETURN NEW;
END; $$;
CREATE TRIGGER assessed_schedule_guard BEFORE UPDATE ON exam_schedules FOR EACH ROW EXECUTE FUNCTION academic_assessed_schedule_guard();
CREATE FUNCTION academic_recorded_enrollment_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM student_attendance_records a WHERE a.school_id=OLD.school_id AND a.enrollment_id=OLD.id AND (NEW.student_id<>OLD.student_id OR NEW.academic_session_id<>OLD.academic_session_id OR NEW.section_id<>OLD.section_id OR a.attendance_date<NEW.started_on OR (NEW.ended_on IS NOT NULL AND (a.attendance_date>NEW.ended_on OR (a.attendance_date=NEW.ended_on AND NEW.status IN ('transferred','withdrawn')))))) OR EXISTS(SELECT 1 FROM exam_marks m JOIN exam_schedules s ON s.school_id=m.school_id AND s.id=m.exam_schedule_id WHERE m.school_id=OLD.school_id AND m.enrollment_id=OLD.id AND (NEW.student_id<>OLD.student_id OR NEW.academic_session_id<>OLD.academic_session_id OR NEW.section_id<>OLD.section_id OR s.exam_date<NEW.started_on OR (NEW.ended_on IS NOT NULL AND (s.exam_date>NEW.ended_on OR (s.exam_date=NEW.ended_on AND NEW.status IN ('transferred','withdrawn')))))) THEN RAISE EXCEPTION 'Enrollment change would invalidate recorded attendance or assessment history' USING ERRCODE='23514'; END IF; RETURN NEW;
END; $$;
CREATE TRIGGER recorded_enrollment_guard BEFORE UPDATE ON enrollments FOR EACH ROW EXECUTE FUNCTION academic_recorded_enrollment_guard();
CREATE FUNCTION academic_work_hours_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM timetable_entries t JOIN timetable_periods p ON p.school_id=t.school_id AND p.id=t.timetable_period_id WHERE t.school_id=OLD.school_id AND t.weekday=OLD.weekday AND (NEW.weekday<>OLD.weekday OR NOT NEW.is_working OR p.starts_at<NEW.opens_at OR p.ends_at>NEW.closes_at)) THEN RAISE EXCEPTION 'Hours would invalidate a timetable' USING ERRCODE='23514'; END IF; RETURN NEW;
END; $$;
CREATE TRIGGER work_hours_guard BEFORE UPDATE ON school_working_days FOR EACH ROW EXECUTE FUNCTION academic_work_hours_guard();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }foreach (['academic_used_period_guard', 'academic_used_assignment_guard', 'academic_assessed_schedule_guard', 'academic_recorded_enrollment_guard', 'academic_work_hours_guard'] as $f) {
            DB::statement('DROP FUNCTION '.$f.'() CASCADE');
        }
    }
};

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
CREATE FUNCTION immutable_academic_snapshot() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Published snapshots and audit entries are append-only' USING ERRCODE='23514'; END; $$;
CREATE TRIGGER immutable_report BEFORE UPDATE OR DELETE ON published_report_cards FOR EACH ROW EXECUTE FUNCTION immutable_academic_snapshot();
CREATE TRIGGER immutable_audit BEFORE UPDATE OR DELETE ON academic_audit_logs FOR EACH ROW EXECUTE FUNCTION immutable_academic_snapshot();
CREATE FUNCTION academic_attendance_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NOT EXISTS (SELECT 1 FROM attendance_sessions a JOIN academic_sessions s ON s.school_id=a.school_id AND s.id=a.academic_session_id JOIN enrollments e ON e.school_id=a.school_id AND e.id=NEW.enrollment_id AND e.student_id=NEW.student_id WHERE a.school_id=NEW.school_id AND a.id=NEW.attendance_session_id AND e.academic_session_id=a.academic_session_id AND e.section_id=a.section_id AND NEW.attendance_date BETWEEN s.starts_on AND s.ends_on AND e.started_on<=NEW.attendance_date AND (e.ended_on IS NULL OR e.ended_on>NEW.attendance_date OR (e.ended_on=NEW.attendance_date AND e.status IN ('promoted','completed')))) THEN
  RAISE EXCEPTION 'Attendance requires a valid historical enrollment in the section and session' USING ERRCODE='23514';
 END IF; RETURN NEW;
END; $$;
CREATE TRIGGER attendance_enrollment_check BEFORE INSERT OR UPDATE ON student_attendance_records FOR EACH ROW EXECUTE FUNCTION academic_attendance_guard();
CREATE FUNCTION academic_timetable_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE p timetable_periods; a teacher_assignments; d school_working_days;
BEGIN
 PERFORM id FROM schools WHERE id=NEW.school_id FOR UPDATE;
 SELECT * INTO p FROM timetable_periods WHERE school_id=NEW.school_id AND id=NEW.timetable_period_id;
 SELECT * INTO d FROM school_working_days WHERE school_id=NEW.school_id AND weekday=NEW.weekday;
 IF d.id IS NULL OR NOT d.is_working OR p.starts_at<d.opens_at OR p.ends_at>d.closes_at THEN RAISE EXCEPTION 'Schedule outside school hours' USING ERRCODE='23514'; END IF;
 IF p.kind='teaching' THEN
  SELECT * INTO a FROM teacher_assignments WHERE school_id=NEW.school_id AND id=NEW.teacher_assignment_id;
  IF a.id IS NULL OR a.kind<>'teaching' THEN RAISE EXCEPTION 'Teaching period requires subject teacher assignment' USING ERRCODE='23514'; END IF;
 ELSE IF NEW.teacher_assignment_id IS NOT NULL THEN RAISE EXCEPTION 'Non-teaching period cannot assign a subject teacher' USING ERRCODE='23514'; END IF; END IF;
 IF EXISTS (SELECT 1 FROM timetable_entries t JOIN timetable_periods q ON q.school_id=t.school_id AND q.id=t.timetable_period_id LEFT JOIN teacher_assignments b ON b.school_id=t.school_id AND b.id=t.teacher_assignment_id WHERE t.school_id=NEW.school_id AND t.id<>NEW.id AND t.academic_session_id=NEW.academic_session_id AND t.weekday=NEW.weekday AND q.starts_at<p.ends_at AND q.ends_at>p.starts_at AND (t.section_id=NEW.section_id OR (a.teacher_id IS NOT NULL AND b.teacher_id=a.teacher_id))) THEN RAISE EXCEPTION 'Teacher or section timetable conflict' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END; $$;
CREATE TRIGGER timetable_conflicts BEFORE INSERT OR UPDATE ON timetable_entries FOR EACH ROW EXECUTE FUNCTION academic_timetable_guard();
CREATE FUNCTION academic_schedule_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE e exams; s academic_sessions;
BEGIN
 PERFORM id FROM schools WHERE id=NEW.school_id FOR UPDATE;
 SELECT * INTO e FROM exams WHERE school_id=NEW.school_id AND id=NEW.exam_id;
 IF e.status='published' THEN RAISE EXCEPTION 'Published exam is locked' USING ERRCODE='23514'; END IF;
 SELECT * INTO s FROM academic_sessions WHERE school_id=NEW.school_id AND id=NEW.academic_session_id;
 IF NEW.exam_date<s.starts_on OR NEW.exam_date>s.ends_on THEN RAISE EXCEPTION 'Exam outside academic session' USING ERRCODE='23514'; END IF;
 IF EXISTS (SELECT 1 FROM exam_schedules x WHERE x.school_id=NEW.school_id AND x.id<>NEW.id AND x.exam_date=NEW.exam_date AND x.starts_at<NEW.ends_at AND x.ends_at>NEW.starts_at AND (x.section_id=NEW.section_id OR (NEW.teacher_id IS NOT NULL AND x.teacher_id=NEW.teacher_id))) THEN RAISE EXCEPTION 'Exam schedule conflict' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END; $$;
CREATE TRIGGER exam_schedule_check BEFORE INSERT OR UPDATE ON exam_schedules FOR EACH ROW EXECUTE FUNCTION academic_schedule_guard();
CREATE FUNCTION academic_marks_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE x exam_schedules; e exams;
BEGIN
 SELECT * INTO x FROM exam_schedules WHERE school_id=NEW.school_id AND id=NEW.exam_schedule_id;
 SELECT * INTO e FROM exams WHERE school_id=NEW.school_id AND id=NEW.exam_id FOR UPDATE;
 IF e.status='published' THEN RAISE EXCEPTION 'Published marks are locked; use correction workflow' USING ERRCODE='23514'; END IF;
 IF NEW.obtained_marks>x.maximum_marks THEN RAISE EXCEPTION 'Marks exceed maximum' USING ERRCODE='23514'; END IF;
 IF NOT EXISTS (SELECT 1 FROM enrollments n WHERE n.school_id=NEW.school_id AND n.id=NEW.enrollment_id AND n.student_id=NEW.student_id AND n.academic_session_id=x.academic_session_id AND n.academic_class_id=x.academic_class_id AND n.section_id=x.section_id AND n.started_on<=x.exam_date AND (n.ended_on IS NULL OR n.ended_on>x.exam_date OR (n.ended_on=x.exam_date AND n.status IN ('promoted','completed')))) THEN RAISE EXCEPTION 'Marks require valid exam-date enrollment' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END; $$;
CREATE TRIGGER marks_bounds BEFORE INSERT OR UPDATE ON exam_marks FOR EACH ROW EXECUTE FUNCTION academic_marks_guard();
CREATE FUNCTION academic_published_delete_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM exams e WHERE e.school_id=OLD.school_id AND e.id=OLD.exam_id AND e.status='published') THEN RAISE EXCEPTION 'Published examination data is locked' USING ERRCODE='23514'; END IF; RETURN OLD;
END; $$;
CREATE TRIGGER marks_delete_guard BEFORE DELETE ON exam_marks FOR EACH ROW EXECUTE FUNCTION academic_published_delete_guard();
CREATE TRIGGER schedule_delete_guard BEFORE DELETE ON exam_schedules FOR EACH ROW EXECUTE FUNCTION academic_published_delete_guard();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (['immutable_academic_snapshot', 'academic_attendance_guard', 'academic_timetable_guard', 'academic_schedule_guard', 'academic_marks_guard', 'academic_published_delete_guard'] as $function) {
            DB::statement('DROP FUNCTION '.$function.'() CASCADE');
        }
    }
};

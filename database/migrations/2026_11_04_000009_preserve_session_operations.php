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
CREATE FUNCTION academic_session_operation_dates_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM attendance_sessions a WHERE a.school_id=OLD.school_id AND a.academic_session_id=OLD.id AND (a.attendance_date<NEW.starts_on OR a.attendance_date>NEW.ends_on)) OR EXISTS(SELECT 1 FROM exam_schedules s WHERE s.school_id=OLD.school_id AND s.academic_session_id=OLD.id AND (s.exam_date<NEW.starts_on OR s.exam_date>NEW.ends_on)) THEN RAISE EXCEPTION 'Session dates would exclude recorded academic operations' USING ERRCODE='23514'; END IF; RETURN NEW;
END; $$;
CREATE TRIGGER session_operation_dates BEFORE UPDATE ON academic_sessions FOR EACH ROW EXECUTE FUNCTION academic_session_operation_dates_guard();
CREATE FUNCTION academic_attendance_context_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF (NEW.section_id,NEW.academic_session_id,NEW.attendance_date) IS DISTINCT FROM (OLD.section_id,OLD.academic_session_id,OLD.attendance_date) AND EXISTS(SELECT 1 FROM student_attendance_records a WHERE a.school_id=OLD.school_id AND a.attendance_session_id=OLD.id) THEN RAISE EXCEPTION 'Recorded attendance context is immutable' USING ERRCODE='23514'; END IF; RETURN NEW;
END; $$;
CREATE TRIGGER attendance_context_guard BEFORE UPDATE ON attendance_sessions FOR EACH ROW EXECUTE FUNCTION academic_attendance_context_guard();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }DB::statement('DROP FUNCTION academic_session_operation_dates_guard() CASCADE');
        DB::statement('DROP FUNCTION academic_attendance_context_guard() CASCADE');
    }
};

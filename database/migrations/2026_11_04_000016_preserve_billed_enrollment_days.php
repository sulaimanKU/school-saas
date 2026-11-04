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
CREATE OR REPLACE FUNCTION finance_enrollment_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM student_fee_assignments WHERE school_id=OLD.school_id AND enrollment_id=OLD.id) THEN
  IF (NEW.school_id,NEW.student_id,NEW.academic_session_id,NEW.academic_class_id,NEW.section_id,NEW.started_on) IS DISTINCT FROM (OLD.school_id,OLD.student_id,OLD.academic_session_id,OLD.academic_class_id,OLD.section_id,OLD.started_on) THEN RAISE EXCEPTION 'Financial enrollment context is immutable' USING ERRCODE='23514'; END IF;
  IF NEW.ended_on IS NOT NULL AND EXISTS(SELECT 1 FROM student_invoices WHERE school_id=OLD.school_id AND enrollment_id=OLD.id AND status<>'voided' AND GREATEST(period,OLD.started_on)>(NEW.ended_on - CASE WHEN NEW.status IN ('promoted','completed') THEN 0 ELSE 1 END)) THEN RAISE EXCEPTION 'Enrollment end invalidates billed day' USING ERRCODE='23514'; END IF;
 END IF;
 RETURN NEW;
END $$;
SQL);
    }

    public function down(): void
    {
        // Preserve the corrected historical-date guard until its owning migration is removed.
    }
};

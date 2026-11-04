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
CREATE OR REPLACE FUNCTION finance_context_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE e enrollments%ROWTYPE; s fee_structures%ROWTYPE; i student_invoices%ROWTYPE; p student_payments%ROWTYPE; a student_fee_assignments%ROWTYPE; used bigint; eligible bigint; penalty bigint; paid bigint; refunded bigint;
BEGIN
 PERFORM id FROM schools WHERE id=NEW.school_id FOR UPDATE;
 IF TG_TABLE_NAME = 'fee_structures' THEN
  IF NEW.section_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM sections WHERE id=NEW.section_id AND school_id=NEW.school_id AND academic_class_id=NEW.academic_class_id) THEN RAISE EXCEPTION 'Invalid fee section'; END IF;
  IF TG_OP='UPDATE' AND EXISTS(SELECT 1 FROM student_fee_assignments WHERE school_id=OLD.school_id AND fee_structure_id=OLD.id) THEN RAISE EXCEPTION 'Assigned fee version is immutable'; END IF;
  IF NEW.active AND EXISTS(SELECT 1 FROM fee_structures f WHERE f.school_id=NEW.school_id AND f.id<>NEW.id AND f.active AND f.academic_session_id=NEW.academic_session_id AND f.academic_class_id=NEW.academic_class_id AND f.section_id IS NOT DISTINCT FROM NEW.section_id AND f.fee_category_id=NEW.fee_category_id AND f.effective_from<=NEW.effective_to AND f.effective_to>=NEW.effective_from) THEN RAISE EXCEPTION 'Conflicting fee structure'; END IF;
 ELSIF TG_TABLE_NAME = 'student_fee_assignments' THEN
  SELECT * INTO e FROM enrollments WHERE school_id=NEW.school_id AND id=NEW.enrollment_id;
  SELECT * INTO s FROM fee_structures WHERE school_id=NEW.school_id AND id=NEW.fee_structure_id;
  IF e.id IS NULL OR s.id IS NULL OR e.student_id<>NEW.student_id OR e.academic_session_id<>NEW.academic_session_id OR s.academic_session_id<>NEW.academic_session_id OR s.academic_class_id<>e.academic_class_id OR (s.section_id IS NOT NULL AND s.section_id<>e.section_id) OR s.fee_category_id<>NEW.fee_category_id OR s.currency<>NEW.currency OR NEW.starts_on<e.started_on OR (e.ended_on IS NOT NULL AND NEW.starts_on>(e.ended_on - CASE WHEN e.status IN ('promoted','completed') THEN 0 ELSE 1 END)) OR NEW.starts_on<s.effective_from OR NEW.ends_on>s.effective_to THEN RAISE EXCEPTION 'Invalid fee enrollment context'; END IF;
  IF EXISTS(SELECT 1 FROM student_fee_assignments f JOIN enrollments x ON x.school_id=f.school_id AND x.id=f.enrollment_id WHERE f.school_id=NEW.school_id AND f.student_id=NEW.student_id AND f.academic_session_id=NEW.academic_session_id AND f.fee_category_id=NEW.fee_category_id AND f.starts_on<=NEW.ends_on AND LEAST(f.ends_on,COALESCE(x.ended_on - CASE WHEN x.status IN ('promoted','completed') THEN 0 ELSE 1 END,f.ends_on))>=NEW.starts_on) THEN RAISE EXCEPTION 'Duplicate fee obligation assignment'; END IF;
 ELSIF TG_TABLE_NAME = 'student_invoices' THEN
  SELECT * INTO e FROM enrollments WHERE school_id=NEW.school_id AND id=NEW.enrollment_id;
  IF e.id IS NULL OR e.student_id<>NEW.student_id OR e.academic_session_id<>NEW.academic_session_id OR e.academic_class_id<>NEW.academic_class_id OR e.section_id<>NEW.section_id THEN RAISE EXCEPTION 'Invalid invoice enrollment context'; END IF;
  IF TG_OP='UPDATE' AND NEW.status='voided' AND (EXISTS(SELECT 1 FROM payment_allocations WHERE school_id=NEW.school_id AND invoice_id=NEW.id) OR EXISTS(SELECT 1 FROM invoice_penalties WHERE school_id=NEW.school_id AND invoice_id=NEW.id)) THEN RAISE EXCEPTION 'Invoice has posted history'; END IF;
 ELSIF TG_TABLE_NAME = 'student_invoice_items' THEN
  SELECT * INTO i FROM student_invoices WHERE school_id=NEW.school_id AND id=NEW.invoice_id;
  SELECT * INTO a FROM student_fee_assignments WHERE school_id=NEW.school_id AND id=NEW.assignment_id;
  IF a.id IS NULL OR i.id IS NULL OR a.student_id<>i.student_id OR NEW.student_id<>i.student_id OR NEW.academic_session_id<>i.academic_session_id OR a.academic_session_id<>i.academic_session_id OR a.fee_category_id<>NEW.fee_category_id OR a.currency<>i.currency OR i.period<>NEW.obligation THEN RAISE EXCEPTION 'Invoice item context mismatch'; END IF;
 ELSIF TG_TABLE_NAME = 'payment_allocations' THEN
  SELECT * INTO p FROM student_payments WHERE school_id=NEW.school_id AND id=NEW.payment_id;
  SELECT * INTO i FROM student_invoices WHERE school_id=NEW.school_id AND id=NEW.invoice_id;
  IF p.id IS NULL OR i.id IS NULL OR p.status<>'posted' OR i.status<>'issued' OR p.student_id<>i.student_id OR p.currency<>i.currency THEN RAISE EXCEPTION 'Allocation student/status/currency mismatch'; END IF;
  SELECT COALESCE(SUM(amount_minor),0) INTO used FROM payment_allocations WHERE school_id=NEW.school_id AND payment_id=p.id;
  SELECT COALESCE(SUM(amount_minor),0) INTO refunded FROM student_refunds WHERE school_id=NEW.school_id AND payment_id=p.id AND invoice_id IS NULL AND status IN ('requested','posted');
  IF used+NEW.amount_minor+refunded>p.amount_minor THEN RAISE EXCEPTION 'Payment over-allocation'; END IF;
  SELECT COALESCE(SUM(x.amount_minor),0) INTO paid FROM payment_allocations x JOIN student_payments y ON y.id=x.payment_id AND y.school_id=x.school_id WHERE x.school_id=NEW.school_id AND x.invoice_id=i.id AND y.status='posted';
  SELECT COALESCE(SUM(amount_minor),0) INTO refunded FROM student_refunds WHERE school_id=NEW.school_id AND invoice_id=i.id AND status='posted';
  SELECT COALESCE(SUM(amount_minor),0) INTO penalty FROM invoice_penalties WHERE school_id=NEW.school_id AND invoice_id=i.id;
  IF paid-refunded+NEW.amount_minor>i.total_minor+penalty THEN RAISE EXCEPTION 'Invoice overpayment'; END IF;
 ELSIF TG_TABLE_NAME = 'student_refunds' THEN
  SELECT * INTO p FROM student_payments WHERE school_id=NEW.school_id AND id=NEW.payment_id;
  IF p.id IS NULL OR p.status<>'posted' THEN RAISE EXCEPTION 'Refund requires a posted payment'; END IF;
  IF NEW.invoice_id IS NULL THEN SELECT p.amount_minor-COALESCE(SUM(amount_minor),0) INTO eligible FROM payment_allocations WHERE school_id=NEW.school_id AND payment_id=p.id;
  ELSE SELECT COALESCE(SUM(amount_minor),0) INTO eligible FROM payment_allocations WHERE school_id=NEW.school_id AND payment_id=p.id AND invoice_id=NEW.invoice_id; END IF;
  SELECT COALESCE(SUM(amount_minor),0) INTO used FROM student_refunds WHERE school_id=NEW.school_id AND payment_id=p.id AND invoice_id IS NOT DISTINCT FROM NEW.invoice_id AND id<>NEW.id AND status IN ('requested','posted');
  IF NEW.status IN ('requested','posted') AND used+NEW.amount_minor>eligible THEN RAISE EXCEPTION 'Refund exceeds eligibility'; END IF;
 ELSIF TG_TABLE_NAME = 'student_payments' THEN
  IF NEW.status='reversed' AND EXISTS(SELECT 1 FROM student_refunds WHERE school_id=NEW.school_id AND payment_id=NEW.id AND status IN ('requested','posted')) THEN RAISE EXCEPTION 'Refund history blocks reversal'; END IF;
 ELSIF TG_TABLE_NAME = 'payment_receipts' THEN
  IF NOT EXISTS(SELECT 1 FROM student_payments WHERE school_id=NEW.school_id AND id=NEW.payment_id AND status='posted') THEN RAISE EXCEPTION 'Receipt requires a posted payment'; END IF;
 ELSIF TG_TABLE_NAME = 'invoice_penalties' THEN
  IF NOT EXISTS(SELECT 1 FROM student_invoices WHERE school_id=NEW.school_id AND id=NEW.invoice_id AND status='issued' AND NOT late_fee_exempt) THEN RAISE EXCEPTION 'Penalty requires non-exempt issued invoice'; END IF;
 END IF;
 RETURN NEW;
END $$;
SQL);
    }

    public function down(): void
    {
        // This data integrity correction remains in place when rolling back its marker.
    }
};

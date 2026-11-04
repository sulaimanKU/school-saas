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
CREATE FUNCTION finance_invoice_total_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE invoice_key bigint; owner_key bigint; billed bigint; concession bigint; i student_invoices%ROWTYPE;
BEGIN
 IF TG_TABLE_NAME='student_invoices' THEN invoice_key=NEW.id; owner_key=NEW.school_id;
 ELSE invoice_key=NEW.invoice_id; owner_key=NEW.school_id; END IF;
 SELECT * INTO i FROM student_invoices WHERE school_id=owner_key AND id=invoice_key;
 SELECT COALESCE(SUM(amount_minor),0),COALESCE(SUM(discount_minor),0) INTO billed,concession FROM student_invoice_items WHERE school_id=owner_key AND invoice_id=invoice_key;
 IF NOT EXISTS(SELECT 1 FROM student_invoice_items WHERE school_id=owner_key AND invoice_id=invoice_key) OR billed<>i.subtotal_minor OR concession<>i.discount_minor THEN RAISE EXCEPTION 'Invoice totals differ from immutable items' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE CONSTRAINT TRIGGER finance_invoice_total AFTER INSERT ON student_invoices DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION finance_invoice_total_guard();
CREATE CONSTRAINT TRIGGER finance_item_total AFTER INSERT ON student_invoice_items DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION finance_invoice_total_guard();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP TRIGGER finance_invoice_total ON student_invoices');
        DB::statement('DROP TRIGGER finance_item_total ON student_invoice_items');
        DB::statement('DROP FUNCTION finance_invoice_total_guard()');
    }
};

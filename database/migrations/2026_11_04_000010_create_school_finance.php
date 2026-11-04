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
        $t->unique(['school_id', 'id']);
        $t->timestamps();
    }

    private function ref(Blueprint $t, string $column, string $table, bool $nullable = false): void
    {
        $t->unsignedBigInteger($column)->nullable($nullable);
        $t->foreign(['school_id', $column])->references(['school_id', 'id'])->on($table)->restrictOnDelete();
    }

    public function up(): void
    {
        foreach (['fee_categories', 'expense_categories'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $this->base($t);
                $t->string('name', 100);
                $t->boolean('active')->default(true);
                $t->unique(['school_id', 'name']);
            });
        }
        Schema::create('finance_counters', function (Blueprint $t) {
            $t->foreignId('school_id')->constrained()->restrictOnDelete();
            $t->string('kind', 20);
            $t->bigInteger('next_number')->default(1);
            $t->primary(['school_id', 'kind']);
        });
        Schema::create('fee_structures', function (Blueprint $t) {
            $this->base($t);
            $t->string('name', 100);
            $this->ref($t, 'academic_session_id', 'academic_sessions');
            $this->ref($t, 'academic_class_id', 'academic_classes');
            $this->ref($t, 'section_id', 'sections', true);
            $this->ref($t, 'fee_category_id', 'fee_categories');
            $t->bigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('frequency', 15);
            $t->date('effective_from');
            $t->date('effective_to');
            $t->boolean('active')->default(true);
        });
        Schema::create('student_fee_assignments', function (Blueprint $t) {
            $this->base($t);
            $this->ref($t, 'student_id', 'students');
            $this->ref($t, 'enrollment_id', 'enrollments');
            $this->ref($t, 'academic_session_id', 'academic_sessions');
            $this->ref($t, 'fee_structure_id', 'fee_structures');
            $this->ref($t, 'fee_category_id', 'fee_categories');
            $t->bigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('frequency', 15);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('active')->default(true);
            $t->json('snapshot');
            $t->unique(['school_id', 'enrollment_id', 'fee_structure_id'], 'finance_assignment_unique');
        });
        Schema::create('student_discounts', function (Blueprint $t) {
            $this->base($t);
            $this->ref($t, 'student_id', 'students');
            $this->ref($t, 'academic_session_id', 'academic_sessions');
            $this->ref($t, 'fee_category_id', 'fee_categories', true);
            $t->string('name', 100);
            $t->string('kind', 30);
            $t->string('calculation', 10);
            $t->bigInteger('value');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('approval_note', 500);
            $t->boolean('active')->default(true);
        });
        Schema::create('late_fee_policies', function (Blueprint $t) {
            $this->base($t);
            $t->string('name', 100);
            $t->string('calculation', 10);
            $t->bigInteger('value');
            $t->unsignedInteger('grace_days')->default(0);
            $t->bigInteger('cap_minor');
            $t->boolean('active')->default(true);
        });
        Schema::create('student_invoices', function (Blueprint $t) {
            $this->base($t);
            $this->ref($t, 'student_id', 'students');
            $this->ref($t, 'enrollment_id', 'enrollments');
            $this->ref($t, 'academic_session_id', 'academic_sessions');
            $this->ref($t, 'academic_class_id', 'academic_classes');
            $this->ref($t, 'section_id', 'sections');
            $t->string('number', 40);
            $t->date('period');
            $t->date('issued_on');
            $t->date('due_on');
            $t->string('status', 15)->default('draft');
            $t->string('currency', 3);
            $t->bigInteger('subtotal_minor');
            $t->bigInteger('discount_minor');
            $t->bigInteger('total_minor');
            $t->boolean('late_fee_exempt')->default(false);
            $t->json('snapshot');
            $t->string('void_reason', 500)->nullable();
            $t->unique(['school_id', 'number']);
            $t->unique(['school_id', 'student_id', 'academic_session_id', 'period'], 'finance_month_unique');
            $t->index(['school_id', 'due_on', 'status']);
        });
        Schema::create('student_invoice_items', function (Blueprint $t) {
            $this->base($t);
            $this->ref($t, 'invoice_id', 'student_invoices');
            $this->ref($t, 'assignment_id', 'student_fee_assignments');
            $this->ref($t, 'fee_category_id', 'fee_categories');
            $this->ref($t, 'student_id', 'students');
            $this->ref($t, 'academic_session_id', 'academic_sessions');
            $t->date('obligation');
            $t->string('description', 150);
            $t->bigInteger('amount_minor');
            $t->bigInteger('discount_minor');
            $t->json('snapshot');
            $t->unique(['school_id', 'assignment_id', 'obligation'], 'finance_obligation_unique');
            $t->unique(['school_id', 'student_id', 'academic_session_id', 'fee_category_id', 'obligation'], 'finance_student_obligation_unique');
        });
        Schema::create('invoice_penalties', function (Blueprint $t) {
            $this->base($t);
            $this->ref($t, 'invoice_id', 'student_invoices');
            $this->ref($t, 'policy_id', 'late_fee_policies');
            $t->date('posted_on');
            $t->bigInteger('amount_minor');
            $t->json('snapshot');
            $t->unique(['school_id', 'invoice_id']);
        });
        Schema::create('student_payments', function (Blueprint $t) {
            $this->base($t);
            $this->ref($t, 'student_id', 'students');
            $t->date('paid_on');
            $t->bigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('method', 20);
            $t->string('reference', 150)->nullable();
            $t->text('notes')->nullable();
            $t->string('idempotency_key', 100);
            $t->string('request_hash', 64);
            $t->string('status', 15)->default('posted');
            $t->string('reversal_reason', 500)->nullable();
            $t->unique(['school_id', 'idempotency_key']);
            $t->index(['school_id', 'paid_on']);
        });
        Schema::create('payment_allocations', function (Blueprint $t) {
            $this->base($t);
            $this->ref($t, 'payment_id', 'student_payments');
            $this->ref($t, 'invoice_id', 'student_invoices');
            $t->bigInteger('amount_minor');
            $t->unique(['school_id', 'payment_id', 'invoice_id'], 'finance_allocation_unique');
        });
        Schema::create('payment_receipts', function (Blueprint $t) {
            $this->base($t);
            $this->ref($t, 'payment_id', 'student_payments');
            $t->string('number', 40);
            $t->json('snapshot');
            $t->unique(['school_id', 'payment_id']);
            $t->unique(['school_id', 'number']);
        });
        Schema::create('student_refunds', function (Blueprint $t) {
            $this->base($t);
            $this->ref($t, 'payment_id', 'student_payments');
            $this->ref($t, 'invoice_id', 'student_invoices', true);
            $t->bigInteger('amount_minor');
            $t->date('refunded_on');
            $t->string('reason', 500);
            $t->string('status', 15)->default('requested');
            $t->string('idempotency_key', 100);
            $t->unique(['school_id', 'idempotency_key']);
        });
        Schema::create('school_expenses', function (Blueprint $t) {
            $this->base($t);
            $this->ref($t, 'expense_category_id', 'expense_categories');
            $t->string('payee', 150);
            $t->string('description', 500);
            $t->date('spent_on');
            $t->bigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('method', 20);
            $t->string('status', 15)->default('posted');
            $t->string('attachment_path')->nullable();
            $t->string('idempotency_key', 100);
            $t->unique(['school_id', 'idempotency_key']);
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (['fee_structures', 'student_fee_assignments', 'student_payments', 'payment_allocations', 'student_refunds', 'school_expenses', 'invoice_penalties'] as $table) {
                DB::statement("ALTER TABLE $table ADD CHECK (amount_minor > 0)");
            }
            DB::statement('ALTER TABLE student_invoices ADD CHECK (subtotal_minor >= 0 AND discount_minor >= 0 AND discount_minor <= subtotal_minor AND total_minor = subtotal_minor - discount_minor AND due_on >= issued_on)');
            DB::statement('ALTER TABLE student_invoice_items ADD CHECK (amount_minor > 0 AND discount_minor >= 0 AND discount_minor <= amount_minor)');
            DB::statement('ALTER TABLE student_discounts ADD CHECK (value > 0 AND (calculation = \'fixed\' OR (calculation = \'percent\' AND value <= 10000)) AND ends_on >= starts_on)');
            DB::statement('ALTER TABLE late_fee_policies ADD CHECK (value > 0 AND cap_minor > 0 AND grace_days >= 0 AND (calculation = \'fixed\' OR (calculation = \'percent\' AND value <= 10000)))');
            foreach (['fee_structures' => ['effective_from', 'effective_to'], 'student_fee_assignments' => ['starts_on', 'ends_on']] as $table => $dates) {
                DB::statement("ALTER TABLE $table ADD CHECK ($dates[1] >= $dates[0] AND frequency IN ('monthly','quarterly','annual','one_time'))");
            }
            DB::statement("ALTER TABLE student_invoices ADD CHECK (status IN ('draft','issued','voided'))");
            DB::statement("ALTER TABLE student_payments ADD CHECK (status IN ('posted','reversed') AND method IN ('cash','bank_transfer','cheque','other'))");
            DB::statement("ALTER TABLE student_refunds ADD CHECK (status IN ('requested','posted','rejected'))");
            DB::unprepared(<<<'SQL'
CREATE FUNCTION finance_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Immutable financial record'; END $$;
SQL);
            foreach (['student_invoice_items', 'payment_allocations', 'payment_receipts', 'invoice_penalties', 'student_fee_assignments'] as $table) {
                DB::statement("CREATE TRIGGER finance_immutable BEFORE UPDATE OR DELETE ON $table FOR EACH ROW EXECUTE FUNCTION finance_immutable()");
            }
            DB::unprepared(<<<'SQL'
CREATE FUNCTION finance_posted_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Financial history cannot be deleted'; END IF;
 IF TG_TABLE_NAME = 'student_invoices' THEN
  IF (to_jsonb(NEW) - ARRAY['status','void_reason','updated_at']) IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['status','void_reason','updated_at']) THEN RAISE EXCEPTION 'Invoice snapshot is immutable'; END IF;
  IF NOT (OLD.status = NEW.status OR (OLD.status = 'draft' AND NEW.status IN ('issued','voided')) OR (OLD.status = 'issued' AND NEW.status = 'voided')) THEN RAISE EXCEPTION 'Invalid invoice transition'; END IF;
 ELSIF TG_TABLE_NAME = 'student_payments' THEN
  IF (to_jsonb(NEW) - ARRAY['status','reversal_reason','updated_at']) IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['status','reversal_reason','updated_at']) OR OLD.status <> 'posted' OR NEW.status <> 'reversed' THEN RAISE EXCEPTION 'Invalid payment correction'; END IF;
 ELSIF TG_TABLE_NAME = 'student_refunds' THEN
  IF (to_jsonb(NEW) - ARRAY['status','updated_at']) IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['status','updated_at']) OR OLD.status <> 'requested' OR NEW.status NOT IN ('posted','rejected') THEN RAISE EXCEPTION 'Invalid refund transition'; END IF;
 ELSE RAISE EXCEPTION 'Posted expense is immutable';
 END IF;
 RETURN NEW;
END $$;
SQL);
            foreach (['student_invoices', 'student_payments', 'student_refunds', 'school_expenses'] as $table) {
                DB::statement("CREATE TRIGGER finance_posted_guard BEFORE UPDATE OR DELETE ON $table FOR EACH ROW EXECUTE FUNCTION finance_posted_guard()");
            }
        }
    }

    public function down(): void
    {
        foreach (['school_expenses', 'student_refunds', 'payment_receipts', 'payment_allocations', 'student_payments', 'invoice_penalties', 'student_invoice_items', 'student_invoices', 'late_fee_policies', 'student_discounts', 'student_fee_assignments', 'fee_structures', 'finance_counters', 'expense_categories', 'fee_categories'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS finance_immutable()');
            DB::statement('DROP FUNCTION IF EXISTS finance_posted_guard()');
        }
    }
};

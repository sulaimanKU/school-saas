<?php

namespace Tests\Feature;

use App\Domain\Finance\FinanceReports;
use App\Domain\Finance\FinanceService;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Models\FeeAssignment;
use App\Domain\Finance\Models\FeeCategory;
use App\Domain\Finance\Models\FeeStructure;
use App\Domain\Finance\Models\InvoiceItem;
use App\Domain\Finance\Models\InvoicePenalty;
use App\Domain\Finance\Models\PaymentAllocation;
use App\Domain\Finance\Models\PaymentReceipt;
use App\Domain\Finance\Models\SchoolExpense;
use App\Domain\Finance\Models\StudentInvoice;
use App\Domain\Finance\Models\StudentPayment;
use App\Domain\Finance\Money;
use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Operations\Models\AcademicAuditLog;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Carbon\Carbon;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchoolFinanceTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/preview/schools/demo-maplewood/finance';

    private function local(): void
    {
        $this->app->instance('env', 'local');
        config(['preview.enabled' => true, 'preview.finance' => true]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutVite();
        $this->seed(LocalDemoSeeder::class);
        app(TenantContext::class)->set(School::where('slug', 'demo-maplewood')->firstOrFail());
        $this->travelTo(Carbon::parse('2026-11-04 12:00:00', 'Asia/Karachi'));
    }

    private function fixture(): array
    {
        $session = AcademicSession::factory()->create();
        $class = AcademicClass::factory()->create();
        $section = Section::factory()->create(['academic_class_id' => $class->id]);
        $student = Student::factory()->create(['admitted_on' => '2026-08-01']);
        $enrollment = Enrollment::create(['student_id' => $student->id, 'academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => $section->id, 'started_on' => '2026-08-01', 'status' => 'active']);
        $category = FeeCategory::create(['name' => 'Tuition', 'active' => true]);
        $structure = $this->service()->catalog('structures', ['name' => 'Tuition 2026', 'academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => null, 'fee_category_id' => $category->id, 'amount' => '1000.00', 'frequency' => 'monthly', 'effective_from' => '2026-08-01', 'effective_to' => '2027-07-31', 'active' => true]);

        return compact('session', 'class', 'section', 'student', 'enrollment', 'category', 'structure');
    }

    private function service(): FinanceService
    {
        return app(FinanceService::class);
    }

    private function billing(array $f, string $period = '2026-10-01'): array
    {
        return ['academic_session_id' => $f['session']->id, 'period' => $period, 'issued_on' => '2026-10-01', 'due_on' => '2026-10-10', 'issue' => true, 'late_fee_exempt' => false];
    }

    private function invoice(array $f): StudentInvoice
    {
        $this->service()->assign(['fee_structure_id' => $f['structure']->id]);

        return $this->service()->generate($this->billing($f))[0];
    }

    private function payment(array $f, StudentInvoice $i, string $amount = '400.00', string $key = 'payment-test-1'): array
    {
        return ['student_id' => $f['student']->id, 'paid_on' => '2026-10-02', 'amount' => $amount, 'method' => 'cash', 'idempotency_key' => $key, 'allocations' => [['invoice_id' => $i->id, 'amount' => $amount]]];
    }

    private function invalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected financial validation failure');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_configuration_crud_assignment_snapshots_and_duplicate_billing(): void
    {
        $this->local();
        $f = $this->fixture();
        $this->post($this->base.'/categories', ['name' => 'Admission', 'active' => true])->assertRedirect();
        $category = FeeCategory::where('name', 'Admission')->firstOrFail();
        $this->put($this->base.'/categories/'.$category->id, ['name' => 'Admission Fee', 'active' => false])->assertRedirect();
        $this->delete($this->base.'/categories/'.$category->id)->assertRedirect();
        $this->assertSame(1, $this->service()->assign(['fee_structure_id' => $f['structure']->id]));
        $this->assertSame(0, $this->service()->assign(['fee_structure_id' => $f['structure']->id]));
        $first = $this->service()->generate($this->billing($f))[0];
        $again = $this->service()->generate($this->billing($f))[0];
        $this->assertSame($first->id, $again->id);
        $this->assertSame('INV-0000001', $first->number);
        $this->assertSame(100000, $first->total_minor);
        $this->assertSame(1, InvoiceItem::count());
        $this->invalid(fn () => $this->service()->catalog('structures', ['name' => 'Changed', ...$f['structure']->only(['academic_session_id', 'academic_class_id', 'section_id', 'fee_category_id', 'frequency', 'effective_from', 'effective_to', 'active']), 'amount' => '2.00'], $f['structure']->id));
        $this->assertSame(100000, $first->fresh()->total_minor);
        $next = $this->service()->generate($this->billing($f, '2026-11-01'))[0];
        $this->assertSame('INV-0000002', $next->number);
    }

    public function test_partial_payments_duplicate_submission_credit_and_reversal(): void
    {
        $this->local();
        $f = $this->fixture();
        $i = $this->invoice($f);
        $d = $this->payment($f, $i);
        $p = $this->service()->pay($d);
        $same = $this->service()->pay($d);
        $this->assertSame($p->id, $same->id);
        $this->assertSame(60000, $this->service()->balance($i)['outstanding']);
        $this->assertSame(1, PaymentReceipt::count());
        $this->invalid(fn () => $this->service()->pay([...$d, 'amount' => '600.00']));
        $this->invalid(fn () => $this->service()->pay($this->payment($f, $i, '601.00', 'payment-test-over')));
        $this->assertSame(1, StudentPayment::count());
        $credit = $this->service()->pay([...$d, 'amount' => '50.00', 'allocations' => [], 'idempotency_key' => 'payment-credit-1']);
        $this->assertSame(5000, PaymentReceipt::where('payment_id', $credit->id)->first()->snapshot['unallocated_minor']);
        $this->service()->reverse($p->id, 'Entry recorded in error');
        $this->assertSame(100000, $this->service()->balance($i)['outstanding']);
        $this->get($this->base.'/documents/receipt/'.$p->id)->assertInertia(fn (Assert $a) => $a->component('FinanceDocument')->where('document.status', 'reversed')->where('document.snapshot.amount_minor', 40000));
        $this->assertSame(1, PaymentAllocation::count());
    }

    public function test_payment_can_allocate_across_multiple_invoices_and_cannot_overallocate(): void
    {
        $this->local();
        $f = $this->fixture();
        $i = $this->invoice($f);
        $j = $this->service()->generate($this->billing($f, '2026-11-01'))[0];
        $d = $this->payment($f, $i, '1000.00');
        $d['amount'] = '1500.00';
        $d['allocations'][] = ['invoice_id' => $j->id, 'amount' => '500.00'];
        $this->service()->pay($d);
        $this->assertSame(0, $this->service()->balance($i)['outstanding']);
        $this->assertSame(50000, $this->service()->balance($j)['outstanding']);
        $d = $this->payment($f, $j, '100.00', 'payment-too-small');
        $d['amount'] = '1.00';
        $this->invalid(fn () => $this->service()->pay($d));
    }

    public function test_discounts_do_not_stack_and_posted_invoices_stay_stable(): void
    {
        $this->local();
        $f = $this->fixture();
        $d = ['name' => 'Need scholarship', 'student_id' => $f['student']->id, 'academic_session_id' => $f['session']->id, 'fee_category_id' => null, 'kind' => 'scholarship', 'calculation' => 'percent', 'value' => '10.00', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'approval_note' => 'Development preview approval', 'active' => true];
        $discount = $this->service()->catalog('discounts', $d);
        $this->service()->catalog('discounts', [...$d, 'name' => 'Sibling', 'calculation' => 'fixed', 'value' => '200.00']);
        $i = $this->invoice($f);
        $this->assertSame(20000, $i->discount_minor);
        $this->assertSame(80000, $i->total_minor);
        $this->service()->catalog('discounts', [...$d, 'value' => '100.00'], $discount->id);
        $this->assertSame(80000, $i->fresh()->total_minor);
        $j = $this->service()->generate($this->billing($f, '2026-11-01'))[0];
        $this->assertSame(0, $j->total_minor);
        $this->assertSame('paid', $this->service()->balance($j)['display_status']);
        $this->invalid(fn () => $this->service()->catalog('discounts', [...$d, 'value' => '101.00']));
    }

    public function test_penalty_preview_posting_idempotency_cap_and_exemptions(): void
    {
        $this->local();
        $f = $this->fixture();
        $i = $this->invoice($f);
        $p = $this->service()->catalog('late-fees', ['name' => 'Five percent', 'calculation' => 'percent', 'value' => '5.00', 'grace_days' => 3, 'cap' => '25.00', 'active' => true]);
        $this->assertSame(2500, $this->service()->penalty($i->id, $p->id)['amount_minor']);
        $this->assertSame(0, InvoicePenalty::count());
        $this->service()->penalty($i->id, $p->id, true);
        $this->service()->penalty($i->id, $p->id, true);
        $this->assertSame(102500, $this->service()->balance($i)['outstanding']);
        $this->assertSame(100000, $i->fresh()->total_minor);
        $d = $this->billing($f, '2026-11-01');
        $d['late_fee_exempt'] = true;
        $j = $this->service()->generate($d)[0];
        $this->assertSame(0, $this->service()->penalty($j->id, $p->id)['amount_minor']);
    }

    public function test_refund_reservations_posting_and_eligibility(): void
    {
        $this->local();
        $f = $this->fixture();
        $i = $this->invoice($f);
        $p = $this->service()->pay($this->payment($f, $i));
        $d = ['payment_id' => $p->id, 'invoice_id' => $i->id, 'amount' => '300.00', 'refunded_on' => '2026-11-01', 'reason' => 'Manual correction refund', 'idempotency_key' => 'refund-test-001'];
        $r = $this->service()->refund($d);
        $this->assertSame('requested', $r->status);
        $this->assertSame($r->id, $this->service()->refund($d)->id);
        $this->invalid(fn () => $this->service()->refund([...$d, 'idempotency_key' => 'refund-test-002']));
        $this->invalid(fn () => $this->service()->reverse($p->id, 'Cannot reverse pending refund'));
        $this->service()->refundAction($r->id, 'post');
        $this->assertSame(90000, $this->service()->balance($i)['outstanding']);
        $statement = iterator_to_array(app(FinanceReports::class)->rows(['report_type' => 'statement', 'student_id' => $f['student']->id, 'from' => '2026-11-01', 'to' => '2026-11-04']));
        $this->assertCount(1, $statement);
        $this->assertSame('Manual refund', $statement[0]['event']);
        $this->assertSame('posted', $p->fresh()->status);
        $this->assertSame(40000, $p->amount_minor);
    }

    public function test_expense_private_attachment_and_reports_dashboard_audit(): void
    {
        $this->local();
        Storage::fake('local');
        $f = $this->fixture();
        $i = $this->invoice($f);
        $this->service()->pay($this->payment($f, $i));
        $c = ExpenseCategory::create(['name' => 'Utilities', 'active' => true]);
        $d = ['expense_category_id' => $c->id, 'amount' => '25.50', 'spent_on' => '2026-11-01', 'payee' => '=Formula Vendor', 'description' => 'Test electricity', 'method' => 'cash', 'idempotency_key' => 'expense-test-001', 'attachment' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')];
        $e = $this->service()->expense($d);
        Storage::disk('local')->assertExists($e->attachment_path);
        $this->assertSame($e->id, $this->service()->expense($d)->id);
        $summary = app(FinanceReports::class)->summary();
        $this->assertSame(100000, $summary['billed_minor']);
        $this->assertSame(40000, $summary['collected_minor']);
        $this->assertSame(60000, $summary['overdue_minor']);
        $this->assertSame(2550, $summary['expense_minor']);
        $this->get($this->base.'/expenses/'.$e->id.'/attachment')->assertOk();
        $csv = $this->get($this->base.'/reports/export?report_type=expenses')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=Formula", $csv);
        $this->get($this->base.'/dashboard')->assertInertia(fn (Assert $a) => $a->component('Finance')->where('metrics.outstanding_minor', 60000));
        $this->assertTrue(AcademicAuditLog::where('operation', 'finance.payment-posted')->exists());
        $this->assertFalse(AcademicAuditLog::where('operation', 'finance.payment-posted')->first()->actor_context['authenticated']);
        app(TenantContext::class)->set(School::where('slug', 'demo-crescent')->firstOrFail());
        $this->assertSame(0, StudentInvoice::count());
        $this->get('/preview/schools/demo-crescent/finance/documents/receipt/'.StudentPayment::withoutGlobalScopes()->first()->id)->assertNotFound();
        $this->get('/preview/schools/demo-crescent/finance/expenses/'.$e->id.'/attachment')->assertNotFound();
    }

    public function test_invoice_state_machine_future_due_dates_and_void_history(): void
    {
        $this->local();
        $f = $this->fixture();
        $this->service()->assign(['fee_structure_id' => $f['structure']->id]);
        $d = $this->billing($f);
        $d['issue'] = false;
        $d['due_on'] = '2026-12-10';
        $i = $this->service()->generate($d)[0];
        $this->assertSame('draft', $this->service()->balance($i)['display_status']);
        $this->service()->invoiceAction($i->id, 'issue', []);
        $i->refresh();
        $this->assertSame('issued', $this->service()->balance($i)['display_status']);
        $this->assertSame(0, app(FinanceReports::class)->summary()['overdue_minor']);
        $this->service()->invoiceAction($i->id, 'void', ['reason' => 'Invoice entered incorrectly']);
        $this->assertSame(0, app(FinanceReports::class)->summary()['billed_minor']);
        $this->invalid(fn () => $this->service()->invoiceAction($i->id, 'issue', []));
    }

    public static function environments(): array
    {
        return [['production', true], ['staging', true], ['testing', true], ['local', false]];
    }

    #[DataProvider('environments')]
    public function test_finance_read_write_and_export_fail_closed(string $environment, bool $enabled): void
    {
        $this->app->instance('env', $environment);
        config(['preview.enabled' => true, 'preview.finance' => $enabled]);
        foreach (['get', 'post', 'put', 'delete'] as $method) {
            $this->{$method}($this->base.'/categories'.(in_array($method, ['put', 'delete']) ? '/1' : ''))->assertNotFound();
        }
        $this->get($this->base.'/reports/export')->assertNotFound();
    }

    public function test_invalid_school_inputs_and_money_are_rejected(): void
    {
        $this->local();
        $f = $this->fixture();
        $this->post($this->base.'/categories', ['name' => 'Hack', 'active' => true, 'school_id' => 99])->assertSessionHasErrors('school_id');
        $this->assertSame(101, Money::minor('1.01'));
        $this->assertSame(1, Money::percent(101, 100));
        $this->invalid(fn () => Money::minor('1.001'));
        $this->invalid(fn () => Money::minor('-1.00'));
        $this->get($this->base.'/reports?student_id=999999')->assertSessionHasErrors('student_id');
    }

    public function test_credit_can_be_allocated_later_without_rewriting_receipt_and_refund_reservations_block_it(): void
    {
        $this->local();
        $f = $this->fixture();
        $i = $this->invoice($f);
        $d = $this->payment($f, $i, '400.00');
        $d['allocations'] = [];
        $p = $this->service()->pay($d);
        $snapshot = PaymentReceipt::where('payment_id', $p->id)->firstOrFail()->snapshot;
        $this->post($this->base.'/payments/'.$p->id.'/allocate', ['confirm' => true, 'invoice_id' => $i->id, 'amount' => '300.00'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(70000, $this->service()->balance($i)['outstanding']);
        $this->assertSame(10000, $this->service()->credit($p));
        $this->assertSame($snapshot, PaymentReceipt::where('payment_id', $p->id)->firstOrFail()->snapshot);
        $r = $this->service()->refund(['payment_id' => $p->id, 'invoice_id' => null, 'amount' => '100.00', 'refunded_on' => '2026-11-01', 'reason' => 'Return unused student credit', 'idempotency_key' => 'credit-refund-01']);
        $this->assertSame(0, $this->service()->credit($p));
        $j = $this->service()->generate($this->billing($f, '2026-11-01'))[0];
        $this->invalid(fn () => $this->service()->allocateCredit($p->id, $j->id, '100.00'));
        $this->service()->refundAction($r->id, 'reject');
        $this->assertSame(10000, $this->service()->credit($p));
    }

    public function test_automatic_section_overrides_and_transfer_billing_preserve_history(): void
    {
        $this->local();
        $f = $this->fixture();
        $override = $this->service()->catalog('structures', [...$f['structure']->only(['academic_session_id', 'academic_class_id', 'fee_category_id', 'frequency', 'effective_from', 'effective_to', 'active']), 'section_id' => $f['section']->id, 'name' => 'Section override', 'amount' => '750.00']);
        $i = $this->service()->generate([...$this->billing($f), 'auto_assign' => true])[0];
        $this->assertSame(75000, $i->total_minor);
        $this->assertSame($override->id, FeeAssignment::first()->fee_structure_id);
        $f['enrollment']->update(['ended_on' => '2026-10-20', 'status' => 'transferred']);
        $newSection = Section::factory()->create(['academic_class_id' => $f['class']->id, 'code' => 'B']);
        Enrollment::create(['student_id' => $f['student']->id, 'academic_session_id' => $f['session']->id, 'academic_class_id' => $f['class']->id, 'section_id' => $newSection->id, 'started_on' => '2026-10-20', 'status' => 'active']);
        $j = $this->service()->generate([...$this->billing($f, '2026-11-01'), 'auto_assign' => true])[0];
        $this->assertSame(100000, $j->total_minor);
        $this->assertSame($newSection->id, $j->section_id);
        $this->assertSame(75000, $i->fresh()->total_minor);
        $this->assertSame($f['section']->id, $i->fresh()->section_id);
        $this->service()->assignmentStatus(FeeAssignment::orderByDesc('id')->first()->id, false);
        $this->assertCount(0, $this->service()->generate($this->billing($f, '2026-12-01')));
    }

    public function test_report_filters_category_class_and_statement_events(): void
    {
        $this->local();
        $f = $this->fixture();
        $i = $this->invoice($f);
        $this->service()->pay($this->payment($f, $i));
        $reports = app(FinanceReports::class);
        $this->assertSame(40000, $reports->summary(['academic_class_id' => $f['class']->id])['collected_minor']);
        $this->assertSame(0, $reports->summary(['academic_class_id' => 999999])['collected_minor']);
        $rows = iterator_to_array($reports->rows(['report_type' => 'category', 'fee_category_id' => $f['category']->id]));
        $this->assertSame('400.00', $rows[0]['gross_collection']);
        $statement = iterator_to_array($reports->rows(['report_type' => 'statement', 'student_id' => $f['student']->id]));
        $this->assertCount(2, $statement);
        $this->assertSame('Manual payment', $statement[1]['event']);
        $this->get('/preview/schools/demo-crescent/finance/dashboard')->assertInertia(fn (Assert $a) => $a->where('metrics.billed_minor', 0)->where('metrics.collected_minor', 0));
    }

    public function test_unassigned_structure_crud_and_billing_frequencies(): void
    {
        $this->local();
        $f = $this->fixture();
        $d = [...$f['structure']->only(['academic_session_id', 'academic_class_id', 'section_id', 'fee_category_id', 'effective_from', 'effective_to', 'active']), 'name' => 'Quarterly fee', 'amount' => '800.00', 'frequency' => 'quarterly'];
        $this->service()->catalog('structures', $d, $f['structure']->id);
        $this->assertSame('quarterly', $f['structure']->fresh()->frequency);
        $this->service()->assign(['fee_structure_id' => $f['structure']->id]);
        $this->assertCount(0, $this->service()->generate($this->billing($f)));
        $this->assertCount(1, $this->service()->generate($this->billing($f, '2026-11-01')));
        $c = FeeCategory::create(['name' => 'Unused configuration', 'active' => true]);
        $s = $this->service()->catalog('structures', [...$d, 'fee_category_id' => $c->id]);
        $this->delete($this->base.'/structures/'.$s->id)->assertRedirect();
        $this->assertNull(FeeStructure::find($s->id));
    }

    public function test_postgresql_guards_reject_cross_school_overpayments_and_snapshot_edits(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL trigger checks');
        }
        $this->local();
        $f = $this->fixture();
        $i = $this->invoice($f);
        $p = $this->service()->pay($this->payment($f, $i));
        $school = $this->service()->school()->id;
        foreach ([fn () => DB::table('student_invoices')->where('id', $i->id)->update(['total_minor' => 1]), fn () => DB::table('student_payments')->where('id', $p->id)->update(['amount_minor' => 1]), fn () => DB::table('payment_allocations')->insert(['school_id' => $school, 'payment_id' => $p->id, 'invoice_id' => $i->id, 'amount_minor' => 1000000]), fn () => DB::table('student_invoice_items')->where('invoice_id', $i->id)->delete(), fn () => DB::table('fee_categories')->insert(['school_id' => 999999, 'name' => 'Cross school'])] as $action) {
            try {
                DB::transaction($action);
                $this->fail('Database should reject invalid financial write');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
        try {
            DB::transaction(fn () => DB::table('enrollments')->where('id', $f['enrollment']->id)->update(['ended_on' => '2026-10-01', 'status' => 'transferred']));
            $this->fail('A transfer cannot invalidate an already billed day');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $this->assertSame(100000, $i->fresh()->total_minor);
    }

    public function test_private_expense_upload_validation_and_posting_endpoint(): void
    {
        $this->local();
        Storage::fake('local');
        $c = ExpenseCategory::create(['name' => 'Supplies', 'active' => true]);
        $d = ['expense_category_id' => $c->id, 'amount' => '250.00', 'spent_on' => '2026-11-01', 'payee' => 'QA Stationery', 'description' => 'Paper supplies', 'method' => 'cash', 'idempotency_key' => 'request-expense-1'];
        $this->post($this->base.'/expenses', [...$d, 'attachment' => UploadedFile::fake()->create('unsafe.exe', 10)])->assertSessionHasErrors('attachment');
        $this->post($this->base.'/expenses', $d)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, SchoolExpense::count());
        $this->post($this->base.'/expenses', $d)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, SchoolExpense::count());
    }

    public function test_postgresql_deferred_totals_reject_invoices_without_items(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Deferred invoice integrity is PostgreSQL-specific');
        }
        $this->local();
        $f = $this->fixture();
        $i = $this->invoice($f);
        try {
            DB::transaction(function () use ($i) {
                $row = $i->getAttributes();
                unset($row['id']);
                $row['number'] = 'INV-BAD-TOTAL';
                $row['period'] = '2026-12-01';
                DB::table('student_invoices')->insert($row);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Deferred invoice totals must reject an invoice with no items');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $this->assertSame(1, StudentInvoice::count());
    }
}

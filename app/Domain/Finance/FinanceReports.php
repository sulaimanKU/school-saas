<?php

namespace App\Domain\Finance;

use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Models\FeeCategory;
use App\Domain\Finance\Models\InvoiceItem;
use App\Domain\Finance\Models\InvoicePenalty;
use App\Domain\Finance\Models\PaymentAllocation;
use App\Domain\Finance\Models\SchoolExpense;
use App\Domain\Finance\Models\StudentInvoice;
use App\Domain\Finance\Models\StudentPayment;
use App\Domain\Finance\Models\StudentRefund;
use App\Modules\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class FinanceReports
{
    public function invoices(array $f = [])
    {
        $school = app(TenantContext::class)->school()->id;
        $paid = DB::table('payment_allocations as a')->join('student_payments as p', fn ($j) => $j->on('p.id', '=', 'a.payment_id')->on('p.school_id', '=', 'a.school_id'))->where('a.school_id', $school)->where('p.status', 'posted')->selectRaw('a.invoice_id, SUM(a.amount_minor) AS paid')->groupBy('a.invoice_id');
        $refund = DB::table('student_refunds')->where('school_id', $school)->where('status', 'posted')->selectRaw('invoice_id, SUM(amount_minor) AS refunded')->groupBy('invoice_id');
        $penalties = DB::table('invoice_penalties')->where('school_id', $school)->selectRaw('invoice_id, SUM(amount_minor) AS penalty')->groupBy('invoice_id');
        $q = StudentInvoice::query()->leftJoinSub($paid, 'pa', 'pa.invoice_id', '=', 'student_invoices.id')->leftJoinSub($refund, 'rf', 'rf.invoice_id', '=', 'student_invoices.id')->leftJoinSub($penalties, 'pn', 'pn.invoice_id', '=', 'student_invoices.id')
            ->select('student_invoices.*')->selectRaw('COALESCE(pa.paid,0) - COALESCE(rf.refunded,0) AS paid_minor, COALESCE(pn.penalty,0) AS penalty_minor, CASE WHEN student_invoices.status = \'voided\' THEN 0 ELSE total_minor + COALESCE(pn.penalty,0) - COALESCE(pa.paid,0) + COALESCE(rf.refunded,0) END AS outstanding_minor');
        foreach (['academic_session_id', 'academic_class_id', 'section_id', 'student_id'] as $key) {
            if (! empty($f[$key])) {
                $q->where('student_invoices.'.$key, $f[$key]);
            }
        }
        if (! empty($f['from'])) {
            $q->where('issued_on', '>=', $f['from']);
        } if (! empty($f['to'])) {
            $q->where('issued_on', '<=', $f['to']);
        }
        if (! empty($f['due_from'])) {
            $q->where('due_on', '>=', $f['due_from']);
        } if (! empty($f['due_to'])) {
            $q->where('due_on', '<=', $f['due_to']);
        }
        if (! empty($f['fee_category_id'])) {
            $q->whereIn('student_invoices.id', InvoiceItem::where('fee_category_id', $f['fee_category_id'])->select('invoice_id'));
        }
        if (! empty($f['q'])) {
            $q->where('number', 'like', '%'.$f['q'].'%');
        }
        $status = $f['status'] ?? null;
        if (in_array($status, ['draft', 'issued', 'voided'])) {
            $q->where('student_invoices.status', $status);
        }
        if (in_array($status, ['overdue', 'partially_paid', 'paid'])) {
            $q->where('student_invoices.status', 'issued');
            $balance = '(total_minor + COALESCE(pn.penalty,0) - COALESCE(pa.paid,0) + COALESCE(rf.refunded,0))';
            $q->whereRaw($balance.($status === 'paid' ? ' = 0' : ' > 0'));
            if ($status === 'overdue') {
                $q->where('due_on', '<', now('Asia/Karachi')->toDateString());
            }
            if ($status === 'partially_paid') {
                $q->whereRaw('(COALESCE(pa.paid,0) - COALESCE(rf.refunded,0)) > 0')->where('due_on', '>=', now('Asia/Karachi')->toDateString());
            }
        }

        return $q;
    }

    public function payments(array $f = [])
    {
        $q = StudentPayment::where('status', 'posted');
        foreach (['student_id', 'method'] as $key) {
            if (! empty($f[$key])) {
                $q->where($key, $f[$key]);
            }
        }
        if (! empty($f['from'])) {
            $q->where('paid_on', '>=', $f['from']);
        } if (! empty($f['to'])) {
            $q->where('paid_on', '<=', $f['to']);
        }
        if ($this->hasInvoiceFilters($f)) {
            $q->whereIn('id', PaymentAllocation::whereIn('invoice_id', $this->invoices($this->allocationFilters($f))->select('student_invoices.id'))->select('payment_id'));
        }

        return $q;
    }

    private function allocationFilters(array $f): array
    {
        return array_diff_key($f, array_flip(['from', 'to', 'q']));
    }

    private function hasInvoiceFilters(array $f): bool
    {
        foreach (['academic_session_id', 'academic_class_id', 'section_id', 'fee_category_id', 'status', 'due_from', 'due_to'] as $k) {
            if (! empty($f[$k])) {
                return true;
            }
        }

        return false;
    }

    private function collectedAmount(StudentPayment $p, array $f): int
    {
        if (! $this->hasInvoiceFilters($f)) {
            return $p->amount_minor;
        }
        $rows = PaymentAllocation::where('payment_id', $p->id)->whereIn('invoice_id', $this->invoices($this->allocationFilters($f))->select('student_invoices.id'))->get();
        $sum = 0;
        foreach ($rows as $a) {
            if (empty($f['fee_category_id'])) {
                $sum += $a->amount_minor;

                continue;
            }
            $i = StudentInvoice::findOrFail($a->invoice_id);
            $total = $i->total_minor + (int) InvoicePenalty::where('invoice_id', $i->id)->sum('amount_minor');
            foreach (InvoiceItem::where('invoice_id', $i->id)->where('fee_category_id', $f['fee_category_id'])->get() as $item) {
                $sum += $total > 0 ? intdiv($a->amount_minor * ($item->amount_minor - $item->discount_minor), $total) : 0;
            }
        }

        return $sum;
    }

    public function summary(array $f = []): array
    {
        $base = $this->invoices($f)->where('student_invoices.status', 'issued');
        $totals = DB::query()->fromSub($base->toBase(), 'finance_totals')->selectRaw('COALESCE(SUM(total_minor + penalty_minor),0) AS billed, COALESCE(SUM(paid_minor),0) AS paid, COALESCE(SUM(outstanding_minor),0) AS outstanding, COALESCE(SUM(discount_minor),0) AS discounts')->first();
        $billed = (int) $totals->billed;
        $paid = (int) $totals->paid;
        $outstanding = (int) $totals->outstanding;
        $today = now('Asia/Karachi')->toDateString();
        $aging = ['1–30' => 0, '31–60' => 0, '61–90' => 0, 'Over 90' => 0];
        foreach (['1–30' => [1, 30], '31–60' => [31, 60], '61–90' => [61, 90], 'Over 90' => [91, null]] as $label => [$min,$max]) {
            $bucket = DB::query()->fromSub($base->toBase(), 'aging')->where('outstanding_minor', '>', 0)->where('due_on', '<=', CarbonImmutable::parse($today)->subDays($min)->toDateString());
            if ($max) {
                $bucket->where('due_on', '>=', CarbonImmutable::parse($today)->subDays($max)->toDateString());
            }
            $aging[$label] = (int) $bucket->sum('outstanding_minor');
        }
        $expenses = SchoolExpense::where('status', 'posted')->where('currency', app(FinanceService::class)->currency());
        $refunds = StudentRefund::where('status', 'posted')->whereIn('payment_id', StudentPayment::where('currency', app(FinanceService::class)->currency())->select('id'));
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if (! empty($f[$key])) {
                $expenses->where('spent_on', $op, $f[$key]);
                $refunds->where('refunded_on', $op, $f[$key]);
            }
        }
        if (! empty($f['student_id'])) {
            $refunds->whereIn('payment_id', StudentPayment::where('student_id', $f['student_id'])->select('id'));
        }
        $payments = $this->payments($f)->where('currency', app(FinanceService::class)->currency());
        $collected = $this->hasInvoiceFilters($f) ? (int) $payments->get()->sum(fn ($p) => $this->collectedAmount($p, $f)) : (int) $payments->sum('amount_minor');

        return ['billed_minor' => $billed, 'allocated_minor' => $paid, 'collected_minor' => $collected, 'outstanding_minor' => $outstanding, 'overdue_minor' => array_sum($aging), 'discount_minor' => (int) $totals->discounts, 'refund_minor' => (int) $refunds->sum('amount_minor'), 'expense_minor' => (int) $expenses->sum('amount_minor'), 'collection_rate' => $billed ? intdiv($paid * 10000, $billed) / 100 : null, 'aging' => $aging];
    }

    public function rows(array $f): \Generator
    {
        $type = $f['report_type'] ?? 'invoices';
        if (in_array($type, ['class-outstanding', 'student-outstanding'])) {
            $groups = [];
            foreach ($this->invoices($f)->where('student_invoices.status', 'issued')->orderBy('student_invoices.id')->cursor() as $i) {
                $key = ($type === 'class-outstanding' ? $i->snapshot['class'] : $i->snapshot['student_name'].' · '.$i->snapshot['admission_number']).' ('.$i->currency.')';
                $groups[$key] = ($groups[$key] ?? 0) + (int) $i->outstanding_minor;
            }
            foreach ($groups as $group => $amount) {
                yield ['group' => $group, 'outstanding' => Money::format($amount)];
            }

            return;
        }
        if (in_array($type, ['invoices', 'outstanding', 'discounts', 'statement'])) {
            $q = $this->invoices($f)->orderBy('student_invoices.id');
            foreach ($q->cursor() as $i) {
                if ($type === 'outstanding' && ($i->status !== 'issued' || $i->outstanding_minor <= 0)) {
                    continue;
                }
                if ($type === 'discounts' && $i->discount_minor === 0) {
                    continue;
                }
                $status = match (true) {
                    $i->status !== 'issued' => $i->status, (int) $i->outstanding_minor === 0 => 'paid', $i->due_on < now('Asia/Karachi')->toDateString() => 'overdue', (int) $i->paid_minor > 0 => 'partially_paid', default => 'issued'
                };
                yield ['event' => 'Invoice', 'date' => $i->issued_on, 'reference' => $i->number, 'person' => $i->snapshot['student_name'], 'group' => $i->snapshot['class'].' / '.$i->snapshot['section'], 'currency' => $i->currency, 'amount' => Money::format($i->total_minor + $i->penalty_minor), 'paid' => Money::format((int) $i->paid_minor), 'outstanding' => Money::format((int) $i->outstanding_minor), 'discount' => Money::format($i->discount_minor), 'status' => $status];
            }
            if ($type === 'statement') {
                $payments = StudentPayment::where('student_id', $f['student_id']);
                foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
                    if (! empty($f[$key])) {
                        $payments->where('paid_on', $op, $f[$key]);
                    }
                }
                foreach ($payments->orderBy('id')->cursor() as $p) {
                    yield ['event' => 'Manual payment', 'date' => $p->paid_on, 'reference' => 'PAY-'.$p->id, 'person' => 'Student #'.$p->student_id, 'group' => $p->method, 'currency' => $p->currency, 'amount' => '', 'paid' => Money::format($p->amount_minor), 'outstanding' => '', 'discount' => '', 'status' => $p->status];
                }
                $reversals = StudentPayment::where('student_id', $f['student_id'])->where('status', 'reversed');
                $refunds = StudentRefund::whereIn('payment_id', StudentPayment::where('student_id', $f['student_id'])->select('id'))->where('status', 'posted');
                foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
                    if (! empty($f[$key])) {
                        $reversals->whereDate('updated_at', $op, $f[$key]);
                        $refunds->where('refunded_on', $op, $f[$key]);
                    }
                }
                foreach ($reversals->orderBy('id')->cursor() as $p) {
                    yield ['event' => 'Payment reversal', 'date' => $p->updated_at->toDateString(), 'reference' => 'PAY-'.$p->id, 'person' => '', 'group' => '', 'currency' => $p->currency, 'amount' => '', 'paid' => Money::format(-$p->amount_minor), 'outstanding' => '', 'discount' => '', 'status' => 'reversed'];
                }
                foreach ($refunds->orderBy('id')->cursor() as $r) {
                    $p = StudentPayment::findOrFail($r->payment_id);
                    yield ['event' => 'Manual refund', 'date' => $r->refunded_on, 'reference' => 'REF-'.$r->id, 'person' => '', 'group' => '', 'currency' => $p->currency, 'amount' => '', 'paid' => Money::format(-$r->amount_minor), 'outstanding' => '', 'discount' => '', 'status' => 'posted'];
                }
            }

            return;
        }
        if (in_array($type, ['expenses', 'expense-categories'])) {
            $q = SchoolExpense::query();
            foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
                if (! empty($f[$key])) {
                    $q->where('spent_on', $op, $f[$key]);
                }
            } if (! empty($f['method'])) {
                $q->where('method', $f['method']);
            } if (! empty($f['expense_category_id'])) {
                $q->where('expense_category_id', $f['expense_category_id']);
            }
            $groups = [];
            foreach ($q->orderBy('id')->cursor() as $e) {
                $category = ExpenseCategory::findOrFail($e->expense_category_id)->name;
                if ($type === 'expense-categories') {
                    $key = $category.' ('.$e->currency.')';
                    $groups[$key] = ($groups[$key] ?? 0) + $e->amount_minor;
                } else {
                    yield ['date' => $e->spent_on, 'reference' => 'EXP-'.$e->id, 'person' => $e->payee, 'group' => $category, 'currency' => $e->currency, 'amount' => Money::format($e->amount_minor), 'method' => $e->method, 'status' => $e->status];
                }
            }
            foreach ($groups as $group => $amount) {
                yield ['category' => $group, 'expense' => Money::format($amount)];
            }

            return;
        }
        if ($type === 'refunds') {
            $q = StudentRefund::query();
            foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
                if (! empty($f[$key])) {
                    $q->where('refunded_on', $op, $f[$key]);
                }
            } if (! empty($f['student_id'])) {
                $q->whereIn('payment_id', StudentPayment::where('student_id', $f['student_id'])->select('id'));
            }
            foreach ($q->orderBy('id')->cursor() as $r) {
                yield ['date' => $r->refunded_on, 'reference' => 'REF-'.$r->id, 'payment' => $r->payment_id, 'invoice' => $r->invoice_id ?? 'Unallocated credit', 'amount' => Money::format($r->amount_minor), 'status' => $r->status];
            }

            return;
        }
        $q = $this->payments($f)->orderBy('id');
        $groups = [];
        foreach ($q->cursor() as $p) {
            if ($type === 'payments') {
                yield ['date' => $p->paid_on, 'reference' => 'PAY-'.$p->id, 'student' => $p->student_id, 'currency' => $p->currency, 'amount' => Money::format($this->collectedAmount($p, $f)), 'method' => $p->method, 'status' => 'Manually recorded'];

                continue;
            }
            $key = match ($type) {
                'daily' => $p->paid_on, 'monthly' => substr($p->paid_on, 0, 7), default => $p->method
            };
            if (in_array($type, ['class', 'category'])) {
                $allocations = PaymentAllocation::where('payment_id', $p->id)->whereIn('invoice_id', $this->invoices(array_diff_key($f, array_flip(['from', 'to'])))->select('student_invoices.id'))->get();
                foreach ($allocations as $a) {
                    $i = StudentInvoice::findOrFail($a->invoice_id);
                    if ($type === 'class') {
                        $k = $i->snapshot['class'].' ('.$p->currency.')';
                        $groups[$k] = ($groups[$k] ?? 0) + $a->amount_minor;
                    } else {
                        $items = InvoiceItem::where('invoice_id', $i->id)->orderBy('id')->get();
                        $base = $i->total_minor + (int) InvoicePenalty::where('invoice_id', $i->id)->sum('amount_minor');
                        $remaining = $a->amount_minor;
                        foreach ($items as $item) {
                            $share = $base > 0 ? intdiv($a->amount_minor * ($item->amount_minor - $item->discount_minor), $base) : 0;
                            $k = FeeCategory::findOrFail($item->fee_category_id)->name.' ('.$p->currency.')';
                            if (empty($f['fee_category_id']) || $item->fee_category_id === (int) $f['fee_category_id']) {
                                $groups[$k] = ($groups[$k] ?? 0) + $share;
                            } $remaining -= $share;
                        }
                        if (empty($f['fee_category_id'])) {
                            $k = 'Penalty / allocation rounding ('.$p->currency.')';
                            $groups[$k] = ($groups[$k] ?? 0) + $remaining;
                        }
                    }
                }
            } else {
                $key .= ' ('.$p->currency.')';
                $groups[$key] = ($groups[$key] ?? 0) + $this->collectedAmount($p, $f);
            }
        }
        foreach ($groups as $group => $amount) {
            yield ['group' => $group, 'gross_collection' => Money::format($amount)];
        }
    }

    public function dashboard(): array
    {
        $service = app(FinanceService::class);
        $summary = $this->summary();
        $months = [];
        $collected = [];
        $expenses = [];
        for ($n = 5; $n >= 0; $n--) {
            $month = now('Asia/Karachi')->startOfMonth()->subMonths($n);
            $months[] = $month->format('M Y');
            $collected[] = (int) StudentPayment::where('status', 'posted')->where('currency', $service->currency())->whereBetween('paid_on', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->sum('amount_minor');
            $expenses[] = (int) SchoolExpense::where('currency', $service->currency())->whereBetween('spent_on', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->sum('amount_minor');
        }
        $top = $this->invoices(['status' => 'overdue'])->get()->groupBy('student_id')->map(fn ($rows) => ['student' => $rows->first()->snapshot['student_name'], 'amount_minor' => (int) $rows->sum('outstanding_minor')])->sortByDesc('amount_minor')->take(5)->values();

        return [...$summary, 'currency' => $service->currency(), 'months' => $months, 'collections' => $collected, 'expenses' => $expenses, 'top' => $top, 'recent' => StudentPayment::orderByDesc('id')->limit(5)->get(['id', 'paid_on', 'amount_minor', 'currency', 'method', 'status'])];
    }
}

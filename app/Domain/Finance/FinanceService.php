<?php

namespace App\Domain\Finance;

use App\Domain\Finance\Models\FeeAssignment;
use App\Domain\Finance\Models\FeeCategory;
use App\Domain\Finance\Models\FeeStructure;
use App\Domain\Finance\Models\InvoiceItem;
use App\Domain\Finance\Models\InvoicePenalty;
use App\Domain\Finance\Models\LateFeePolicy;
use App\Domain\Finance\Models\PaymentAllocation;
use App\Domain\Finance\Models\PaymentReceipt;
use App\Domain\Finance\Models\SchoolExpense;
use App\Domain\Finance\Models\StudentDiscount;
use App\Domain\Finance\Models\StudentInvoice;
use App\Domain\Finance\Models\StudentPayment;
use App\Domain\Finance\Models\StudentRefund;
use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Operations\Models\AcademicAuditLog;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class FinanceService
{
    public function school(): School
    {
        return app(TenantContext::class)->school();
    }

    public function currency(): string
    {
        return $this->school()->settings()->where('key', 'finance')->first()?->value['currency'] ?? 'PKR';
    }

    public function transaction(callable $work): mixed
    {
        return DB::transaction(function () use ($work) {
            DB::table('schools')->where('id', $this->school()->id)->lockForUpdate()->first();

            return $work();
        }, 3);
    }

    public function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['finance' => $message]);
        }
    }

    public function audit(string $action, $record, array $before = [], ?string $reason = null): void
    {
        AcademicAuditLog::create(['operation' => 'finance.'.$action, 'entity_type' => $record->getTable(), 'entity_id' => $record->id, 'actor_context' => ['mode' => 'local-development-preview', 'authenticated' => false], 'changes' => ['before' => $before, 'after' => array_intersect_key($record->getAttributes(), array_flip(['status', 'amount_minor', 'total_minor', 'discount_minor', 'currency', 'student_id', 'invoice_id', 'payment_id', 'active', 'value', 'calculation']))], 'reason' => $reason]);
    }

    private function number(string $kind): string
    {
        DB::table('finance_counters')->insertOrIgnore(['school_id' => $this->school()->id, 'kind' => $kind, 'next_number' => 1]);
        $q = DB::table('finance_counters')->where('school_id', $this->school()->id)->where('kind', $kind);
        $n = $q->lockForUpdate()->value('next_number');
        $q->increment('next_number');

        return strtoupper($kind).'-'.str_pad((string) $n, 7, '0', STR_PAD_LEFT);
    }

    public function catalog(string $resource, array $data, ?int $id = null)
    {
        return $this->transaction(function () use ($resource, $data, $id) {
            $class = FinanceCatalog::MODELS[$resource];
            $record = $id ? $class::findOrFail($id) : new $class;
            if (isset($data['amount'])) {
                $data['amount_minor'] = Money::minor($data['amount']);
                unset($data['amount']);
            }
            if (isset($data['cap'])) {
                $data['cap_minor'] = Money::minor($data['cap']);
                unset($data['cap']);
            }
            if (in_array($resource, ['discounts', 'late-fees'])) {
                $data['value'] = Money::minor($data['value']);
                $this->require($data['value'] > 0 && ($data['calculation'] !== 'percent' || $data['value'] <= 10000), 'Percentage must be greater than zero and at most 100.');
            }
            if ($resource === 'late-fees') {
                $this->require($data['cap_minor'] > 0, 'Penalty cap must be positive.');
            }
            if ($resource === 'structures') {
                $this->require($data['amount_minor'] > 0, 'Fee amount must be positive.');
                $data['currency'] = $this->currency();
                $session = AcademicSession::findOrFail($data['academic_session_id']);
                $this->require($data['effective_from'] >= substr($session->starts_on, 0, 10) && $data['effective_to'] <= substr($session->ends_on, 0, 10), 'Fee effective dates must lie within the academic session.');
                if (! empty($data['section_id'])) {
                    $this->require(Section::findOrFail($data['section_id'])->academic_class_id === (int) $data['academic_class_id'], 'Section must belong to the selected class.');
                }
                if ($id) {
                    $this->require(! FeeAssignment::where('fee_structure_id', $id)->exists(), 'Assigned structures are immutable. Create a new effective version.');
                }
                $this->require(! FeeStructure::where('academic_session_id', $data['academic_session_id'])->where('academic_class_id', $data['academic_class_id'])->where('section_id', $data['section_id'] ?? null)->where('fee_category_id', $data['fee_category_id'])->where('active', true)->when($id, fn ($q) => $q->where('id', '!=', $id))->where('effective_from', '<=', $data['effective_to'])->where('effective_to', '>=', $data['effective_from'])->exists() || ! ($data['active'] ?? true), 'An active structure already covers this category and effective period.');
            }
            if ($resource === 'late-fees' && $id) {
                $this->require(! InvoicePenalty::where('policy_id', $id)->exists(), 'Posted policies are immutable. Create another policy.');
            }
            $before = $record->only(['active', 'value', 'amount_minor']);
            $record->fill($data)->save();
            $this->audit('configuration', $record, $before);

            return $record;
        });
    }

    public function remove(string $resource, int $id): void
    {
        $this->transaction(function () use ($resource, $id) {
            $class = FinanceCatalog::MODELS[$resource];
            $record = $class::findOrFail($id);
            $this->audit('configuration-delete', $record);
            $record->delete();
        });
    }

    public function assign(array $d): int
    {
        return $this->transaction(function () use ($d) {
            $s = FeeStructure::findOrFail($d['fee_structure_id']);
            $this->require($s->active && FeeCategory::findOrFail($s->fee_category_id)->active, 'Select an active structure and fee category.');
            $roster = Enrollment::where('academic_session_id', $s->academic_session_id)->where('academic_class_id', $s->academic_class_id)->where('started_on', '<=', $s->effective_to)->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $s->effective_from))->when($s->section_id, fn ($q) => $q->where('section_id', $s->section_id))->when(! empty($d['section_id']), fn ($q) => $q->where('section_id', $d['section_id']))->when(! empty($d['student_id']), fn ($q) => $q->where('student_id', $d['student_id']))->orderBy('started_on')->get();
            $this->require($roster->isNotEmpty(), 'No eligible enrollments match this assignment.');
            $count = 0;
            foreach ($roster as $e) {
                if (FeeAssignment::where('enrollment_id', $e->id)->where('fee_structure_id', $s->id)->exists()) {
                    continue;
                }
                $from = max($s->effective_from, substr($e->started_on, 0, 10));
                $to = min($s->effective_to, $this->enrollmentEnd($e) ?? $s->effective_to);
                if ($from > $to) {
                    continue;
                }
                $overlaps = FeeAssignment::where('student_id', $e->student_id)->where('academic_session_id', $s->academic_session_id)->where('fee_category_id', $s->fee_category_id)->where('starts_on', '<=', $to)->where('ends_on', '>=', $from)->get()->contains(function ($a) use ($from) {
                    $old = Enrollment::findOrFail($a->enrollment_id);

                    return min($a->ends_on, $this->enrollmentEnd($old) ?? $a->ends_on) >= $from;
                });
                $this->require(! $overlaps, 'A student already has this category for an overlapping period. Section overrides must be assigned instead of the class default.');
                $a = FeeAssignment::create(['student_id' => $e->student_id, 'enrollment_id' => $e->id, 'academic_session_id' => $s->academic_session_id, 'fee_structure_id' => $s->id, 'fee_category_id' => $s->fee_category_id, 'amount_minor' => ! empty($d['adjusted_amount']) ? Money::minor($d['adjusted_amount']) : $s->amount_minor, 'currency' => $s->currency, 'frequency' => $s->frequency, 'starts_on' => $from, 'ends_on' => $to, 'active' => true, 'snapshot' => ['structure' => $s->toArray(), 'class_id' => $e->academic_class_id, 'section_id' => $e->section_id]]);
                $this->require($a->amount_minor > 0, 'Assignment amount must be positive.');
                $this->audit('assignment', $a);
                $count++;
            }

            return $count;
        });
    }

    private function enrollmentEnd(Enrollment $e): ?string
    {
        if (! $e->ended_on) {
            return null;
        }

        return Date::parse($e->ended_on)->subDays(in_array($e->status, ['promoted', 'completed']) ? 0 : 1)->toDateString();
    }

    private function obligation(FeeAssignment $a, string $period): ?string
    {
        $p = Date::parse($period)->startOfMonth();
        $start = Date::parse($a->frequency === 'one_time' ? $a->starts_on : $a->snapshot['structure']['effective_from'])->startOfMonth();
        if ($p->endOfMonth()->toDateString() < $a->starts_on || $p->toDateString() > $a->ends_on) {
            return null;
        }
        $months = (int) $start->diffInMonths($p);
        $step = match ($a->frequency) {
            'monthly' => 1, 'quarterly' => 3, 'annual' => 12, default => 0
        };
        if (($step === 0 && $months !== 0) || ($step > 0 && $months % $step !== 0)) {
            return null;
        }

        return $p->toDateString();
    }

    public function generate(array $d): array
    {
        return $this->transaction(function () use ($d) {
            $period = Date::parse($d['period'])->startOfMonth()->toDateString();
            $session = AcademicSession::findOrFail($d['academic_session_id']);
            $this->require($period <= substr($session->ends_on, 0, 10) && Date::parse($period)->endOfMonth()->toDateString() >= substr($session->starts_on, 0, 10), 'Billing period is outside the session.');
            if (! empty($d['auto_assign'])) {
                $this->automaticAssignments($d, $period);
            }
            $assignments = FeeAssignment::where('academic_session_id', $session->id)->where('active', true)->when(! empty($d['student_id']), fn ($q) => $q->where('student_id', $d['student_id']))->get()->filter(function ($a) use ($d) {
                return (empty($d['academic_class_id']) || $a->snapshot['class_id'] === (int) $d['academic_class_id']) && (empty($d['section_id']) || $a->snapshot['section_id'] === (int) $d['section_id']);
            });
            $created = [];
            foreach ($assignments->groupBy('student_id') as $studentId => $fees) {
                $existing = StudentInvoice::where('student_id', $studentId)->where('academic_session_id', $session->id)->where('period', $period)->first();
                if ($existing) {
                    $created[] = $existing;

                    continue;
                }
                $items = [];
                $subtotal = 0;
                $discount = 0;
                foreach ($fees->sortBy('starts_on') as $a) {
                    $obligation = $this->obligation($a, $period);
                    if (! $obligation) {
                        continue;
                    }
                    if (collect($items)->contains('fee_category_id', $a->fee_category_id)) {
                        continue;
                    }
                    if ($a->frequency === 'one_time' && InvoiceItem::where('student_id', $studentId)->where('academic_session_id', $session->id)->where('fee_category_id', $a->fee_category_id)->exists()) {
                        continue;
                    }
                    $e = Enrollment::findOrFail($a->enrollment_id);
                    $asOf = max($period, $a->starts_on);
                    if (substr($e->started_on, 0, 10) > $asOf || ($this->enrollmentEnd($e) && $this->enrollmentEnd($e) < $asOf)) {
                        continue;
                    }
                    $concessions = StudentDiscount::where('student_id', $studentId)->where('academic_session_id', $session->id)->where('active', true)->where('starts_on', '<=', $asOf)->where('ends_on', '>=', $asOf)->where(fn ($q) => $q->whereNull('fee_category_id')->orWhere('fee_category_id', $a->fee_category_id))->get();
                    $best = 0;
                    $chosen = null;
                    foreach ($concessions as $c) {
                        $v = min($a->amount_minor, $c->calculation === 'fixed' ? $c->value : Money::percent($a->amount_minor, $c->value));
                        if ($v > $best) {
                            $best = $v;
                            $chosen = $c->toArray();
                        }
                    }
                    $items[] = ['student_id' => $studentId, 'academic_session_id' => $session->id, 'assignment_id' => $a->id, 'fee_category_id' => $a->fee_category_id, 'obligation' => $obligation, 'description' => $a->snapshot['structure']['name'], 'amount_minor' => $a->amount_minor, 'discount_minor' => $best, 'snapshot' => ['assignment' => $a->toArray(), 'discount' => $chosen, 'stacking' => 'largest eligible concession per item; no stacking']];
                    $subtotal += $a->amount_minor;
                    $discount += $best;
                }
                if (! $items) {
                    continue;
                }
                $first = FeeAssignment::findOrFail($items[0]['assignment_id']);
                $e = Enrollment::findOrFail($first->enrollment_id);
                $student = Student::withTrashed()->findOrFail($studentId);
                $this->require($fees->every(fn ($a) => $a->currency === $this->currency()), 'Currency differs from assigned fees. Historical currency cannot be converted implicitly.');
                $invoice = StudentInvoice::create(['student_id' => $studentId, 'enrollment_id' => $e->id, 'academic_session_id' => $session->id, 'academic_class_id' => $e->academic_class_id, 'section_id' => $e->section_id, 'number' => $this->number('inv'), 'period' => $period, 'issued_on' => $d['issued_on'], 'due_on' => $d['due_on'], 'status' => ! empty($d['issue']) ? 'issued' : 'draft', 'currency' => $this->currency(), 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'total_minor' => $subtotal - $discount, 'late_fee_exempt' => $d['late_fee_exempt'] ?? false, 'snapshot' => ['school' => $this->schoolSnapshot(), 'student_name' => $student->full_name, 'admission_number' => $student->admission_number, 'class' => AcademicClass::findOrFail($e->academic_class_id)->name, 'section' => Section::findOrFail($e->section_id)->name, 'session' => $session->name]]);
                foreach ($items as $item) {
                    InvoiceItem::create(['invoice_id' => $invoice->id, ...$item]);
                }
                $this->audit('invoice-created', $invoice);
                if ($invoice->status === 'issued') {
                    $this->audit('invoice-issued', $invoice);
                } if ($discount) {
                    $this->audit('discount-applied', $invoice);
                }
                $created[] = $invoice;
            }

            return $created;
        });
    }

    private function automaticAssignments(array $d, string $period): void
    {
        $structures = FeeStructure::where('academic_session_id', $d['academic_session_id'])->where('active', true)->where('effective_from', '<=', Date::parse($period)->endOfMonth()->toDateString())->where('effective_to', '>=', $period)->get();
        $roster = Enrollment::where('academic_session_id', $d['academic_session_id'])->where('started_on', '<=', Date::parse($period)->endOfMonth()->toDateString())->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $period))->when(! empty($d['student_id']), fn ($q) => $q->where('student_id', $d['student_id']))->when(! empty($d['academic_class_id']), fn ($q) => $q->where('academic_class_id', $d['academic_class_id']))->when(! empty($d['section_id']), fn ($q) => $q->where('section_id', $d['section_id']))->get();
        foreach ($roster as $e) {
            foreach ($structures->filter(fn ($s) => $s->academic_class_id === $e->academic_class_id && (! $s->section_id || $s->section_id === $e->section_id))->groupBy('fee_category_id') as $fees) {
                $s = $fees->sortByDesc(fn ($s) => $s->section_id ? 1 : 0)->first();
                $this->assign(['fee_structure_id' => $s->id, 'student_id' => $e->student_id, 'section_id' => $e->section_id]);
            }
        }
    }

    public function credit(StudentPayment $p): int
    {
        if ($p->status !== 'posted') {
            return 0;
        }

        return $p->amount_minor - (int) PaymentAllocation::where('payment_id', $p->id)->sum('amount_minor') - (int) StudentRefund::where('payment_id', $p->id)->whereNull('invoice_id')->whereIn('status', ['requested', 'posted'])->sum('amount_minor');
    }

    public function assignmentStatus(int $id, bool $active): void
    {
        $this->transaction(function () use ($id, $active) {
            $a = FeeAssignment::findOrFail($id);
            $before = ['active' => $a->active];
            $a->update(['active' => $active]);
            $this->audit('assignment-status', $a, $before);
        });
    }

    public function allocateCredit(int $paymentId, int $invoiceId, string $amount): void
    {
        $this->transaction(function () use ($paymentId, $invoiceId, $amount) {
            $p = StudentPayment::lockForUpdate()->findOrFail($paymentId);
            $i = StudentInvoice::lockForUpdate()->findOrFail($invoiceId);
            $value = Money::minor($amount);
            $existing = PaymentAllocation::where('payment_id', $p->id)->where('invoice_id', $i->id)->first();
            if ($existing) {
                $this->require($existing->amount_minor === $value, 'This payment already has a different allocation to that invoice.');

                return;
            }
            $this->require($p->status === 'posted' && $i->status === 'issued' && $p->student_id === $i->student_id && $p->currency === $i->currency && $value > 0 && $value <= $this->credit($p) && $value <= $this->balance($i)['outstanding'], 'Allocation exceeds available student credit or invoice balance, or the student/currency differs.');
            $a = PaymentAllocation::create(['payment_id' => $p->id, 'invoice_id' => $i->id, 'amount_minor' => $value]);
            $this->audit('credit-allocated', $a);
        });
    }

    private function schoolSnapshot(): array
    {
        $general = $this->school()->settings()->where('key', 'general')->first()?->value ?? [];
        $logo = $general['logo_path'] ?? '/images/school-emblem.svg';
        if (! preg_match('~^/(images|storage)/[a-zA-Z0-9/_.-]+$~', $logo)) {
            $logo = '/images/school-emblem.svg';
        }

        return ['name' => $this->school()->name, 'city' => $this->school()->city, 'address' => $general['address'] ?? '', 'phone' => $general['phone'] ?? '', 'logo' => $logo];
    }

    public function balance(StudentInvoice $i): array
    {
        $penalty = (int) InvoicePenalty::where('invoice_id', $i->id)->sum('amount_minor');
        $paid = (int) PaymentAllocation::where('invoice_id', $i->id)->whereIn('payment_id', StudentPayment::where('status', 'posted')->select('id'))->sum('amount_minor');
        $refunded = (int) StudentRefund::where('invoice_id', $i->id)->where('status', 'posted')->sum('amount_minor');
        $paid -= $refunded;
        $total = $i->total_minor + $penalty;
        $outstanding = $i->status === 'voided' ? 0 : $total - $paid;
        $status = match (true) {
            $i->status !== 'issued' => $i->status, $outstanding === 0 => 'paid', $i->due_on < now('Asia/Karachi')->toDateString() => 'overdue', $paid > 0 => 'partially_paid', default => 'issued'
        };

        return ['total' => $total, 'penalty' => $penalty, 'paid' => $paid, 'outstanding' => $outstanding, 'display_status' => $status];
    }

    public function invoiceAction(int $id, string $action, array $d): void
    {
        $this->transaction(function () use ($id, $action, $d) {
            $i = StudentInvoice::lockForUpdate()->findOrFail($id);
            if ($action === 'issue') {
                $this->require($i->status === 'draft', 'Only a draft invoice can be issued.');
                $i->update(['status' => 'issued']);
            } else {
                $this->require($i->status !== 'voided' && ! PaymentAllocation::where('invoice_id', $id)->exists() && ! InvoicePenalty::where('invoice_id', $id)->exists(), 'Only invoices without payment history or penalties can be voided.');
                $i->update(['status' => 'voided', 'void_reason' => $d['reason']]);
            }
            $this->audit('invoice-'.$action, $i, [], $d['reason'] ?? null);
        });
    }

    public function pay(array $d): StudentPayment
    {
        return $this->transaction(function () use ($d) {
            $hash = hash('sha256', json_encode($d));
            $existing = StudentPayment::where('idempotency_key', $d['idempotency_key'])->first();
            if ($existing) {
                $this->require(hash_equals($existing->request_hash, $hash), 'Idempotency key was already used for different payment details.');

                return $existing;
            }
            $amount = Money::minor($d['amount']);
            $this->require($amount > 0, 'Payment must be positive.');
            $currency = $this->currency();
            $sum = 0;
            $allocations = [];
            foreach ($d['allocations'] ?? [] as $row) {
                $i = StudentInvoice::lockForUpdate()->findOrFail($row['invoice_id']);
                $v = Money::minor($row['amount']);
                $this->require(! isset($allocations[$i->id]) && $i->status === 'issued' && $i->student_id === (int) $d['student_id'] && $i->currency === $currency && $v > 0 && $v <= $this->balance($i)['outstanding'], 'Allocation must target this student’s issued invoice, match currency, and not exceed its balance.');
                $allocations[$i->id] = $v;
                $sum += $v;
            }
            $this->require($sum <= $amount, 'Allocations cannot exceed the payment amount.');
            Student::withTrashed()->findOrFail($d['student_id']);
            $p = StudentPayment::create(['student_id' => $d['student_id'], 'paid_on' => $d['paid_on'], 'amount_minor' => $amount, 'currency' => $currency, 'method' => $d['method'], 'reference' => $d['reference'] ?? null, 'notes' => $d['notes'] ?? null, 'idempotency_key' => $d['idempotency_key'], 'request_hash' => $hash, 'status' => 'posted']);
            $refs = [];
            foreach ($allocations as $invoiceId => $value) {
                PaymentAllocation::create(['payment_id' => $p->id, 'invoice_id' => $invoiceId, 'amount_minor' => $value]);
                $i = StudentInvoice::findOrFail($invoiceId);
                $refs[] = ['invoice' => $i->number, 'amount_minor' => $value, 'remaining_minor' => $this->balance($i)['outstanding']];
            }
            $student = Student::withTrashed()->findOrFail($p->student_id);
            $enrollment = Enrollment::where('student_id', $student->id)->orderByDesc('started_on')->first();
            PaymentReceipt::create(['payment_id' => $p->id, 'number' => $this->number('rcpt'), 'snapshot' => ['school' => $this->schoolSnapshot(), 'student_name' => $student->full_name, 'admission_number' => $student->admission_number, 'class' => $enrollment ? AcademicClass::findOrFail($enrollment->academic_class_id)->name : '', 'section' => $enrollment ? Section::findOrFail($enrollment->section_id)->name : '', 'paid_on' => $p->paid_on, 'method' => $p->method, 'currency' => $currency, 'amount_minor' => $amount, 'unallocated_minor' => $amount - $sum, 'allocations' => $refs, 'verification' => 'Manually recorded; bank or cheque not independently verified']]);
            $this->audit('payment-posted', $p);

            return $p;
        });
    }

    public function reverse(int $id, string $reason): void
    {
        $this->transaction(function () use ($id, $reason) {
            $p = StudentPayment::lockForUpdate()->findOrFail($id);
            $this->require($p->status === 'posted' && ! StudentRefund::where('payment_id', $id)->whereIn('status', ['requested', 'posted'])->exists(), 'Only posted payments without pending or posted refunds can be reversed.');
            $p->update(['status' => 'reversed', 'reversal_reason' => $reason]);
            $this->audit('payment-reversed', $p, ['status' => 'posted'], $reason);
        });
    }

    public function refund(array $d): StudentRefund
    {
        return $this->transaction(function () use ($d) {
            $existing = StudentRefund::where('idempotency_key', $d['idempotency_key'])->first();
            if ($existing) {
                $this->require($existing->payment_id === (int) $d['payment_id'] && $existing->amount_minor === Money::minor($d['amount']) && $existing->invoice_id === (empty($d['invoice_id']) ? null : (int) $d['invoice_id']) && $existing->reason === $d['reason'] && $existing->refunded_on === $d['refunded_on'], 'Refund key already used with different details.');

                return $existing;
            }
            $p = StudentPayment::lockForUpdate()->findOrFail($d['payment_id']);
            $amount = Money::minor($d['amount']);
            $invoiceId = $d['invoice_id'] ?? null;
            $this->require($p->status === 'posted', 'Only posted payments can be refunded.');
            $eligible = $invoiceId ? (int) PaymentAllocation::where('payment_id', $p->id)->where('invoice_id', $invoiceId)->sum('amount_minor') : $p->amount_minor - (int) PaymentAllocation::where('payment_id', $p->id)->sum('amount_minor');
            $reserved = (int) StudentRefund::where('payment_id', $p->id)->where('invoice_id', $invoiceId)->whereIn('status', ['requested', 'posted'])->sum('amount_minor');
            $this->require($amount > 0 && $amount <= $eligible - $reserved, 'Refund exceeds the eligible allocated payment or unallocated credit after earlier refunds.');
            $r = StudentRefund::create(['payment_id' => $p->id, 'invoice_id' => $invoiceId, 'amount_minor' => $amount, 'refunded_on' => $d['refunded_on'], 'reason' => $d['reason'], 'status' => 'requested', 'idempotency_key' => $d['idempotency_key']]);
            $this->audit('refund-requested', $r, [], $d['reason']);

            return $r;
        });
    }

    public function refundAction(int $id, string $action): void
    {
        $this->transaction(function () use ($id, $action) {
            $r = StudentRefund::lockForUpdate()->findOrFail($id);
            $this->require($r->status === 'requested', 'Only pending refund requests can be processed.');
            $r->update(['status' => $action === 'post' ? 'posted' : 'rejected']);
            $this->audit('refund-'.$action, $r, [], $r->reason);
        });
    }

    public function penalty(int $invoiceId, int $policyId, bool $post = false): array
    {
        return $this->transaction(function () use ($invoiceId, $policyId, $post) {
            $i = StudentInvoice::lockForUpdate()->findOrFail($invoiceId);
            $p = LateFeePolicy::findOrFail($policyId);
            $existing = InvoicePenalty::where('invoice_id', $invoiceId)->first();
            if ($existing) {
                return ['amount_minor' => $existing->amount_minor, 'already_posted' => true];
            }
            $b = $this->balance($i);
            $eligible = $p->active && $i->status === 'issued' && ! $i->late_fee_exempt && $b['outstanding'] > 0 && Date::parse($i->due_on)->addDays($p->grace_days)->toDateString() < now('Asia/Karachi')->toDateString();
            $value = $eligible ? min($p->cap_minor, $p->calculation === 'fixed' ? $p->value : Money::percent($b['outstanding'], $p->value)) : 0;
            if ($post) {
                $this->require($value > 0, 'No penalty is eligible.');
                $this->require($i->currency === $this->currency(), 'Penalty policy currency differs from invoice.');
                $entry = InvoicePenalty::create(['invoice_id' => $i->id, 'policy_id' => $p->id, 'posted_on' => now('Asia/Karachi')->toDateString(), 'amount_minor' => $value, 'snapshot' => ['policy' => $p->toArray(), 'base_minor' => $b['outstanding']]]);
                $this->audit('late-fee-posted', $entry);
            }

            return ['amount_minor' => $value, 'already_posted' => false];
        });
    }

    public function expense(array $d): SchoolExpense
    {
        return $this->transaction(function () use ($d) {
            $existing = SchoolExpense::where('idempotency_key', $d['idempotency_key'])->first();
            if ($existing) {
                $this->require($existing->amount_minor === Money::minor($d['amount']) && $existing->payee === $d['payee'] && $existing->description === $d['description'] && $existing->method === $d['method'] && $existing->spent_on === $d['spent_on'] && $existing->expense_category_id === (int) $d['expense_category_id'], 'Expense key already used with different details.');

                return $existing;
            }
            $amount = Money::minor($d['amount']);
            $this->require($amount > 0, 'Expense must be positive.');
            $path = isset($d['attachment']) ? $d['attachment']->store('finance/'.$this->school()->id, 'local') : null;
            try {
                $e = SchoolExpense::create(['expense_category_id' => $d['expense_category_id'], 'payee' => $d['payee'], 'description' => $d['description'], 'spent_on' => $d['spent_on'], 'amount_minor' => $amount, 'currency' => $this->currency(), 'method' => $d['method'], 'attachment_path' => $path, 'idempotency_key' => $d['idempotency_key'], 'status' => 'posted']);
                $this->audit('expense-posted', $e);

                return $e;
            } catch (\Throwable $e) {
                if ($path) {
                    Storage::disk('local')->delete($path);
                } throw $e;
            }
        });
    }
}

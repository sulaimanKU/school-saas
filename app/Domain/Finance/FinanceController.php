<?php

namespace App\Domain\Finance;

use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Models\FeeCategory;
use App\Domain\Finance\Models\FeeStructure;
use App\Domain\Finance\Models\InvoiceItem;
use App\Domain\Finance\Models\LateFeePolicy;
use App\Domain\Finance\Models\PaymentAllocation;
use App\Domain\Finance\Models\PaymentReceipt;
use App\Domain\Finance\Models\SchoolExpense;
use App\Domain\Finance\Models\StudentInvoice;
use App\Domain\Finance\Models\StudentPayment;
use App\Domain\Finance\Models\StudentRefund;
use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Operations\Models\AcademicAuditLog;
use App\Modules\Platform\Preview\PreviewData;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class FinanceController
{
    private function base(Request $r): string
    {
        return '/preview/schools/'.$r->route('school').'/finance';
    }

    private function filters(Request $r): array
    {
        $rules = ['school_id' => 'prohibited', 'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'due_from' => 'nullable|date_format:Y-m-d', 'due_to' => 'nullable|date_format:Y-m-d|after_or_equal:due_from', 'method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'cheque', 'other'])], 'status' => ['nullable', Rule::in(['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'voided'])], 'report_type' => ['nullable', Rule::in(['invoices', 'outstanding', 'discounts', 'statement', 'payments', 'daily', 'monthly', 'class', 'category', 'method', 'expenses', 'expense-categories', 'class-outstanding', 'student-outstanding', 'refunds'])]];
        foreach (['academic_session_id' => 'academic_sessions', 'academic_class_id' => 'academic_classes', 'section_id' => 'sections', 'student_id' => 'students', 'fee_category_id' => 'fee_categories', 'expense_category_id' => 'expense_categories'] as $key => $table) {
            $rules[$key] = ['nullable', 'integer', Rule::exists($table, 'id')->where('school_id', app(FinanceService::class)->school()->id)];
        }

        return $r->validate($rules);
    }

    private function options(): array
    {
        $o = [];
        foreach (['sessions' => AcademicSession::class, 'classes' => AcademicClass::class, 'sections' => Section::class, 'students' => Student::class, 'categories' => FeeCategory::class, 'expenseCategories' => ExpenseCategory::class, 'structures' => FeeStructure::class, 'policies' => LateFeePolicy::class] as $key => $model) {
            $o[$key] = $model::orderBy('id')->get()->map(fn ($r) => ['id' => $r->id, 'label' => $key === 'students' ? $r->admission_number.' · '.$r->full_name : $r->name, 'class_id' => $r->academic_class_id]);
        }
        $o['invoices'] = app(FinanceReports::class)->invoices()->where('student_invoices.status', 'issued')->orderByDesc('student_invoices.id')->get()->map(fn ($i) => ['id' => $i->id, 'student_id' => $i->student_id, 'label' => $i->number.' · '.$i->snapshot['student_name'].' · '.$i->currency.' '.Money::format((int) $i->outstanding_minor)]);
        $o['payments'] = StudentPayment::where('status', 'posted')->orderByDesc('id')->get()->map(fn ($p) => ['id' => $p->id, 'label' => 'PAY-'.$p->id.' · '.$p->currency.' '.Money::format($p->amount_minor)]);

        return $o;
    }

    public function page(Request $r, FinanceReports $reports, PreviewData $preview)
    {
        $f = $this->filters($r);
        $page = $r->route('page', 'dashboard');
        $rows = null;
        if ($page === 'defaulters') {
            $f['status'] = 'overdue';
            $f['report_type'] = 'outstanding';
        }
        if ($page === 'invoices' || $page === 'defaulters') {
            $rows = $reports->invoices($page === 'defaulters' ? [...$f, 'status' => 'overdue'] : $f)->orderByDesc('student_invoices.id')->paginate(20)->withQueryString();
        } elseif ($page === 'audit') {
            $rows = AcademicAuditLog::where('operation', 'like', 'finance.%')->orderByDesc('id')->paginate(20)->withQueryString();
        } elseif (isset(FinanceCatalog::MODELS[$page])) {
            $model = FinanceCatalog::MODELS[$page];
            $q = $model::query();
            if (! empty($f['q']) && in_array($page, ['categories', 'structures', 'discounts', 'late-fees', 'expense-categories'])) {
                $q->where('name', 'like', '%'.$f['q'].'%');
            }
            if (! empty($f['student_id']) && in_array($page, ['assignments', 'discounts', 'payments'])) {
                $q->where('student_id', $f['student_id']);
            }
            foreach (['academic_session_id', 'fee_category_id'] as $key) {
                if (! empty($f[$key]) && in_array($page, ['structures', 'assignments', 'discounts'])) {
                    $q->where($key, $f[$key]);
                }
            }
            foreach (['academic_class_id', 'section_id'] as $key) {
                if (! empty($f[$key]) && $page === 'structures') {
                    $q->where($key, $f[$key]);
                }
            }
            if ($page === 'assignments') {
                foreach (['academic_class_id', 'section_id'] as $key) {
                    if (! empty($f[$key])) {
                        $q->whereIn('enrollment_id', Enrollment::where($key, $f[$key])->select('id'));
                    }
                }
            }
            if ($page === 'payments') {
                foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
                    if (! empty($f[$key])) {
                        $q->where('paid_on', $op, $f[$key]);
                    }
                } if (! empty($f['method'])) {
                    $q->where('method', $f['method']);
                }
            }
            if (in_array($page, ['expenses', 'refunds'])) {
                foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
                    if (! empty($f[$key])) {
                        $q->where($page === 'expenses' ? 'spent_on' : 'refunded_on', $op, $f[$key]);
                    }
                }
            }
            if ($page === 'expenses') {
                if (! empty($f['method'])) {
                    $q->where('method', $f['method']);
                } if (! empty($f['expense_category_id'])) {
                    $q->where('expense_category_id', $f['expense_category_id']);
                }
            }
            if ($page === 'refunds' && ! empty($f['student_id'])) {
                $q->whereIn('payment_id', StudentPayment::where('student_id', $f['student_id'])->select('id'));
            }
            $rows = $q->orderByDesc('id')->paginate(20)->withQueryString();
            if ($page === 'payments') {
                $rows->through(function ($p) {
                    $p->credit_minor = app(FinanceService::class)->credit($p);
                    $p->allocations = PaymentAllocation::where('payment_id', $p->id)->get(['invoice_id', 'amount_minor']);

                    return $p;
                });
            }
        }
        $reportRows = [];
        if (in_array($page, ['reports', 'statements'])) {
            if ($page === 'statements') {
                $f['report_type'] = 'statement';
            }
            if ($page !== 'statements' || ! empty($f['student_id'])) {
                foreach ($reports->rows($f) as $row) {
                    $reportRows[] = $row;
                    if (count($reportRows) === 100) {
                        break;
                    }
                }
            }
        }
        $previewData = $preview->get($r->route('school'));

        return Inertia::render('Finance', [...$previewData, 'page' => 'finance-'.$page, 'financePage' => $page, 'title' => FinanceCatalog::TITLES[$page], 'baseUrl' => $this->base($r), 'fields' => FinanceCatalog::fields($page), 'rows' => $rows, 'options' => $this->options(), 'filters' => $f, 'reportRows' => $reportRows, 'metrics' => $previewData['liveFinance'], 'summary' => in_array($page, ['reports', 'statements', 'defaulters']) ? $reports->summary($page === 'statements' && empty($f['student_id']) ? [...$f, 'student_id' => -1] : $f) : $previewData['liveFinance'], 'flash' => ['success' => $r->session()->get('success')]]);
    }

    private function safe(callable $action): mixed
    {
        try {
            return $action();
        } catch (QueryException $e) {
            if (str_starts_with((string) $e->getCode(), '23') || $e->getCode() === 'P0001') {
                throw ValidationException::withMessages(['finance' => 'Database integrity rejected this change. Check duplicates, school, amounts or dependent history.']);
            } throw $e;
        }
    }

    public function save(FinanceRequest $r, FinanceService $s)
    {
        $d = $r->validated();
        unset($d['school_id']);
        $resource = $r->route('resource');
        $result = $this->safe(fn () => match ($resource) {
            'generate' => $s->generate($d), 'assignments' => $s->assign($d), 'payments' => $s->pay($d), 'refunds' => $s->refund($d), 'expenses' => $s->expense($d),
            'settings' => $s->transaction(function () use ($s, $d) {
                $s->require(! StudentInvoice::exists() && ! StudentPayment::exists() && ! FeeStructure::exists() && ! SchoolExpense::exists(), 'Set currency before creating financial records.');
                $setting = $s->school()->settings()->updateOrCreate(['key' => 'finance'], ['value' => ['currency' => $d['currency']]]);
                $s->audit('currency-configured', $setting);

                return $setting;
            }),
            default => $s->catalog($resource, $d, $r->route('record') ? (int) $r->route('record') : null),
        });

        return redirect($this->base($r).'/'.($resource === 'generate' ? 'invoices' : ($resource === 'settings' ? 'dashboard' : $resource)))->with('success', $resource === 'generate' ? count($result).' invoice(s) generated or already present.' : 'Finance record saved.');
    }

    public function destroy(FinanceRequest $r, FinanceService $s)
    {
        $this->safe(fn () => $s->remove($r->route('resource'), (int) $r->route('record')));

        return back()->with('success', 'Unused configuration deleted.');
    }

    public function action(FinanceRequest $r, FinanceService $s)
    {
        $d = $r->validated();
        $id = (int) $r->route('record');
        $action = $r->route('action');
        if ($action === 'preview-penalty') {
            return response()->json($s->penalty($id, $d['policy_id']));
        }
        $this->safe(fn () => match ($action) {
            'allocate' => $s->allocateCredit($id, $d['invoice_id'], $d['amount']),
            'activate', 'deactivate' => $s->assignmentStatus($id, $action === 'activate'),
            'issue', 'void' => $s->invoiceAction($id, $action, $d), 'reverse' => $s->reverse($id, $d['reason']), 'post', 'reject' => $s->refundAction($id, $action),
            'post-penalty' => $s->transaction(function () use ($s, $id, $d) {
                $preview = $s->penalty($id, $d['policy_id']);
                $s->require($preview['amount_minor'] === (int) $d['preview_amount_minor'], 'Penalty changed since preview. Preview again.');

                return $s->penalty($id, $d['policy_id'], true);
            }),
        });

        return back()->with('success', 'Financial action recorded.');
    }

    public function document(Request $r, PreviewData $preview)
    {
        $kind = $r->route('kind');
        $id = $r->route('record');
        if ($kind === 'receipt') {
            $p = StudentPayment::findOrFail($id);
            $record = PaymentReceipt::where('payment_id', $id)->firstOrFail();
            $snapshot = $record->snapshot;
            $data = ['number' => $record->number, 'snapshot' => $snapshot, 'status' => $p->status, 'refund_minor' => (int) StudentRefund::where('payment_id', $id)->where('status', 'posted')->sum('amount_minor'), 'reason' => $p->reversal_reason];
        } else {
            $i = StudentInvoice::findOrFail($id);
            $data = [...$i->toArray(), ...app(FinanceService::class)->balance($i), 'items' => InvoiceItem::where('invoice_id', $id)->get()];
        }

        return Inertia::render('FinanceDocument', [...$preview->get($r->route('school')), 'page' => 'finance-invoices', 'title' => ucfirst($kind), 'kind' => $kind, 'document' => $data, 'baseUrl' => $this->base($r)]);
    }

    public function attachment(Request $r)
    {
        $e = SchoolExpense::findOrFail($r->route('record'));
        abort_unless($e->attachment_path && Storage::disk('local')->exists($e->attachment_path), 404);

        return Storage::disk('local')->download($e->attachment_path, 'expense-'.$e->id.'.'.pathinfo($e->attachment_path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff']);
    }

    public function export(Request $r, FinanceReports $reports)
    {
        $f = $this->filters($r);
        if (($f['report_type'] ?? '') === 'statement' && empty($f['student_id'])) {
            throw ValidationException::withMessages(['student_id' => 'Select a student for their fee statement.']);
        }

        return response()->streamDownload(function () use ($f, $reports) {
            $out = fopen('php://output', 'w');
            $header = false;
            foreach ($reports->rows($f) as $row) {
                if (! $header) {
                    fputcsv($out, array_keys($row), ',', '"', '');
                    $header = true;
                } fputcsv($out, array_map(fn ($v) => preg_match('/\A[=+@\-\t\r]/', (string) $v) ? "'".$v : $v, array_values($row)), ',', '"', '');
            }
            if (! $header) {
                fputcsv($out, ['No financial records match these filters.'], ',', '"', '');
            } fclose($out);
        }, 'school-finance.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}

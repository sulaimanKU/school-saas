<?php

namespace App\Domain\Finance;

use App\Modules\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->environment('local') && config('preview.enabled') === true && config('preview.finance') === true;
    }

    public function rules(): array
    {
        $school = app(TenantContext::class)->school()->id;
        $id = fn ($table, $required = true) => [$required ? 'required' : 'nullable', 'integer', Rule::exists($table, 'id')->where('school_id', $school)];
        $money = ['required', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'];
        $optionalMoney = ['nullable', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'];
        $name = ['required', 'string', 'max:100'];
        $date = ['required', 'date_format:Y-m-d'];
        $method = ['required', Rule::in(['cash', 'bank_transfer', 'cheque', 'other'])];
        $key = ['required', 'string', 'max:100', 'regex:/\A[a-zA-Z0-9_-]{8,100}\z/'];
        $action = $this->route('action');
        $resource = $this->route('resource');
        if ($this->isMethod('delete')) {
            return ['school_id' => 'prohibited'];
        }
        $rules = match (true) {
            $action === 'issue' => ['confirm' => 'accepted'],
            in_array($action, ['activate', 'deactivate']) => ['confirm' => 'accepted'],
            in_array($action, ['void', 'reverse']) => ['confirm' => 'accepted', 'reason' => ['required', 'string', 'min:5', 'max:500']],
            in_array($action, ['post', 'reject']) => ['confirm' => 'accepted'],
            $action === 'allocate' => ['confirm' => 'accepted', 'invoice_id' => $id('student_invoices'), 'amount' => $money],
            in_array($action, ['preview-penalty', 'post-penalty']) => ['policy_id' => $id('late_fee_policies'), ...($action === 'post-penalty' ? ['confirm' => 'accepted', 'preview_amount_minor' => ['required', 'integer', 'min:1']] : [])],
            $resource === 'settings' => ['currency' => ['required', Rule::in(['PKR', 'USD', 'GBP', 'EUR', 'AED', 'SAR'])]],
            in_array($resource, ['categories', 'expense-categories']) => ['name' => $name, 'active' => 'required|boolean'],
            $resource === 'structures' => ['name' => $name, 'academic_session_id' => $id('academic_sessions'), 'academic_class_id' => $id('academic_classes'), 'section_id' => $id('sections', false), 'fee_category_id' => $id('fee_categories'), 'amount' => $money, 'frequency' => ['required', Rule::in(['monthly', 'quarterly', 'annual', 'one_time'])], 'effective_from' => $date, 'effective_to' => [...$date, 'after_or_equal:effective_from'], 'active' => 'required|boolean'],
            $resource === 'assignments' => ['fee_structure_id' => $id('fee_structures'), 'student_id' => $id('students', false), 'section_id' => $id('sections', false), 'adjusted_amount' => $optionalMoney],
            $resource === 'generate' => ['academic_session_id' => $id('academic_sessions'), 'academic_class_id' => $id('academic_classes', false), 'section_id' => $id('sections', false), 'student_id' => $id('students', false), 'period' => $date, 'issued_on' => [...$date, 'before_or_equal:today'], 'due_on' => [...$date, 'after_or_equal:issued_on'], 'auto_assign' => 'sometimes|boolean', 'issue' => 'required|boolean', 'late_fee_exempt' => 'required|boolean'],
            $resource === 'payments' => ['student_id' => $id('students'), 'paid_on' => [...$date, 'before_or_equal:today'], 'amount' => $money, 'method' => $method, 'reference' => 'nullable|string|max:150', 'notes' => 'nullable|string|max:2000', 'idempotency_key' => $key, 'allocations' => 'present|array|max:100', 'allocations.*.invoice_id' => [...$id('student_invoices'), 'distinct'], 'allocations.*.amount' => $money],
            $resource === 'discounts' => ['name' => $name, 'student_id' => $id('students'), 'academic_session_id' => $id('academic_sessions'), 'fee_category_id' => $id('fee_categories', false), 'kind' => ['required', Rule::in(['scholarship', 'sibling', 'staff_child', 'need_based', 'custom'])], 'calculation' => ['required', Rule::in(['fixed', 'percent'])], 'value' => $money, 'starts_on' => $date, 'ends_on' => [...$date, 'after_or_equal:starts_on'], 'approval_note' => 'required|string|min:5|max:500', 'active' => 'required|boolean'],
            $resource === 'late-fees' => ['name' => $name, 'calculation' => ['required', Rule::in(['fixed', 'percent'])], 'value' => $money, 'grace_days' => 'required|integer|min:0|max:365', 'cap' => $money, 'active' => 'required|boolean'],
            $resource === 'refunds' => ['payment_id' => $id('student_payments'), 'invoice_id' => $id('student_invoices', false), 'amount' => $money, 'refunded_on' => [...$date, 'before_or_equal:today'], 'reason' => 'required|string|min:5|max:500', 'idempotency_key' => $key],
            $resource === 'expenses' => ['expense_category_id' => $id('expense_categories'), 'spent_on' => [...$date, 'before_or_equal:today'], 'amount' => $money, 'payee' => 'required|string|max:150', 'description' => 'required|string|max:500', 'method' => $method, 'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', 'idempotency_key' => $key],
            default => [],
        };

        return ['school_id' => 'prohibited', ...$rules];
    }
}

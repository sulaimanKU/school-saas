<?php

namespace App\Domain\Finance;

use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Models\FeeAssignment;
use App\Domain\Finance\Models\FeeCategory;
use App\Domain\Finance\Models\FeeStructure;
use App\Domain\Finance\Models\LateFeePolicy;
use App\Domain\Finance\Models\SchoolExpense;
use App\Domain\Finance\Models\StudentDiscount;
use App\Domain\Finance\Models\StudentInvoice;
use App\Domain\Finance\Models\StudentPayment;
use App\Domain\Finance\Models\StudentRefund;

final class FinanceCatalog
{
    public const MODELS = ['categories' => FeeCategory::class, 'structures' => FeeStructure::class, 'discounts' => StudentDiscount::class, 'late-fees' => LateFeePolicy::class, 'expense-categories' => ExpenseCategory::class, 'assignments' => FeeAssignment::class, 'invoices' => StudentInvoice::class, 'payments' => StudentPayment::class, 'refunds' => StudentRefund::class, 'expenses' => SchoolExpense::class];

    public const TITLES = ['dashboard' => 'Finance Dashboard', 'categories' => 'Fee Categories', 'structures' => 'Fee Structures', 'assignments' => 'Student Fee Assignment', 'invoices' => 'Invoice Management', 'generate' => 'Invoice Generation', 'payments' => 'Payment Collection & History', 'discounts' => 'Discounts & Scholarships', 'late-fees' => 'Late Fee Configuration', 'refunds' => 'Manual Refunds', 'expenses' => 'Expense Management', 'expense-categories' => 'Expense Categories', 'defaulters' => 'Fee Defaulters', 'statements' => 'Student Fee Statements', 'reports' => 'Financial Reports', 'audit' => 'Finance Audit'];

    public static function fields(string $page): array
    {
        $fields = match ($page) {
            'categories', 'expense-categories' => ['name:text', 'active:checkbox'],
            'structures' => ['name:text', 'academic_session_id:sessions', 'academic_class_id:classes', 'section_id:sections', 'fee_category_id:categories', 'amount:money', 'frequency:monthly|quarterly|annual|one_time', 'effective_from:date', 'effective_to:date', 'active:checkbox'],
            'assignments' => ['fee_structure_id:structures', 'student_id:students', 'section_id:sections', 'adjusted_amount:money'],
            'generate' => ['academic_session_id:sessions', 'academic_class_id:classes', 'section_id:sections', 'student_id:students', 'period:date', 'issued_on:date', 'due_on:date', 'auto_assign:checkbox', 'issue:checkbox', 'late_fee_exempt:checkbox'],
            'payments' => ['student_id:students', 'paid_on:date', 'amount:money', 'method:cash|bank_transfer|cheque|other', 'reference:text', 'notes:text'],
            'discounts' => ['name:text', 'student_id:students', 'academic_session_id:sessions', 'fee_category_id:categories', 'kind:scholarship|sibling|staff_child|need_based|custom', 'calculation:fixed|percent', 'value:money', 'starts_on:date', 'ends_on:date', 'approval_note:text', 'active:checkbox'],
            'late-fees' => ['name:text', 'calculation:fixed|percent', 'value:money', 'grace_days:number', 'cap:money', 'active:checkbox'],
            'refunds' => ['payment_id:payments', 'invoice_id:invoices', 'amount:money', 'refunded_on:date', 'reason:text'],
            'expenses' => ['expense_category_id:expenseCategories', 'spent_on:date', 'amount:money', 'payee:text', 'description:text', 'method:cash|bank_transfer|cheque|other', 'attachment:file'],
            default => [],
        };

        return array_map(function ($s) {
            [$key, $type] = explode(':', $s);

            return ['key' => $key, 'type' => $type, 'label' => ucwords(str_replace('_', ' ', preg_replace('/_id$/', '', $key)))];
        }, $fields);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'fee_structures' => ['academic_session_id', 'fee_category_id'],
        'student_fee_assignments' => ['student_id', 'academic_session_id', 'fee_category_id'],
        'student_invoices' => ['academic_session_id', 'academic_class_id', 'section_id', 'issued_on'],
        'student_invoice_items' => ['invoice_id', 'fee_category_id'],
        'payment_allocations' => ['invoice_id'],
        'student_refunds' => ['payment_id', 'invoice_id', 'refunded_on'],
        'school_expenses' => ['spent_on', 'expense_category_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->index(['school_id', $column]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->dropIndex(['school_id', $column]);
                }
            });
        }
    }
};

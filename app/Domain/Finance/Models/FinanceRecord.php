<?php

namespace App\Domain\Finance\Models;

use App\Modules\Academics\Models\AcademicRecord;

abstract class FinanceRecord extends AcademicRecord
{
    protected function casts(): array
    {
        return ['active' => 'boolean', 'late_fee_exempt' => 'boolean', 'snapshot' => 'array', 'amount_minor' => 'integer', 'subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'total_minor' => 'integer', 'value' => 'integer', 'cap_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::deleting(function (self $model) {
            if (! in_array($model->getTable(), ['fee_categories', 'expense_categories', 'fee_structures', 'student_discounts', 'late_fee_policies'])) {
                throw new \LogicException('Financial history cannot be deleted.');
            }
        });
        static::updating(function (self $model) {
            $allowed = match ($model->getTable()) {
                'student_invoices' => ['status', 'void_reason', 'updated_at'],
                'student_payments' => ['status', 'reversal_reason', 'updated_at'],
                'student_refunds' => ['status', 'updated_at'],
                'student_fee_assignments' => ['active', 'updated_at'],
                'fee_categories', 'expense_categories', 'fee_structures', 'student_discounts', 'late_fee_policies' => null,
                default => [],
            };
            if ($allowed !== null && array_diff(array_keys($model->getDirty()), $allowed)) {
                throw new \LogicException('Financial snapshot is immutable.');
            }
        });
    }
}

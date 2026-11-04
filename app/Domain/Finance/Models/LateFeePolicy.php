<?php

namespace App\Domain\Finance\Models;

final class LateFeePolicy extends FinanceRecord
{
    protected $table = 'late_fee_policies';
}

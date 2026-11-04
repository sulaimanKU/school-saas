<?php

namespace App\Domain\Finance;

use Illuminate\Validation\ValidationException;

final class Money
{
    public static function minor(string|int $value): int
    {
        $value = (string) $value;
        if (! preg_match('/\A([0-9]{1,10})(?:\.([0-9]{1,2}))?\z/', $value, $m)) {
            throw ValidationException::withMessages(['amount' => 'Use a positive amount with at most two decimal places.']);
        }

        return ((int) $m[1] * 100) + (int) str_pad($m[2] ?? '', 2, '0');
    }

    public static function percent(int $amount, int $basisPoints): int
    {
        return intdiv($amount * $basisPoints + 5000, 10000);
    }

    public static function format(int $amount): string
    {
        if ($amount < 0) {
            return '-'.self::format(-$amount);
        }

        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}

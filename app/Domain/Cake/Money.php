<?php

namespace App\Domain\Cake;

/**
 * All arithmetic happens in integer paise (₹1 = 100 paise). Floats are never
 * used for money — every conversion below is either an exact multiply or an
 * explicit round-half-up, so results are reproducible to the paisa.
 */
class Money
{
    /**
     * @param int|float $rupees
     */
    public static function toPaise($rupees): int
    {
        return (int) round($rupees * 100);
    }

    public static function toRupees(int $paise): int
    {
        // Every price in this domain resolves to a whole-rupee amount, so
        // this division is exact — no fractional rupees ever get truncated.
        return intdiv($paise, 100);
    }

    /**
     * Round-half-up to the nearest paisa: (amount * percent) / 100.
     * e.g. percentRoundHalfUp(110000, 18) -> 19800 (₹198.00 on ₹1100).
     */
    public static function percentRoundHalfUp(int $amountPaise, int $percent): int
    {
        return intdiv(($amountPaise * $percent) + 50, 100);
    }
}

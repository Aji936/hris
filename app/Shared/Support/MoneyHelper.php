<?php

declare(strict_types=1);

namespace App\Shared\Support;

use InvalidArgumentException;

/**
 * Arbitrary-precision decimal helper for financial/payroll calculations.
 * Enforces DECIMAL(15, 2) safe string arithmetic without IEEE-754 float errors.
 */
final class MoneyHelper
{
    public const DEFAULT_SCALE = 2;

    public static function add(string|int $left, string|int $right, int $scale = self::DEFAULT_SCALE): string
    {
        return bcadd(self::normalize($left, $scale), self::normalize($right, $scale), $scale);
    }

    public static function sub(string|int $left, string|int $right, int $scale = self::DEFAULT_SCALE): string
    {
        return bcsub(self::normalize($left, $scale), self::normalize($right, $scale), $scale);
    }

    public static function mul(string|int $amount, string|int $multiplier, int $scale = self::DEFAULT_SCALE): string
    {
        $raw = bcmul(self::normalize($amount, $scale + 4), self::normalize($multiplier, $scale + 4), $scale + 4);

        return self::roundHalfUp($raw, $scale);
    }

    public static function div(string|int $amount, string|int $divisor, int $scale = self::DEFAULT_SCALE): string
    {
        $normalizedDivisor = self::normalize($divisor, $scale + 4);

        if (bccomp($normalizedDivisor, '0', $scale + 4) === 0) {
            throw new InvalidArgumentException('Division by zero in financial calculation.');
        }

        $raw = bcdiv(self::normalize($amount, $scale + 4), $normalizedDivisor, $scale + 4);

        return self::roundHalfUp($raw, $scale);
    }

    public static function compare(string|int $left, string|int $right, int $scale = self::DEFAULT_SCALE): int
    {
        return bccomp(self::normalize($left, $scale), self::normalize($right, $scale), $scale);
    }

    public static function normalize(string|int $value, int $scale = self::DEFAULT_SCALE): string
    {
        $stringValue = trim((string) $value);

        if (! is_numeric($stringValue)) {
            throw new InvalidArgumentException("Invalid numeric string value [{$stringValue}].");
        }

        return bcadd($stringValue, '0', $scale);
    }

    public static function roundHalfUp(string $value, int $scale = self::DEFAULT_SCALE): string
    {
        $offset = '0.' . str_repeat('0', $scale) . '5';

        if (str_starts_with($value, '-')) {
            return bcsub($value, $offset, $scale);
        }

        return bcadd($value, $offset, $scale);
    }
}


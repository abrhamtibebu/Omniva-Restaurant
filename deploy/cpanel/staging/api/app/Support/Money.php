<?php

declare(strict_types=1);

namespace App\Support;

/**
 * String-based decimal arithmetic. Never convert money to float.
 */
final class Money
{
    public const SCALE = 2;

    public static function of(string|int|float $value): string
    {
        return bcadd((string) $value, '0', self::SCALE);
    }

    public static function add(string ...$amounts): string
    {
        $sum = '0.00';

        foreach ($amounts as $amount) {
            $sum = bcadd($sum, self::of($amount), self::SCALE);
        }

        return $sum;
    }

    public static function subtract(string $left, string $right): string
    {
        return bcsub(self::of($left), self::of($right), self::SCALE);
    }

    public static function multiply(string $amount, string $factor): string
    {
        return bcmul(self::of($amount), self::of($factor), self::SCALE);
    }

    public static function percent(string $amount, string $rate): string
    {
        return bcmul(self::of($amount), bcdiv(self::of($rate), '100', 6), self::SCALE);
    }

    public static function compare(string $left, string $right): int
    {
        return bccomp(self::of($left), self::of($right), self::SCALE);
    }

    public static function isZero(string $amount): bool
    {
        return self::compare($amount, '0') === 0;
    }

    public static function isPositive(string $amount): bool
    {
        return self::compare($amount, '0') === 1;
    }

    public static function max(string $left, string $right): string
    {
        return self::compare($left, $right) >= 0 ? self::of($left) : self::of($right);
    }

    public static function min(string $left, string $right): string
    {
        return self::compare($left, $right) <= 0 ? self::of($left) : self::of($right);
    }
}

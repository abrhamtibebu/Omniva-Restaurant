<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decimal quantities (stock and bono counts). Never convert to float.
 */
final class Qty
{
    public const SCALE = 3;

    public static function of(string|int|float $value): string
    {
        return bcadd((string) $value, '0', self::SCALE);
    }

    public static function add(string $left, string $right): string
    {
        return bcadd(self::of($left), self::of($right), self::SCALE);
    }

    public static function sub(string $left, string $right): string
    {
        return bcsub(self::of($left), self::of($right), self::SCALE);
    }

    public static function cmp(string $left, string $right): int
    {
        return bccomp(self::of($left), self::of($right), self::SCALE);
    }

    public static function gt(string $left, string $right): bool
    {
        return self::cmp($left, $right) === 1;
    }

    public static function gte(string $left, string $right): bool
    {
        return self::cmp($left, $right) >= 0;
    }

    public static function eq(string $left, string $right): bool
    {
        return self::cmp($left, $right) === 0;
    }

    public static function lte(string $left, string $right): bool
    {
        return self::cmp($left, $right) <= 0;
    }

    public static function isPositive(string $value): bool
    {
        return self::cmp($value, '0') === 1;
    }
}

<?php

namespace App\Support;

use LogicException;

final class DecimalMoney
{
    public static function add(string|int $a, string|int $b, int $scale = 2): string
    {
        self::ensureBcmath();
        return self::round(bcadd((string) $a, (string) $b, $scale + 6), $scale);
    }

    public static function sub(string|int $a, string|int $b, int $scale = 2): string
    {
        self::ensureBcmath();
        return self::round(bcsub((string) $a, (string) $b, $scale + 6), $scale);
    }

    public static function mul(string|int $a, string|int $b, int $scale = 2): string
    {
        self::ensureBcmath();
        return self::round(bcmul((string) $a, (string) $b, $scale + 6), $scale);
    }

    public static function div(string|int $a, string|int $b, int $scale = 2): string
    {
        self::ensureBcmath();
        if (bccomp((string) $b, '0', 8) === 0) {
            throw new \InvalidArgumentException('No se puede dividir entre cero.');
        }

        return self::round(bcdiv((string) $a, (string) $b, $scale + 6), $scale);
    }

    public static function percent(string|int $amount, string|int $rate, int $scale = 2): string
    {
        return self::div(self::mul($amount, $rate, $scale + 4), '100', $scale);
    }

    public static function compare(string|int $a, string|int $b): int
    {
        self::ensureBcmath();
        return bccomp((string) $a, (string) $b, 8);
    }

    public static function ceilUnits(string|int $quantity, string|int $content): int
    {
        self::ensureBcmath();
        if (self::compare($content, '0') <= 0) {
            throw new \InvalidArgumentException('El contenido debe ser mayor que cero.');
        }

        $whole = (int) bcdiv((string) $quantity, (string) $content, 0);
        return self::compare(bcmul((string) $whole, (string) $content, 8), (string) $quantity) < 0
            ? $whole + 1
            : $whole;
    }

    private static function round(string $value, int $scale): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-');
        $increment = '0.' . str_repeat('0', $scale) . '5';
        $rounded = bcadd($absolute, $increment, max(6, $scale + 3));
        $result = bcdiv($rounded, '1', $scale);

        return $negative && $result !== '0.' . str_repeat('0', $scale) ? '-' . $result : $result;
    }

    private static function ensureBcmath(): void
    {
        if (!function_exists('bcadd')) {
            throw new LogicException('La extensión BCMath es obligatoria para los cálculos monetarios.');
        }
    }
}

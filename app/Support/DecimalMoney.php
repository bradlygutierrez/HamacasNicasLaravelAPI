<?php

namespace App\Support;

final class DecimalMoney
{
    public static function add(string|int $a, string|int $b, int $scale = 2): string
    {
        return self::round(self::operation('add', (string) $a, (string) $b, $scale), $scale);
    }

    public static function sub(string|int $a, string|int $b, int $scale = 2): string
    {
        return self::round(self::operation('sub', (string) $a, (string) $b, $scale), $scale);
    }

    public static function mul(string|int $a, string|int $b, int $scale = 2): string
    {
        return self::round(self::operation('mul', (string) $a, (string) $b, $scale + 6), $scale);
    }

    public static function div(string|int $a, string|int $b, int $scale = 2): string
    {
        if (self::compare((string) $b, '0') === 0) {
            throw new \InvalidArgumentException('No se puede dividir entre cero.');
        }

        return self::round(self::operation('div', (string) $a, (string) $b, $scale + 6), $scale);
    }

    public static function percent(string|int $amount, string|int $rate, int $scale = 2): string
    {
        return self::div(self::mul($amount, $rate, $scale + 4), '100', $scale);
    }

    public static function compare(string|int $a, string|int $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp((string) $a, (string) $b, 6);
        }

        return self::toScaled((string) $a) <=> self::toScaled((string) $b);
    }

    public static function ceilUnits(string|int $quantity, string|int $content): int
    {
        if (self::compare((string) $content, '0') <= 0) {
            throw new \InvalidArgumentException('El contenido debe ser mayor que cero.');
        }

        $whole = self::divTruncated((string) $quantity, (string) $content);
        return self::compare(self::mul((string) $whole, (string) $content, 6), (string) $quantity) < 0
            ? $whole + 1
            : $whole;
    }

    private static function operation(string $operation, string $a, string $b, int $scale): string
    {
        if (function_exists('bcadd')) {
            return match ($operation) {
                'add' => bcadd($a, $b, $scale),
                'sub' => bcsub($a, $b, $scale),
                'mul' => bcmul($a, $b, $scale),
                'div' => bcdiv($a, $b, $scale),
            };
        }

        $scaleFactor = 10 ** 6;
        $left = self::toScaled($a);
        $right = self::toScaled($b);

        return match ($operation) {
            'add' => self::fromScaled($left + $right),
            'sub' => self::fromScaled($left - $right),
            'mul' => self::fromScaled(intdiv($left * $right, $scaleFactor)),
            'div' => self::fromScaled(intdiv($left * $scaleFactor, $right)),
        };
    }

    private static function round(string $value, int $scale): string
    {
        if (function_exists('bcadd')) {
            $negative = str_starts_with($value, '-');
            $absolute = ltrim($value, '-');
            $increment = '0.' . str_repeat('0', $scale) . '5';
            $rounded = bcadd($absolute, $increment, max(6, $scale + 3));
            $result = bcdiv($rounded, '1', $scale);

            return $negative && $result !== '0.' . str_repeat('0', $scale) ? '-' . $result : $result;
        }

        $factor = 10 ** $scale;
        $scaled = self::toScaled($value);
        $rounded = $scaled >= 0
            ? intdiv($scaled + intdiv(10 ** (6 - $scale), 2), 10 ** (6 - $scale))
            : -intdiv(abs($scaled) + intdiv(10 ** (6 - $scale), 2), 10 ** (6 - $scale));

        return number_format($rounded / $factor, $scale, '.', '');
    }

    private static function divTruncated(string $a, string $b): int
    {
        if (function_exists('bcdiv')) {
            return (int) bcdiv($a, $b, 0);
        }

        return intdiv(self::toScaled($a), self::toScaled($b));
    }

    private static function toScaled(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $absolute, 2), 2, '');
        $scaled = ((int) $whole * 1_000_000) + (int) str_pad(substr($fraction, 0, 6), 6, '0');

        return $negative ? -$scaled : $scaled;
    }

    private static function fromScaled(int $value): string
    {
        $negative = $value < 0;
        $absolute = abs($value);
        $whole = intdiv($absolute, 1_000_000);
        $fraction = str_pad((string) ($absolute % 1_000_000), 6, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }
}

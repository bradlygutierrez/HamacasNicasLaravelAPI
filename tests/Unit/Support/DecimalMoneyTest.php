<?php

namespace Tests\Unit\Support;

use App\Support\DecimalMoney;
use PHPUnit\Framework\TestCase;

class DecimalMoneyTest extends TestCase
{
    public function test_decimal_operations_do_not_drift(): void
    {
        $this->assertSame('0.30', DecimalMoney::add('0.10', '0.20'));
        $this->assertSame('0.03', DecimalMoney::mul('0.10', '0.30'));
        $this->assertSame('0.07', DecimalMoney::sub('0.10', '0.03'));
        $this->assertSame('0.33', DecimalMoney::percent('1.10', '30'));
    }

    public function test_large_values_are_deterministic(): void
    {
        $this->assertSame('25000062.50', DecimalMoney::mul('100000.25', '250'));
        $this->assertSame('1000000.00', DecimalMoney::add('999999.99', '0.01'));
        $this->assertSame('2098765.26', DecimalMoney::mul('123456.78', '17'));
        $this->assertSame('333333.33', DecimalMoney::div('1000000', '3'));
    }

    public function test_rounding_and_ceiling_are_half_up_and_exact(): void
    {
        $this->assertSame('1.24', DecimalMoney::add('1.235', '0'));
        $this->assertSame('-1.24', DecimalMoney::add('-1.235', '0'));
        $this->assertSame(3, DecimalMoney::ceilUnits('262.5000', '100'));
        $this->assertSame(3, DecimalMoney::ceilUnits('200.0001', '100'));
        $this->assertSame(2, DecimalMoney::ceilUnits('200', '100'));
    }
}

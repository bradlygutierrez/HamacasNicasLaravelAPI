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
}

<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_birr_input_is_converted_to_santim_without_rounding_errors(): void
    {
        $this->assertSame(150000, Money::toSantim('1500'));
        $this->assertSame(150050, Money::toSantim('1500.5'));
        $this->assertSame(150005, Money::toSantim('1500.05'));
        $this->assertSame(1999, Money::toSantim('19.99'));
        $this->assertSame(0, Money::toSantim('0'));
    }

    public function test_santim_is_formatted_as_birr(): void
    {
        $this->assertSame('ETB 1,500.50', Money::format(150050));
        $this->assertSame('ETB 0.05', Money::format(5));
        $this->assertSame('ETB 2,500,000.00', Money::format(250000000));
        $this->assertSame('-ETB 10.00', Money::format(-1000));
    }

    public function test_the_input_pattern_accepts_only_plain_amounts(): void
    {
        foreach (['0', '1500', '1500.5', '1500.50'] as $good) {
            $this->assertMatchesRegularExpression(Money::PATTERN, $good);
        }

        foreach (['-1', '1.234', '1,500', 'abc', '1e5', ' 15', '.5'] as $bad) {
            $this->assertDoesNotMatchRegularExpression(Money::PATTERN, $bad);
        }
    }

    public function test_santim_round_trips_through_the_form_value(): void
    {
        $this->assertSame('1500.05', Money::toInput(150005));
        $this->assertSame(150005, Money::toSantim(Money::toInput(150005)));
    }
}

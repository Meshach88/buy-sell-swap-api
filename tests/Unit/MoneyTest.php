<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_converts_between_naira_and_kobo(): void
    {
        $this->assertSame(41_500_000, Money::fromNaira(415_000));
        $this->assertSame(415_000.0, Money::toNaira(41_500_000));
    }

    public function test_formats_naira(): void
    {
        $this->assertSame('₦415,000', Money::format(41_500_000));
        $this->assertSame('₦1,500.50', Money::format(150_050));
    }

    public function test_commission_uses_basis_points_and_rounds_to_the_nearest_kobo(): void
    {
        // 8% of ₦415,000 = ₦33,200
        $this->assertSame(3_320_000, Money::commission(41_500_000, 800));
        // 10% of 1 kobo rounds to 0, 10% of 5 kobo rounds to 1 (half up)
        $this->assertSame(0, Money::commission(1, 1000));
        $this->assertSame(1, Money::commission(5, 1000));
    }
}

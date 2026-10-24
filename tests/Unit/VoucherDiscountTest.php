<?php

namespace Tests\Unit;

use App\Models\Seat;
use App\Models\Voucher;
use PHPUnit\Framework\TestCase;

class VoucherDiscountTest extends TestCase
{
    public function test_fixed_voucher_never_exceeds_the_subtotal(): void
    {
        $voucher = new Voucher(['type' => 'fixed', 'value' => 50000]);

        $this->assertSame(50000, $voucher->discountFor(180000));
        $this->assertSame(30000, $voucher->discountFor(30000));
    }

    public function test_percent_voucher_is_rounded_to_whole_dong(): void
    {
        $voucher = new Voucher(['type' => 'percent', 'value' => 15]);

        $this->assertSame(28500, $voucher->discountFor(190000));
        $this->assertSame(15, $voucher->discountFor(99));
    }

    public function test_seat_label_combines_row_and_number(): void
    {
        $this->assertSame('H3', (new Seat(['row' => 'H', 'number' => 3]))->label);
    }
}

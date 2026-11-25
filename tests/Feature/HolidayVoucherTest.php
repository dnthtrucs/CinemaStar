<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\HolidayVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['holiday_vouchers.enabled' => true]);
    }

    public function test_upcoming_holidays_are_created_once_and_manual_changes_are_preserved(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26)->setTime(12, 0));
        $service = app(HolidayVoucherService::class);
        $this->assertSame(2, $service->sync());
        $voucher = Voucher::where('code', 'CSBLACKFRIDAY2026')->firstOrFail();
        $voucher->update(['is_active' => false, 'used_count' => 12, 'value' => 10000]);
        $this->assertSame(0, $service->sync());
        $this->assertFalse($voucher->fresh()->is_active);
        $this->assertSame(12, (int) $voucher->fresh()->used_count);
        $this->assertSame(10000, (int) $voucher->fresh()->value);
        $this->assertDatabaseMissing('vouchers', ['code' => 'CSNEWYEAR2027']);
    }

    public function test_year_boundary_creates_next_year_event_with_correct_limits(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 20)->setTime(12, 0));
        app(HolidayVoucherService::class)->sync();
        $v = Voucher::where('code', 'CSNEWYEAR2027')->firstOrFail();
        $this->assertSame(200, (int) $v->usage_limit);
        $this->assertSame(150000, (int) $v->min_order);
        $this->assertSame(20000, (int) $v->value);
        $this->assertSame('2027-01-01', $v->starts_at->format('Y-m-d'));
    }

    public function test_tet_uses_verified_lunar_date_and_rejects_early_and_expired_redemption(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 10)->setTime(12, 0));
        app(HolidayVoucherService::class)->sync();
        $v = Voucher::where('code', 'CSTET2027')->firstOrFail();
        $this->assertSame('2027-02-04', $v->starts_at->format('Y-m-d'));
        $this->assertSame('2027-02-11', $v->ends_at->format('Y-m-d'));
        $this->assertFalse($v->isUsableFor(200000));
        $this->travelTo($v->starts_at->copy()->addHour());
        $this->assertFalse($v->isUsableFor(199999));
        $this->assertTrue($v->isUsableFor(200000));
        $this->travelTo($v->ends_at->copy()->addSecond());
        $this->assertFalse($v->isUsableFor(200000));
    }

    public function test_disabled_generation_and_unknown_lunar_year_do_not_invent_vouchers(): void
    {
        $this->travelTo(now()->setDate(2028, 1, 1)->setTime(12, 0));
        config(['holiday_vouchers.enabled' => false]);
        $this->assertSame(0, app(HolidayVoucherService::class)->sync());
        config(['holiday_vouchers.enabled' => true]);
        app(HolidayVoucherService::class)->sync();
        $this->assertDatabaseMissing('vouchers', ['code' => 'CSTET2028']);
    }

    public function test_public_offers_hide_disabled_expired_and_exhausted_codes(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26)->setTime(12, 0));
        app(HolidayVoucherService::class)->sync();
        Voucher::where('code', 'CSBLACKFRIDAY2026')->update(['is_active' => false]);
        Voucher::create(['code' => 'EXPIRED', 'name' => 'Old', 'type' => 'fixed', 'value' => 1000, 'ends_at' => now()->subDay()]);
        Voucher::create(['code' => 'EXHAUSTED', 'name' => 'Used', 'type' => 'fixed', 'value' => 1000, 'usage_limit' => 1, 'used_count' => 1]);
        $this->get(route('offers.index'))->assertOk()->assertSee('CSXMAS2026')->assertSee('Sắp áp dụng')->assertDontSee('CSBLACKFRIDAY2026')->assertDontSee('EXPIRED')->assertDontSee('EXHAUSTED');
    }
}

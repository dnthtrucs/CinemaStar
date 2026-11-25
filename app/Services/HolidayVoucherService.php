<?php

namespace App\Services;

use App\Models\Voucher;
use Carbon\CarbonImmutable;

class HolidayVoucherService
{
    public function sync(): int
    {
        if (! config('holiday_vouchers.enabled')) {
            return 0;
        }
        $today = CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfDay();
        $horizon = $today->addDays((int) config('holiday_vouchers.advance_days', 30))->endOfDay();
        $created = 0;
        for ($year = $today->year; $year <= $horizon->year; $year++) {
            $events = [
                ['NEWYEAR', 'Tết Dương lịch', "$year-01-01", 0, 2],
                ['LOVE', 'Valentine', "$year-02-14", 1, 0],
                ['WOMEN', 'Quốc tế Phụ nữ', "$year-03-08", 1, 0],
                ['APRIL', 'Lễ 30/4 và 1/5', "$year-04-30", 0, 1],
                ['KIDS', 'Quốc tế Thiếu nhi', "$year-06-01", 0, 1],
                ['NATIONAL', 'Quốc khánh 2/9', "$year-09-02", 1, 1],
                ['VN WOMEN', 'Phụ nữ Việt Nam', "$year-10-20", 1, 0],
                ['HALLOWEEN', 'Halloween', "$year-10-31", 1, 0],
                ['TEACHERS', 'Ngày Nhà giáo Việt Nam', "$year-11-20", 0, 0],
                ['XMAS', 'Giáng sinh', "$year-12-25", 1, 1],
            ];
            $blackFriday = CarbonImmutable::create($year, 11, 1, 0, 0, 0, 'Asia/Ho_Chi_Minh')->nthOfMonth(4, CarbonImmutable::FRIDAY);
            $events[] = ['BLACKFRIDAY', 'Black Friday', $blackFriday->toDateString(), 0, 2];
            if ($date = config("holiday_vouchers.tet_dates.$year")) {
                $events[] = ['TET', 'Tết Nguyên đán', $date, 2, 5];
            }
            foreach ($events as [$key, $name, $date, $before, $after]) {
                $anchor = CarbonImmutable::parse($date, 'Asia/Ho_Chi_Minh');
                $start = $anchor->subDays($before)->startOfDay();
                $end = $anchor->addDays($after)->endOfDay();
                if ($end->lt($today) || $start->gt($horizon)) {
                    continue;
                }
                $voucher = Voucher::firstOrCreate(['code' => 'CS'.str_replace(' ', '', $key).$year], [
                    'name' => "$name $year", 'type' => 'fixed',
                    'value' => $key === 'TET' ? 30000 : 20000,
                    'min_order' => $key === 'TET' ? 200000 : 150000,
                    'usage_limit' => 200, 'used_count' => 0,
                    'starts_at' => $start, 'ends_at' => $end, 'is_active' => true,
                ]);
                $created += (int) $voucher->wasRecentlyCreated;
            }
        }

        return $created;
    }
}

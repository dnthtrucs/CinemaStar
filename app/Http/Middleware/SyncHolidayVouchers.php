<?php

namespace App\Http\Middleware;

use App\Services\HolidayVoucherService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SyncHolidayVouchers
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && ! app()->runningUnitTests() && config('holiday_vouchers.enabled')) {
            // A request fallback also works when a free host sleeps or has no scheduler.
            $key = 'holiday-vouchers:v1:'.now('Asia/Ho_Chi_Minh')->toDateString();
            if (! Cache::has($key)) {
                app(HolidayVoucherService::class)->sync();
                Cache::put($key, true, now()->addDay());
            }
        }

        return $next($request);
    }
}

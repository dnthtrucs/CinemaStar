<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherSuggestionController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['subtotal' => ['required', 'integer', 'min:0', 'max:100000000']]);
        $rows = DB::transaction(function () use ($request, $data) {
            return Voucher::where('is_active', true)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->orderBy('id')->limit(100)->get()->map(function ($v) use ($request, $data) {
                $usable = $v->isUsableFor($data['subtotal'], $request->user());
                $reason = $usable ? null : ($v->starts_at?->isFuture() ? 'Chưa đến thời gian áp dụng' : ($data['subtotal'] < $v->min_order ? 'Đơn tối thiểu '.number_format($v->min_order, 0, ',', '.').'₫' : 'Đã dùng, đang giữ lượt hoặc hết lượt'));

                return ['code' => $v->code, 'name' => $v->name, 'usable' => $usable, 'discount' => $usable ? $v->discountFor($data['subtotal']) : 0, 'reason' => $reason];
            })->sortByDesc('discount')->values()->take(8);
        });

        return response()->json(['vouchers' => $rows])->header('Cache-Control', 'no-store, private');
    }
}

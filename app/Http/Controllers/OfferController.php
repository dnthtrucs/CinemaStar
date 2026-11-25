<?php

namespace App\Http\Controllers;

use App\Models\Voucher;

class OfferController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()->addDays(30)))
            ->orderBy('starts_at')->paginate(12);

        return view('offers.index', compact('vouchers'));
    }
}

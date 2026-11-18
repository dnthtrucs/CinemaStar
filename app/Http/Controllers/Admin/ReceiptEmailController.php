<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Notifications\PaymentReceiptNotification;
use Throwable;

class ReceiptEmailController extends Controller
{
    public function store(Booking $booking)
    {
        abort_unless($booking->status === 'confirmed' && $booking->payment_status === 'paid', 422, 'Chỉ gửi vé của đơn đã thanh toán thành công.');
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->with('error', 'Dịch vụ gửi email chưa được cấu hình. Đơn vé vẫn được giữ nguyên.');
        }
        try {
            $booking->loadMissing('user');
            $booking->user->notify(new PaymentReceiptNotification($booking));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Chưa gửi được email vé. Vui lòng kiểm tra dịch vụ gửi email rồi thử lại.');
        }

        return back()->with('success', 'Đã gửi lại email vé tới địa chỉ đăng ký của khách hàng.');
    }
}

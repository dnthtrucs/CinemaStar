<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\PaymentReceiptNotification;
use App\Services\PaymentGatewayService;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentGatewayService $gateway)
    {
    }

    public function show(Booking $booking)
    {
        $this->authorizeBooking($booking);
        abort_unless($booking->isPayable(), 422, 'Đơn hàng không còn hiệu lực thanh toán.');

        $booking->loadMissing(['showtime.movie', 'showtime.room.cinema', 'user', 'voucher']);
        $paymentMode = $this->gateway->mode();
        $providerStatus = [
            'momo' => $paymentMode === 'simulate' || $this->gateway->isConfigured('momo'),
            'vnpay' => $paymentMode === 'simulate' || $this->gateway->isConfigured('vnpay'),
        ];

        return view('payments.checkout', compact('booking', 'paymentMode', 'providerStatus'));
    }

    public function store(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);
        abort_unless($booking->isPayable(), 422, 'Đơn hàng không còn hiệu lực thanh toán.');

        $validated = $request->validate([
            'provider' => ['required', 'in:demo,vnpay,momo'],
            'points_to_use' => ['nullable', 'integer', 'min:0'],
        ]);
        $provider = $validated['provider'];
        $pointsToUse = (int) ($validated['points_to_use'] ?? 0);

        if ($provider === 'demo' && ! config('cinema.demo_payment_enabled')) {
            return back()->with('error', 'Thanh toán mô phỏng đã bị tắt.');
        }

        if (in_array($provider, ['momo', 'vnpay'], true)
            && ! $this->gateway->isSimulationMode()
            && ! $this->gateway->isConfigured($provider)) {
            return back()->withInput()->with('error', 'Cổng '.strtoupper($provider).' chưa được cấu hình.');
        }

        $payment = DB::transaction(function () use ($booking, $provider, $pointsToUse) {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            abort_unless($lockedBooking->isPayable(), 422, 'Đơn hàng không còn hiệu lực thanh toán.');

            if ($lockedBooking->points_used === 0 && $pointsToUse > 0) {
                $user = $lockedBooking->user()->lockForUpdate()->firstOrFail();
                // discount là giảm giá voucher đã áp dụng khi tạo đơn.
                // Điểm chỉ được đổi trên số tiền còn lại sau voucher.
                $amountAfterVoucher = max(0, (int) $lockedBooking->subtotal - (int) $lockedBooking->discount);
                $maxRedeemable = min((int) $user->loyalty_points, (int) floor($amountAfterVoucher / 1000));
                if ($pointsToUse > $maxRedeemable) abort(422, "Bạn chỉ có thể dùng tối đa {$maxRedeemable} điểm cho đơn này.");
                $discount = $pointsToUse * 1000;
                $lockedBooking->update([
                    'points_used' => $pointsToUse,
                    'total_price' => max(0, $amountAfterVoucher - $discount),
                ]);
                $user->decrement('loyalty_points', $pointsToUse);
            }

            $firstPaymentAt = $lockedBooking->payments()->oldest('created_at')->value('created_at');
            $windowStartedAt = $firstPaymentAt ? Carbon::parse($firstPaymentAt) : now();
            $paymentDeadline = $windowStartedAt->copy()->addMinutes(15);

            if (! $lockedBooking->expires_at || $lockedBooking->expires_at->lt($paymentDeadline)) {
                $lockedBooking->update(['expires_at' => $paymentDeadline]);
            }

            return Payment::create([
                'booking_id' => $lockedBooking->id,
                'provider' => $provider,
                'request_id' => 'PAY'.now()->format('ymdHis').Str::upper(Str::random(6)),
                'amount' => $lockedBooking->fresh()->total_price,
                'status' => 'initiated',
            ]);
        });

        if ((int) $payment->amount === 0) {
            $successful = $this->markSuccessful($payment, 'POINTS-'.$payment->id, ['result' => 'points_redemption', 'points_only' => true]);
            return redirect()->route('bookings.show', $payment->booking_id)->with($successful ? 'success' : 'error', $successful ? 'Đơn hàng đã được thanh toán hoàn toàn bằng điểm thành viên.' : 'Không thể xác nhận đơn hàng.');
        }

        try {
            $url = $this->gateway->paymentUrl(
                $payment->load('booking'),
                (string) $request->ip(),
            );
            $payment->update(['status' => 'pending']);

            return redirect()->away($url);
        } catch (Throwable $exception) {
            report($exception);
            $payment->update([
                'status' => 'failed',
                'payload' => ['message' => $exception->getMessage()],
            ]);

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function simulate(Payment $payment)
    {
        $this->authorizePayment($payment);
        abort_unless(
            $this->gateway->isSimulationMode()
                && in_array($payment->provider, ['momo', 'vnpay'], true),
            404,
        );
        abort_unless(in_array($payment->status, ['initiated', 'pending'], true), 422, 'Giao dịch đã kết thúc.');

        $payment->loadMissing('booking.showtime.movie');

        $paymentQr = $this->makeQrDataUri(route('payments.simulate', $payment));

        return view('payments.simulate', compact('payment', 'paymentQr'));
    }

    public function completeSimulation(Request $request, Payment $payment)
    {
        $this->authorizePayment($payment);
        abort_unless(
            $this->gateway->isSimulationMode()
                && in_array($payment->provider, ['momo', 'vnpay'], true),
            404,
        );

        $validated = $request->validate([
            'result' => ['required', 'in:success,cancelled'],
        ]);

        if ($validated['result'] === 'cancelled') {
            $this->markFailed($payment, 'USER_CANCELLED', ['result' => 'cancelled', 'simulated' => true]);

            return redirect()->route('bookings.show', $payment->booking_id)
                ->with('error', 'Bạn đã hủy thanh toán mô phỏng.');
        }

        $provider = strtoupper($payment->provider);
        $successful = $this->markSuccessful(
            $payment,
            'SIM-'.$provider.'-'.$payment->id,
            ['result' => 'success', 'simulated' => true],
        );

        return redirect()->route('bookings.show', $payment->booking_id)->with(
            $successful ? 'success' : 'error',
            $successful
                ? "Thanh toán {$provider} mô phỏng thành công. Mã QR vé đã được kích hoạt."
                : 'Đơn không còn đủ điều kiện để xác nhận thanh toán.',
        );
    }

    public function demo(Payment $payment)
    {
        $this->authorizePayment($payment);
        abort_unless($payment->provider === 'demo' && config('cinema.demo_payment_enabled'), 404);

        return view('payments.demo', compact('payment'));
    }

    public function completeDemo(Payment $payment)
    {
        $this->authorizePayment($payment);
        abort_unless($payment->provider === 'demo' && config('cinema.demo_payment_enabled'), 404);
        $this->markSuccessful($payment, 'DEMO-'.$payment->id, ['result' => 'success']);

        return redirect()->route('bookings.show', $payment->booking_id)
            ->with('success', 'Thanh toán mô phỏng thành công.');
    }

    public function vnpayReturn(Request $request)
    {
        $payment = Payment::query()
            ->where('provider', 'vnpay')
            ->where('request_id', $request->string('vnp_TxnRef')->toString())
            ->firstOrFail();
        $valid = $this->gateway->verifyVnpay($request->query())
            && (int) $request->input('vnp_Amount') === (int) $payment->amount * 100;

        if (! $valid) {
            return redirect()->route('bookings.show', $payment->booking_id)
                ->with('error', 'Kết quả VNPAY có chữ ký hoặc số tiền không hợp lệ.');
        }

        if ($request->input('vnp_ResponseCode') === '00'
            && $request->input('vnp_TransactionStatus') === '00') {
            $message = $payment->fresh()->status === 'success'
                ? 'Thanh toán VNPAY thành công.'
                : 'VNPAY đã tiếp nhận giao dịch. Hệ thống đang chờ xác nhận IPN.';

            return redirect()->route('bookings.show', $payment->booking_id)->with('success', $message);
        }

        return redirect()->route('bookings.show', $payment->booking_id)
            ->with('error', 'Giao dịch VNPAY chưa thành công.');
    }

    public function vnpayIpn(Request $request)
    {
        $payment = Payment::query()
            ->where('provider', 'vnpay')
            ->where('request_id', $request->string('vnp_TxnRef')->toString())
            ->first();

        if (! $payment) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
        }

        if (! $this->gateway->verifyVnpay($request->query())) {
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
        }

        if ((int) $request->input('vnp_Amount') !== (int) $payment->amount * 100) {
            return response()->json(['RspCode' => '04', 'Message' => 'Invalid amount']);
        }

        if ($payment->status === 'success') {
            return response()->json(['RspCode' => '02', 'Message' => 'Order already confirmed']);
        }

        if ($request->input('vnp_ResponseCode') === '00'
            && $request->input('vnp_TransactionStatus') === '00') {
            $this->markSuccessful(
                $payment,
                (string) $request->input('vnp_TransactionNo'),
                $request->query(),
            );
        } else {
            $this->markFailed(
                $payment,
                (string) $request->input('vnp_ResponseCode'),
                $request->query(),
            );
        }

        return response()->json(['RspCode' => '00', 'Message' => 'Confirm success']);
    }

    public function momoReturn(Request $request)
    {
        $payment = Payment::query()
            ->where('provider', 'momo')
            ->where('request_id', $request->string('orderId')->toString())
            ->firstOrFail();
        $valid = $this->gateway->verifyMomo($request->query())
            && (int) $request->input('amount') === (int) $payment->amount;

        if (! $valid) {
            return redirect()->route('bookings.show', $payment->booking_id)
                ->with('error', 'Kết quả MoMo có chữ ký hoặc số tiền không hợp lệ.');
        }

        if ((int) $request->input('resultCode') === 0) {
            $message = $payment->fresh()->status === 'success'
                ? 'Thanh toán MoMo thành công.'
                : 'MoMo đã tiếp nhận giao dịch. Hệ thống đang chờ xác nhận IPN.';

            return redirect()->route('bookings.show', $payment->booking_id)->with('success', $message);
        }

        return redirect()->route('bookings.show', $payment->booking_id)
            ->with('error', 'Giao dịch MoMo chưa thành công.');
    }

    public function momoIpn(Request $request)
    {
        $payment = Payment::query()
            ->where('provider', 'momo')
            ->where('request_id', $request->string('orderId')->toString())
            ->first();

        if (! $payment || ! $this->gateway->verifyMomo($request->all())) {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        if ((int) $request->input('amount') !== (int) $payment->amount) {
            return response()->json(['message' => 'Invalid amount'], 400);
        }

        if ((int) $request->input('resultCode') === 0) {
            $this->markSuccessful(
                $payment,
                (string) $request->input('transId'),
                $request->all(),
            );
        } else {
            $this->markFailed(
                $payment,
                (string) $request->input('resultCode'),
                $request->all(),
            );
        }

        return response()->json(['message' => 'OK']);
    }

    private function makeQrDataUri(string $value): string
    {
        $qrCode = new QrCode(
            data: $value,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 360,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(23, 23, 23),
            backgroundColor: new Color(255, 255, 255),
        );

        return 'data:image/svg+xml;base64,'.base64_encode(
            (new SvgWriter())->write($qrCode)->getString(),
        );
    }

    private function markSuccessful(Payment $payment, string $transactionId, array $payload): bool
    {
        return DB::transaction(function () use ($payment, $transactionId, $payload) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'success') {
                return true;
            }

            $booking = $payment->booking()->lockForUpdate()->firstOrFail();
            $amountMatches = (int) $payment->amount === (int) $booking->total_price;
            $ticketsAreHeld = $booking->tickets()->count() === $booking->quantity;
            if ($booking->status !== 'pending' || ! $amountMatches || ! $ticketsAreHeld) {
                $payment->update([
                    'transaction_id' => $transactionId,
                    'status' => 'failed',
                    'response_code' => 'ORDER_INVALID',
                    'payload' => $payload,
                ]);

                return false;
            }

            $paidAt = now();
            $payment->update([
                'transaction_id' => $transactionId,
                'status' => 'success',
                'response_code' => '00',
                'payload' => $payload,
                'paid_at' => $paidAt,
            ]);

            $booking->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_method' => $payment->provider,
                'paid_at' => $paidAt,
                'expires_at' => null,
            ]);
            if ((int) $booking->points_earned === 0) {
                $earnedPoints = (int) floor((float) $booking->total_price / 10000);
                if ($earnedPoints > 0) {
                    $booking->user()->lockForUpdate()->firstOrFail()->increment('loyalty_points', $earnedPoints);
                    $booking->update(['points_earned' => $earnedPoints]);
                }
            }

            // Gửi email chỉ sau khi giao dịch đã được lưu thành công. Áp dụng cho
            // thanh toán demo, mô phỏng MoMo/VNPAY và callback IPN thật.
            DB::afterCommit(function () use ($booking) {
                try {
                    $booking->loadMissing([
                        'user',
                        'showtime.movie',
                        'showtime.room.cinema',
                        'tickets.seat',
                    ]);

                    $booking->user->notify(new PaymentReceiptNotification($booking));
                } catch (Throwable $exception) {
                    // Không để lỗi SMTP làm mất trạng thái thanh toán thành công.
                    report($exception);
                }
            });

            return true;
        });
    }

    private function markFailed(Payment $payment, string $responseCode, array $payload): void
    {
        DB::transaction(function () use ($payment, $responseCode, $payload) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'success') {
                $payment->update([
                    'status' => 'failed',
                    'response_code' => $responseCode,
                    'payload' => $payload,
                ]);
            }
        });
    }

    private function authorizeBooking(Booking $booking): void
    {
        abort_unless($booking->user_id === auth()->id(), 403);
    }

    private function authorizePayment(Payment $payment): void
    {
        $payment->loadMissing('booking');
        abort_unless($payment->booking->user_id === auth()->id(), 403);
    }
}

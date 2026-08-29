<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentGatewayService
{
    public function paymentUrl(Payment $payment, string $ipAddress): string
    {
        if (in_array($payment->provider, ['momo', 'vnpay'], true) && $this->isSimulationMode()) {
            return route('payments.simulate', $payment);
        }

        return match ($payment->provider) {
            'demo' => route('payments.demo', $payment),
            'vnpay' => $this->vnpayUrl($payment, $ipAddress),
            'momo' => $this->momoUrl($payment),
            default => throw new RuntimeException('Cổng thanh toán không được hỗ trợ.'),
        };
    }

    public function mode(): string
    {
        $mode = (string) config('cinema.payment_mode', 'simulate');

        return in_array($mode, ['simulate', 'sandbox', 'production'], true) ? $mode : 'simulate';
    }

    public function isSimulationMode(): bool
    {
        return $this->mode() === 'simulate';
    }

    public function isConfigured(string $provider): bool
    {
        return match ($provider) {
            'vnpay' => (string) config('cinema.vnpay.tmn_code') !== ''
                && (string) config('cinema.vnpay.hash_secret') !== '',
            'momo' => (string) config('cinema.momo.partner_code') !== ''
                && (string) config('cinema.momo.access_key') !== ''
                && (string) config('cinema.momo.secret_key') !== '',
            'demo' => (bool) config('cinema.demo_payment_enabled'),
            'sepay' => $this->isSepayConfigured(),
            default => false,
        };
    }

    public function isSepayConfigured(): bool
    {
        return (string) config('cinema.sepay.api_key') !== ''
            && (string) config('cinema.sepay.bank_code') !== ''
            && (string) config('cinema.sepay.account_number') !== '';
    }

    public function sepayQrUrl(Payment $payment): string
    {
        if (! $this->isSepayConfigured()) {
            throw new RuntimeException('Chưa cấu hình SePay.');
        }

        return rtrim((string) config('cinema.sepay.qr_url'), '?').'?'.http_build_query([
            'acc' => config('cinema.sepay.account_number'),
            'bank' => config('cinema.sepay.bank_code'),
            'amount' => (int) $payment->amount,
            'des' => $payment->request_id,
            'template' => 'compact',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function hasValidSepayWebhookKey(Request $request): bool
    {
        $key = (string) config('cinema.sepay.api_key');

        return $key !== '' && hash_equals('Apikey '.$key, trim((string) $request->header('Authorization')));
    }

    public function isValidSepayTransfer(array $payload): bool
    {
        $expected = preg_replace('/\D+/', '', (string) config('cinema.sepay.account_number'));
        $received = preg_replace('/\D+/', '', (string) ($payload['accountNumber'] ?? ''));

        return $expected !== '' && $expected === $received
            && strtolower((string) ($payload['transferType'] ?? '')) === 'in'
            && (int) ($payload['transferAmount'] ?? 0) > 0;
    }

    public function verifyVnpay(array $data): bool
    {
        $secret = (string) config('cinema.vnpay.hash_secret');
        $tmnCode = (string) config('cinema.vnpay.tmn_code');
        if ($secret === '' || $tmnCode === '' || ($data['vnp_TmnCode'] ?? null) !== $tmnCode) {
            return false;
        }

        $secureHash = strtolower((string) ($data['vnp_SecureHash'] ?? ''));
        unset($data['vnp_SecureHash'], $data['vnp_SecureHashType']);
        $data = array_filter(
            $data,
            fn ($value, $key) => str_starts_with((string) $key, 'vnp_') && $value !== null && $value !== '',
            ARRAY_FILTER_USE_BOTH,
        );
        $expected = hash_hmac('sha512', $this->vnpayHashData($data), $secret);

        return $secureHash !== '' && hash_equals($expected, $secureHash);
    }

    public function verifyMomo(array $data): bool
    {
        $accessKey = (string) config('cinema.momo.access_key');
        $secretKey = (string) config('cinema.momo.secret_key');
        $partnerCode = (string) config('cinema.momo.partner_code');
        if ($accessKey === '' || $secretKey === '' || $partnerCode === '' || ($data['partnerCode'] ?? null) !== $partnerCode) {
            return false;
        }

        $fields = [
            'accessKey' => $accessKey,
            'amount' => $data['amount'] ?? '',
            'extraData' => $data['extraData'] ?? '',
            'message' => $data['message'] ?? '',
            'orderId' => $data['orderId'] ?? '',
            'orderInfo' => $data['orderInfo'] ?? '',
            'orderType' => $data['orderType'] ?? '',
            'partnerCode' => $data['partnerCode'] ?? '',
            'payType' => $data['payType'] ?? '',
            'requestId' => $data['requestId'] ?? '',
            'responseTime' => $data['responseTime'] ?? '',
            'resultCode' => $data['resultCode'] ?? '',
            'transId' => $data['transId'] ?? '',
        ];

        $raw = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode('&');
        $expected = hash_hmac('sha256', $raw, $secretKey);

        return isset($data['signature'])
            && hash_equals($expected, strtolower((string) $data['signature']));
    }

    private function vnpayUrl(Payment $payment, string $ipAddress): string
    {
        $tmnCode = (string) config('cinema.vnpay.tmn_code');
        $secret = (string) config('cinema.vnpay.hash_secret');

        if (! $this->isConfigured('vnpay')) {
            throw new RuntimeException('Chưa cấu hình VNPAY_TMN_CODE và VNPAY_HASH_SECRET.');
        }

        $expiresAt = $payment->booking->expires_at ?? now()->addMinutes(15);
        $data = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => (int) $payment->amount * 100,
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $payment->request_id,
            'vnp_OrderInfo' => 'Thanh toan don '.$payment->booking->code,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => route('payments.vnpay.return'),
            'vnp_IpAddr' => $ipAddress,
            'vnp_CreateDate' => now('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_ExpireDate' => $expiresAt->timezone('Asia/Ho_Chi_Minh')->format('YmdHis'),
        ];

        $query = $this->vnpayHashData($data);
        $hash = hash_hmac('sha512', $query, $secret);

        return rtrim((string) config('cinema.vnpay.url'), '?').'?'.$query.'&vnp_SecureHash='.$hash;
    }

    private function momoUrl(Payment $payment): string
    {
        $partnerCode = (string) config('cinema.momo.partner_code');
        $accessKey = (string) config('cinema.momo.access_key');
        $secretKey = (string) config('cinema.momo.secret_key');

        if (! $this->isConfigured('momo')) {
            throw new RuntimeException('Chưa cấu hình thông tin tài khoản MoMo sandbox.');
        }

        $requestId = $payment->request_id;
        $orderId = $payment->request_id;
        $amount = (string) (int) $payment->amount;
        $orderInfo = 'Thanh toán đơn '.$payment->booking->code;
        $redirectUrl = route('payments.momo.return');
        $ipnUrl = route('payments.momo.ipn');
        $extraData = '';
        $requestType = 'captureWallet';

        $raw = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}&ipnUrl={$ipnUrl}"
            ."&orderId={$orderId}&orderInfo={$orderInfo}&partnerCode={$partnerCode}"
            ."&redirectUrl={$redirectUrl}&requestId={$requestId}&requestType={$requestType}";

        $payload = [
            'partnerCode' => $partnerCode,
            'storeName' => config('app.name'),
            'storeId' => Str::slug((string) config('app.name')),
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'requestType' => $requestType,
            'autoCapture' => true,
            'extraData' => $extraData,
            'signature' => hash_hmac('sha256', $raw, $secretKey),
        ];

        $response = Http::timeout(30)
            ->acceptJson()
            ->asJson()
            ->post((string) config('cinema.momo.url'), $payload);

        $responsePayload = $response->json() ?? [];
        if (! $response->successful()
            || (int) ($responsePayload['resultCode'] ?? -1) !== 0
            || (string) ($responsePayload['partnerCode'] ?? '') !== $partnerCode
            || (string) ($responsePayload['requestId'] ?? '') !== $requestId
            || (string) ($responsePayload['orderId'] ?? '') !== $orderId
            || (int) ($responsePayload['amount'] ?? -1) !== (int) $amount
            || (isset($responsePayload['signature'])
                && ! $this->verifyMomoCreateResponse($responsePayload, $accessKey, $secretKey))
            || empty($responsePayload['payUrl'])) {
            throw new RuntimeException((string) ($responsePayload['message'] ?? 'Không thể kết nối cổng MoMo.'));
        }

        $payment->update(['payload' => $responsePayload, 'status' => 'pending']);

        return (string) $responsePayload['payUrl'];
    }

    private function verifyMomoCreateResponse(array $data, string $accessKey, string $secretKey): bool
    {
        $fields = [
            'accessKey' => $accessKey,
            'amount' => $data['amount'] ?? '',
            'message' => $data['message'] ?? '',
            'orderId' => $data['orderId'] ?? '',
            'partnerCode' => $data['partnerCode'] ?? '',
            'payUrl' => $data['payUrl'] ?? '',
            'requestId' => $data['requestId'] ?? '',
            'responseTime' => $data['responseTime'] ?? '',
            'resultCode' => $data['resultCode'] ?? '',
        ];
        $raw = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode('&');
        $expected = hash_hmac('sha256', $raw, $secretKey);

        return isset($data['signature'])
            && hash_equals($expected, strtolower((string) $data['signature']));
    }

    private function vnpayHashData(array $data): string
    {
        ksort($data);

        return collect($data)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $key) => urlencode((string) $key).'='.urlencode((string) $value))
            ->implode('&');
    }
}

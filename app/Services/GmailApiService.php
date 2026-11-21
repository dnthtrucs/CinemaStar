<?php

namespace App\Services;

use App\Models\GmailConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GmailApiService
{
    public const SEND_SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    public function configured(): bool
    {
        return (string) config('services.gmail.client_id') !== '' && (string) config('services.gmail.client_secret') !== '';
    }

    public function redirectUri(): string
    {
        return (string) (config('services.gmail.redirect_uri') ?: rtrim(config('app.url'), '/').'/admin/email/gmail/callback');
    }

    public function connection(): ?GmailConnection
    {
        return GmailConnection::where('provider', 'gmail')->where('client_id', config('services.gmail.client_id'))->first();
    }

    public function authorizationUrl(string $state, string $verifier): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('Cần cấu hình Google Client ID và Client Secret trong Render trước.');
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.gmail.client_id'), 'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code', 'scope' => 'openid email '.self::SEND_SCOPE,
            'access_type' => 'offline', 'prompt' => 'consent select_account', 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function connect(string $code, string $verifier, int $userId): void
    {
        $tokens = $this->tokenRequest([
            'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $this->redirectUri(), 'grant_type' => 'authorization_code',
        ]);
        if (! in_array(self::SEND_SCOPE, explode(' ', (string) ($tokens['scope'] ?? '')), true)
            || ! is_string($tokens['refresh_token'] ?? null) || $tokens['refresh_token'] === '') {
            throw new RuntimeException('Chưa được cấp quyền gửi thư lâu dài. Vui lòng kết nối lại và cho phép gửi email.');
        }
        try {
            $response = Http::withToken($tokens['access_token'])->acceptJson()->connectTimeout(4)->timeout(10)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');
        } catch (Throwable) {
            throw new RuntimeException('Chưa xác minh được tài khoản Google. Vui lòng thử lại.');
        }
        $email = $response->json('email');
        if (! $response->successful() || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $response->json('email_verified') !== true) {
            throw new RuntimeException('Tài khoản Google chưa có địa chỉ email được xác minh.');
        }
        GmailConnection::updateOrCreate(['provider' => 'gmail'], [
            'email' => $email, 'client_id' => config('services.gmail.client_id'),
            'refresh_token' => $tokens['refresh_token'], 'connected_by' => $userId,
        ]);
    }

    private function tokenRequest(array $fields): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Thiếu thông tin kết nối Gmail API.');
        }
        try {
            $response = Http::asForm()->acceptJson()->connectTimeout(4)->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.gmail.client_id'), 'client_secret' => config('services.gmail.client_secret'), ...$fields,
            ]);
        } catch (Throwable) {
            throw new RuntimeException('Không kết nối được Google để lấy quyền gửi thư.');
        }
        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '' || strtolower((string) $response->json('token_type')) !== 'bearer') {
            throw new RuntimeException('Google từ chối quyền gửi thư. Vui lòng kết nối lại Gmail trong quản trị.');
        }

        return $response->json();
    }

    public function send(string $mime, GmailConnection $connection): string
    {
        if (! $this->configured() || $connection->client_id !== config('services.gmail.client_id')) {
            throw new RuntimeException('Cần kết nối lại tài khoản Gmail.');
        }
        $key = 'gmail-api-access:'.hash('sha256', $connection->client_id.'|'.$connection->refresh_token);
        $cached = Cache::get($key);
        if (is_string($cached)) {
            $accessToken = Crypt::decryptString($cached);
        } else {
            $tokens = $this->tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $connection->refresh_token]);
            $accessToken = $tokens['access_token'];
            Cache::put($key, Crypt::encryptString($accessToken), max(1, min(3600, (int) ($tokens['expires_in'] ?? 300)) - 60));
        }
        try {
            // Never retry this POST automatically: a timeout may occur after Google accepted the email.
            $response = Http::withToken($accessToken)->acceptJson()->connectTimeout(4)->timeout(10)
                ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                    'raw' => rtrim(strtr(base64_encode($mime), '+/', '-_'), '='),
                ]);
        } catch (Throwable) {
            throw new RuntimeException('Chưa xác nhận được kết quả gửi thư từ Gmail. Kiểm tra thư đã gửi trước khi gửi lại.');
        }
        if ($response->status() === 401) {
            Cache::forget($key);
        }
        $id = $response->json('id');
        if (! $response->successful() || ! is_string($id) || $id === '') {
            throw new RuntimeException('Gmail chưa chấp nhận email. Kiểm tra quyền gửi, hạn mức và kết nối Gmail.');
        }

        return $id;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GmailApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GmailConnectionController extends Controller
{
    public function index(GmailApiService $gmail)
    {
        return view('admin.email.index', ['connection' => $gmail->connection(), 'configured' => $gmail->configured(), 'redirectUri' => $gmail->redirectUri()])->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function connect(Request $request, GmailApiService $gmail)
    {
        $state = bin2hex(random_bytes(32));
        $verifier = bin2hex(random_bytes(32));
        try {
            $url = $gmail->authorizationUrl($state, $verifier);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
        $request->session()->put('gmail_oauth', ['state' => hash('sha256', $state), 'verifier' => $verifier, 'expires' => time() + 600, 'user_id' => $request->user()->id]);

        return redirect()->away($url)->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function callback(Request $request, GmailApiService $gmail)
    {
        $stored = $request->session()->get('gmail_oauth');
        abort_unless(is_array($stored) && ($stored['user_id'] ?? null) === $request->user()->id
            && ($stored['expires'] ?? 0) >= time() && is_string($request->query('state'))
            && hash_equals($stored['state'], hash('sha256', $request->query('state'))), 403, 'Phiên kết nối Gmail không hợp lệ hoặc đã hết hạn.');
        $request->session()->forget('gmail_oauth');
        if ($request->filled('error')) {
            return redirect()->route('admin.email.index')->with('error', 'Bạn chưa cấp quyền gửi email. Kết nối hiện tại không thay đổi.');
        }
        $code = $request->query('code');
        abort_unless(is_string($code) && $code !== '' && strlen($code) < 4096, 422);
        try {
            $gmail->connect($code, $stored['verifier'], $request->user()->id);
        } catch (Throwable $exception) {
            Log::warning('Gmail connection failed', ['exception_type' => get_class($exception)]);

            return redirect()->route('admin.email.index')->with('error', 'Chưa kết nối được Gmail. Kiểm tra Gmail API đã bật và đã cấp quyền gửi thư, rồi thử kết nối lại.');
        }

        return redirect()->route('admin.email.index')->with('success', 'Đã kết nối Gmail.')->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}

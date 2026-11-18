<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustCloudProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductionHttpsTest extends TestCase
{
    public function test_explicit_trusted_proxy_recognizes_https_without_trusting_forwarded_hostname(): void
    {
        config(['deployment.trusted_proxies' => ['10.0.0.1']]);
        $request = Request::create('http://cinema.example.test/login', 'GET', server: [
            'REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443', 'HTTP_X_FORWARDED_HOST' => 'wrong.example.test',
        ]);
        (new TrustCloudProxies)->handle($request, function ($request) {
            $this->assertTrue($request->isSecure());
            $this->assertSame('cinema.example.test', $request->getHost());
            $this->assertSame(443, $request->getPort());

            return response('ok');
        });
    }

    public function test_untrusted_client_cannot_claim_https(): void
    {
        config(['deployment.trusted_proxies' => ['10.0.0.1']]);
        $request = Request::create('http://cinema.example.test/login', 'GET', server: [
            'REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        (new TrustCloudProxies)->handle($request, function ($request) {
            $this->assertFalse($request->isSecure());

            return response('ok');
        });
    }
}

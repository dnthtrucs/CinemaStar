<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustCloudProxies extends TrustProxies
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;

    protected function proxies()
    {
        return config('deployment.trusted_proxies', []);
    }
}

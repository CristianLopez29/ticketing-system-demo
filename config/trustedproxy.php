<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Read by Illuminate\Http\Middleware\TrustProxies, which is already in the default
    | global stack. Comma-separated IPs or CIDRs, or "*" to trust whatever forwarded the
    | request (correct behind Cloudflare, wrong on a directly exposed host).
    |
    | This is not cosmetic: without it, X-Forwarded-For is ignored and $request->ip()
    | returns the reverse proxy's address for every request. Every per-IP rate limit then
    | shares a single bucket, so one client can exhaust the login throttle for everybody.
    |
    */
    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1,::1'),

];

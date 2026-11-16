<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limit embed/m3u8 theo IP: tránh scraping hàng loạt.
 * Giới hạn mặc định: 120 request/phút mỗi IP.
 */
class RateLimitEmbed
{
    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next, int $perMinute = 120): Response
    {
        $key = 'embed:'.$request->ip();

        if ($this->limiter->tooManyAttempts($key, $perMinute)) {
            return response('Too Many Requests', 429)
                ->header('Retry-After', (string) $this->limiter->availableIn($key))
                ->header('Content-Type', 'text/plain');
        }

        $this->limiter->hit($key, 60);

        return $next($request);
    }
}

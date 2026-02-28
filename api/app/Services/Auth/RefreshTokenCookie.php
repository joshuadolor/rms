<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

final readonly class RefreshTokenCookie
{
    public function name(): string
    {
        return (string) config('refresh_tokens.cookie.name', 'rms_refresh');
    }

    /**
     * Resolve cookie domain from request so it works on both localhost and rms.local.
     * - localhost / 127.0.0.1 → null (cookie bound to that host, sent on refresh).
     * - Otherwise use REFRESH_TOKEN_COOKIE_DOMAIN (e.g. .rms.local) so cookie is sent on all subdomains.
     */
    public function resolveDomain(Request $request): ?string
    {
        $host = strtolower((string) $request->getHost());
        if ($host === 'localhost' || $host === '127.0.0.1') {
            return null;
        }

        $configured = config('refresh_tokens.cookie.domain');

        return $configured && $configured !== '' ? $configured : null;
    }

    public function make(string $plainToken, ?Request $request = null): Cookie
    {
        $ttlDays = (int) config('refresh_tokens.cookie.ttl_days', 30);
        $minutes = max(1, $ttlDays * 24 * 60);

        $path = (string) config('refresh_tokens.cookie.path', '/');
        $domain = $request ? $this->resolveDomain($request) : (config('refresh_tokens.cookie.domain') ?: null);
        $sameSite = strtolower((string) config('refresh_tokens.cookie.same_site', 'lax'));
        $secure = ! app()->environment('local') || $sameSite === 'none';

        return cookie(
            $this->name(),
            $plainToken,
            $minutes,
            $path,
            $domain,
            $secure,
            true,
            false,
            $sameSite
        );
    }

    public function forget(?Request $request = null): Cookie
    {
        $path = (string) config('refresh_tokens.cookie.path', '/');
        $domain = $request ? $this->resolveDomain($request) : (config('refresh_tokens.cookie.domain') ?: null);
        $sameSite = strtolower((string) config('refresh_tokens.cookie.same_site', 'lax'));
        $secure = ! app()->environment('local') || $sameSite === 'none';

        return cookie(
            $this->name(),
            '',
            -60,
            $path,
            $domain,
            $secure,
            true,
            false,
            $sameSite
        );
    }
}


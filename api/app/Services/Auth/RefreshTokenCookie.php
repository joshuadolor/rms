<?php

namespace App\Services\Auth;

use Symfony\Component\HttpFoundation\Cookie;

final readonly class RefreshTokenCookie
{
    public function name(): string
    {
        return (string) config('refresh_tokens.cookie.name', 'rms_refresh');
    }

    public function make(string $plainToken): Cookie
    {
        $ttlDays = (int) config('refresh_tokens.cookie.ttl_days', 30);
        $minutes = max(1, $ttlDays * 24 * 60);

        $path = (string) config('refresh_tokens.cookie.path', '/');
        $domain = config('refresh_tokens.cookie.domain');
        $sameSite = strtolower((string) config('refresh_tokens.cookie.same_site', 'lax'));
        $secure = ! app()->environment('local') || $sameSite === 'none';

        // cookie() helper signature is minutes-based; this returns Symfony Cookie instance.
        return cookie(
            $this->name(),
            $plainToken,
            $minutes,
            $path,
            $domain ?: null,
            $secure,
            true,
            false,
            $sameSite
        );
    }

    public function forget(): Cookie
    {
        $path = (string) config('refresh_tokens.cookie.path', '/');
        $domain = config('refresh_tokens.cookie.domain');
        $sameSite = strtolower((string) config('refresh_tokens.cookie.same_site', 'lax'));
        $secure = ! app()->environment('local') || $sameSite === 'none';

        return cookie(
            $this->name(),
            '',
            -60,
            $path,
            $domain ?: null,
            $secure,
            true,
            false,
            $sameSite
        );
    }
}


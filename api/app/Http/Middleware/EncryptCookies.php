<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * Refresh tokens are random secrets already; excluding them keeps refresh
     * token rotation independent of Sanctum's "stateful" middleware detection.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Laravel default: keep XSRF cookie readable by JS.
        'XSRF-TOKEN',
    ];

    public function handle($request, Closure $next)
    {
        $refreshCookieName = (string) config('refresh_tokens.cookie.name', 'rms_refresh');

        $this->except = array_values(array_unique([
            ...$this->except,
            $refreshCookieName,
        ]));

        return parent::handle($request, $next);
    }
}


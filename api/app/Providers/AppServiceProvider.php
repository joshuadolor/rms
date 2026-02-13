<?php

namespace App\Providers;

use App\Domain\Auth\Contracts\SocialAccountRepositoryInterface;
use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\SocialAccountRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\UserRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(SocialAccountRepositoryInterface::class, SocialAccountRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthRateLimiting();
    }

    private function configureAuthRateLimiting(): void
    {
        RateLimiter::for('auth.login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('auth.register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('auth.forgot-password', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('auth.social', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}

<?php

namespace App\Providers;

use App\Contracts\TranslationServiceInterface;
use App\Domain\Auth\Contracts\SocialAccountRepositoryInterface;
use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\RestaurantRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\SocialAccountRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\UserRepository;
use App\Services\Translation\LibreTranslateService;
use App\Services\Translation\StubTranslationService;
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
        $this->app->bind(RestaurantRepositoryInterface::class, RestaurantRepository::class);

        $this->app->bind(TranslationServiceInterface::class, function (): TranslationServiceInterface {
            $driver = config('translation.driver');
            $url = config('translation.libre_translate.url', '');
            if ($driver === 'libre' && $url !== '') {
                return new LibreTranslateService(
                    $url,
                    config('translation.libre_translate.api_key')
                );
            }

            return new StubTranslationService;
        });
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
            // Relax in local so e2e (and manual testing) can run multiple registers without 429
            $limit = app()->environment('local') ? 30 : 3;
            return Limit::perMinute($limit)->by($request->ip());
        });

        RateLimiter::for('auth.forgot-password', function (Request $request) {
            $limit = app()->environment('local') ? 30 : 3;
            return Limit::perMinute($limit)->by($request->ip());
        });

        RateLimiter::for('auth.social', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('translate', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}

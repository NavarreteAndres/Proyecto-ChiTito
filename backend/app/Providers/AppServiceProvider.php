<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(5)->by($r->ip()), Limit::perMinute(5)->by(strtolower((string)$r->input('email')).'|'.$r->ip())]);
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(100)->by($r->user()?->id ?? $r->ip()));
    }
}

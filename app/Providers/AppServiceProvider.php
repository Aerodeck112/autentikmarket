<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Blog\BlogDraftGenerator;
use App\Services\Blog\TemplateBlogDraftGenerator;
use App\Services\Updates\ApplicationRuntime;
use App\Services\Updates\ArtisanRuntime;
use App\Support\AppVersion;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApplicationRuntime::class, ArtisanRuntime::class);
        $this->app->bind(BlogDraftGenerator::class, TemplateBlogDraftGenerator::class);
    }

    public function boot(): void
    {
        // RBAC: orice can('permisiune') verifică tabelele roles/permissions (server-side).
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            if (str_contains($ability, '.') && $user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        Paginator::defaultView('components.pagination');

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('forms', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('scan', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('map', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));

        View::composer('*', function ($view) {
            $view->with(['appVersion' => AppVersion::current(), 'shopUrl' => Settings::shopUrl()]);
        });
    }
}

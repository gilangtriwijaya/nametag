<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\EmployeeQrToken;
use App\Observers\StatistikPublikObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Support\OpdContext;
use Illuminate\Support\Facades\Blade;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpdContext::class, fn() => new OpdContext());
        $this->app->alias(OpdContext::class, 'opd.context');
        // helper service for view utilities
        $this->app->singleton(\App\Support\ViewHelpers::class, fn() => new \App\Support\ViewHelpers());

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('publik', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Ensure URL generation uses the configured app URL (handles subpath deployments)
        if (config('app.url')) {
            URL::forceRootUrl(config('app.url'));
            // also force scheme if present in APP_URL
            $scheme = parse_url(config('app.url'), PHP_URL_SCHEME);
            if ($scheme) {
                URL::forceScheme($scheme);
            }
        }

        // Blade helper: @datetime($value, $format = 'd M Y H:i')
        // Renders $value in Asia/Jakarta timezone; returns '—' for null/empty.
        Blade::directive('datetime', function ($expression) {
            return "<?php echo app('\\App\\Support\\ViewHelpers')->datetime({$expression}); ?>";
        });

        Employee::observe(StatistikPublikObserver::class);
        EmployeeQrToken::observe(StatistikPublikObserver::class);
    }
}

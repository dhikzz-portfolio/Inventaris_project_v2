<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pastikan scheme HTTPS diterapkan saat di belakang reverse proxy (Traefik) atau production domain
        if (request()->header('x-forwarded-proto') === 'https' || request()->server('HTTPS') === 'on') {
            URL::forceScheme('https');
        } elseif (app()->environment('production') && str_starts_with((string) config('app.url'), 'https://') && ! in_array(request()->getHost(), ['localhost', '127.0.0.1'])) {
            URL::forceScheme('https');
        }

        // R9: Audit log tidak bisa diubah atau dihapus dari UI maupun API
        Activity::updating(function () {
            throw new \DomainException('Audit log bersifat immutable dan tidak dapat diubah.');
        });

        Activity::deleting(function () {
            throw new \DomainException('Audit log bersifat immutable dan tidak dapat dihapus.');
        });
    }
}

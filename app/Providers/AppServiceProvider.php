<?php

namespace App\Providers;

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
        // R9: Audit log tidak bisa diubah atau dihapus dari UI maupun API
        Activity::updating(function () {
            throw new \DomainException('Audit log bersifat immutable dan tidak dapat diubah.');
        });

        Activity::deleting(function () {
            throw new \DomainException('Audit log bersifat immutable dan tidak dapat dihapus.');
        });
    }
}

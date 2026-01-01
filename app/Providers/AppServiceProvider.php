<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\ImageProxy;
use Illuminate\Support\ServiceProvider;
use Onliner\ImgProxy\UrlBuilder;

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
        if (ImageProxy::isEnabled()) {
            $this->app->bind(UrlBuilder::class, function () {
                return UrlBuilder::signed(
                    key: config('services.imgproxy.key'),
                    salt: config('services.imgproxy.salt')
                );
            });
        }
    }
}

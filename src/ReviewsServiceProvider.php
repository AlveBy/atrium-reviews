<?php

declare(strict_types=1);

namespace Atrium\Reviews;

use Atrium\Reviews\Models\Review;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

final class ReviewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atrium-reviews.php', 'atrium-reviews');
        $this->app->singleton(Reviews::class);

        config()->push('atrium.resources', Review::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'atrium-reviews');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atrium-reviews');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'atrium-reviews');

        if (config('atrium-reviews.routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        // The public theme draws the views listed here under every record
        // page; ours shows the reviews of the records that take them.
        if (config('atrium-reviews.theme', true)) {
            config()->push('atrium-theme.record_views', 'atrium-reviews::theme-record');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/atrium-reviews.php' => config_path('atrium-reviews.php'),
            ], 'atrium-reviews-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/atrium-reviews'),
            ], 'atrium-reviews-views');
        }
    }
}

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews;

use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Support\ConfigList;
use Atrium\Reviews\Console\RecountCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * The model and the migrations come from the manifest in composer.json
 * (`extra.atrium`) once `atrium:module:install alveby/atrium-reviews` has
 * run; this adds the public side.
 */
final class ReviewsServiceProvider extends ServiceProvider
{
    public const MODULE = 'alveby/atrium-reviews';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atrium-reviews.php', 'atrium-reviews');
        $this->app->singleton(Reviews::class);
        $this->app->singleton(ReviewCounters::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'atrium-reviews');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atrium-reviews');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'atrium-reviews');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/atrium-reviews.php' => config_path('atrium-reviews.php'),
            ], 'atrium-reviews-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/atrium-reviews'),
            ], 'atrium-reviews-views');
        }

        // Required but not installed: no table yet, so nothing on the site.
        if (! $this->app->make(Modules::class)->isInstalled(self::MODULE)) {
            return;
        }

        if ($this->app->runningInConsole()) {
            $this->commands([RecountCommand::class]);
        }

        if (config('atrium-reviews.routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        // The public theme draws the views listed here under every record
        // page; ours shows the reviews of the records that take them.
        if (config('atrium-reviews.theme', true)) {
            ConfigList::add('atrium-theme.record_views', 'atrium-reviews::theme-record');
        }
    }
}

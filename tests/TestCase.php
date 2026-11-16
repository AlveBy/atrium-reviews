<?php

declare(strict_types=1);

namespace Atrium\Reviews\Tests;

use Atrium\Admin\AtriumServiceProvider;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Reviews\ReviewsServiceProvider;
use Atrium\Reviews\Tests\Fixtures\Article;
use Atrium\Reviews\Tests\Fixtures\Product;
use Atrium\Theme\ThemeServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Tighten\Ziggy\ZiggyServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return array_values(array_filter([
            InertiaServiceProvider::class,
            ZiggyServiceProvider::class,
            AtriumServiceProvider::class,
            // The public theme is optional: the reviews work without it.
            class_exists(ThemeServiceProvider::class) ? ThemeServiceProvider::class : null,
            ReviewsServiceProvider::class,
        ]));
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $config->set('app.name', 'Demo shop');
        // CI runs the suite against MySQL and PostgreSQL too.
        $target = env('DB_CONNECTION');
        $config->set('database.default', in_array($target, ['mysql', 'pgsql'], true) ? $target : 'testing');
        // The repository's .env.example turns the demo on; it is not wanted here.
        $config->set('atrium.demo', false);
        $config->set('atrium.auth.two_factor.enabled', false);
        $config->set('atrium.dashboard.timings.enabled', false);
        // Installed, as atrium:module:install leaves it: the model and the
        // migrations come from the manifest in composer.json.
        $config->set('atrium.modules', [ReviewsServiceProvider::MODULE]);
        $config->set('atrium.resources', [Product::class]);
        $config->set('atrium.public_urls', [
            Product::class => ['prefix' => 'products'],
            Article::class => ['prefix' => 'articles'],
        ]);
        $config->set('atrium-reviews.models', [
            Product::class => 'Product',
            'App\\Models\\NotInstalled' => 'Product',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        PublicUrl::forgetPaths();
        RateLimiter::clear('atrium-reviews:127.0.0.1');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function admin(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $admin->forceFill(['is_superadmin' => true])->save();

        return $admin;
    }
}

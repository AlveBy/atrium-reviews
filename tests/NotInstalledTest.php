<?php

declare(strict_types=1);

namespace Atrium\Reviews\Tests;

use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Reviews\Models\Review;
use Illuminate\Support\Facades\Schema;

/**
 * Required with Composer but not installed with atrium:module:install.
 */
final class NotInstalledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.modules', []);
    }

    public function test_a_module_that_is_not_installed_stays_out_of_the_panel_and_the_site(): void
    {
        $this->assertNotContains(Review::class, app(ResourceRegistry::class)->all());
        $this->assertFalse(app('router')->has('admin.reviews.index'));
        $this->assertFalse(app('router')->has('atrium.reviews.index'));
        $this->assertNotContains('atrium-reviews::theme-record', (array) config('atrium-theme.record_views', []));
        $this->assertFalse(Schema::hasTable('reviews'));

        $this->get('/reviews')->assertNotFound();
    }
}

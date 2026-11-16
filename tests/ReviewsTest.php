<?php

declare(strict_types=1);

namespace Atrium\Reviews\Tests;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Reviews\Models\Review;
use Atrium\Reviews\Reviews;
use Atrium\Reviews\ReviewStatus;
use Atrium\Reviews\Tests\Fixtures\Article;
use Atrium\Reviews\Tests\Fixtures\Product;
use Atrium\Theme\ThemeServiceProvider;
use Illuminate\Support\Facades\Blade;

final class ReviewsTest extends TestCase
{
    public function test_the_module_is_in_the_admin_with_its_routes(): void
    {
        $this->assertContains(Review::class, app(ResourceRegistry::class)->all());
        $this->assertContains('atrium-reviews::theme-record', config('atrium-theme.record_views'));
        $this->assertTrue(app('router')->has('admin.reviews.index'));
        $this->assertTrue(app('router')->has('atrium.reviews.index'));
        $this->assertTrue(app('router')->has('atrium.reviews.store'));
    }

    public function test_the_site_page_shows_published_reviews_with_their_rating(): void
    {
        $this->review(['author_name' => 'Anna', 'rating' => 5, 'text' => 'Great shop']);
        $this->review(['author_name' => 'Boris', 'rating' => 4, 'text' => "Fast\ndelivery"]);
        $this->review(['author_name' => 'Hidden', 'rating' => 1, 'status' => ReviewStatus::New]);
        $this->review(['author_name' => 'Spam', 'rating' => 1, 'status' => ReviewStatus::Rejected]);
        $this->review(['author_name' => 'About a product', 'rating' => 1], Product::query()->create(['name' => 'Phone', 'code' => 'phone']));

        $response = $this->get('/reviews')->assertOk()
            ->assertSee('Anna')
            ->assertSee('Fast<br />', false)
            ->assertSee('4.5 out of 5')
            ->assertSee('2 reviews')
            ->assertDontSee('Hidden')
            ->assertDontSee('Spam')
            ->assertDontSee('About a product');

        $jsonLd = $this->jsonLd($response->getContent());
        $this->assertSame('Organization', $jsonLd['@type']);
        $this->assertSame('Demo shop', $jsonLd['name']);
        $this->assertSame(['@type' => 'AggregateRating', 'ratingValue' => 4.5, 'reviewCount' => 2, 'bestRating' => 5, 'worstRating' => 1], $jsonLd['aggregateRating']);
        $this->assertCount(2, $jsonLd['review']);
        $this->assertSame('Anna', $jsonLd['review'][1]['author']['name']);
    }

    public function test_no_rating_markup_without_published_reviews(): void
    {
        $this->review(['status' => ReviewStatus::New]);

        $response = $this->get('/reviews')->assertOk()->assertSee('No reviews yet');

        $this->assertStringNotContainsString('application/ld+json', (string) $response->getContent());
    }

    public function test_a_visitor_sends_a_review_that_waits_for_moderation(): void
    {
        $this->from('/reviews')->post('/reviews', ['author_name' => ' Vera ', 'rating' => 5, 'text' => 'Lovely service'])
            ->assertRedirect(url('/reviews').'#reviews')
            ->assertSessionHas('atrium-reviews.sent', true);

        $review = Review::query()->sole();
        $this->assertSame('Vera', $review->author_name);
        $this->assertSame(ReviewStatus::New, $review->status);
        $this->assertNull($review->reviewable_type);
        $this->assertSame(Site::query()->value('id'), $review->site_id);
        $this->assertNotNull($review->posted_at);

        $this->get('/reviews')->assertDontSee('Lovely service');

        $review->update(['status' => ReviewStatus::Published]);

        $this->get('/reviews')->assertSee('Lovely service');
    }

    public function test_the_honeypot_drops_a_bot_quietly(): void
    {
        $this->from('/reviews')->post('/reviews', ['author_name' => 'Bot', 'rating' => 5, 'text' => 'Buy now', 'website' => 'http://spam.test'])
            ->assertRedirect()
            ->assertSessionHas('atrium-reviews.sent', true);

        $this->assertSame(0, Review::query()->count());
    }

    public function test_sending_is_rate_limited(): void
    {
        config(['atrium-reviews.rate_limit' => ['attempts' => 2, 'minutes' => 10]]);

        foreach ([1, 2, 3] as $i) {
            $response = $this->from('/reviews')->post('/reviews', ['author_name' => 'Pat', 'rating' => 3, 'text' => "Review {$i}"]);
        }

        $response->assertSessionHasErrors(['text'], null, 'reviews');
        $this->assertSame(2, Review::query()->count());
    }

    public function test_invalid_input_comes_back_with_errors(): void
    {
        $this->from('/reviews')->post('/reviews', ['author_name' => '', 'rating' => 6, 'text' => ''])
            ->assertSessionHasErrors(['author_name', 'rating', 'text'], null, 'reviews');

        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_review_about_a_record_of_another_module(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $reviews = app(Reviews::class);

        $this->from('/products/phone')->post('/reviews', ['author_name' => 'Ivan', 'rating' => 4, 'text' => 'Good phone', 'about' => $reviews->key($phone)])
            ->assertSessionHasNoErrors();

        $review = Review::query()->sole();
        $this->assertSame($phone->getMorphClass(), $review->reviewable_type);
        $this->assertSame($phone->id, $review->reviewable_id);
        $this->assertTrue($phone->is($review->reviewable));
        $this->assertSame('Products: Phone', $review->subject_label);

        $review->update(['status' => ReviewStatus::Published]);
        $this->review(['rating' => 2], $phone);

        $this->assertSame(['count' => 2, 'average' => 3.0], $reviews->summary($phone));
        $this->assertSame(['count' => 0, 'average' => null], $reviews->summary());
    }

    public function test_only_records_of_the_listed_modules_can_be_reviewed(): void
    {
        $article = Article::query()->create(['name' => 'News', 'code' => 'news']);

        foreach (['review_articles:'.$article->id, 'products:999', 'products:abc', 'App\\Models\\NotInstalled:1'] as $about) {
            $this->from('/reviews')->post('/reviews', ['author_name' => 'Eve', 'rating' => 5, 'text' => 'Hi', 'about' => $about])
                ->assertSessionHasErrors(['about'], null, 'reviews');
        }

        $this->assertSame(0, Review::query()->count());
        $this->assertSame([Product::class => 'Product'], app(Reviews::class)->models());
    }

    public function test_reviews_outlive_the_module_they_are_about(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $about = $this->review(['author_name' => 'Ivan'], $phone);
        $gone = $this->review(['author_name' => 'Old']);
        $gone->forceFill(['reviewable_type' => 'Vendor\\Removed\\Store', 'reviewable_id' => 7])->save();

        // The shop module is required but taken out of the installed ones.
        app(Modules::class)->add(new Module('acme/atrium-shop', resources: [Product::class]));

        $this->assertNull($about->refresh()->reviewable);
        $this->assertNull($gone->refresh()->reviewable);
        $this->assertSame('Product #'.$phone->id.' (removed)', $about->subject_label);
        $this->assertSame('Store #7 (removed)', $gone->subject_label);
        $this->assertSame([], app(Reviews::class)->models());

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee('Store #7 (removed)')
            ->assertSee('Product #'.$phone->id.' (removed)');

        $this->from('/reviews')->post('/reviews', ['author_name' => 'Eve', 'rating' => 5, 'text' => 'Hi', 'about' => 'products:'.$phone->id])
            ->assertSessionHasErrors(['about'], null, 'reviews');
    }

    public function test_a_moderator_publishes_a_review_in_the_admin(): void
    {
        $review = $this->review(['status' => ReviewStatus::New, 'author_name' => 'Nina', 'rating' => 4, 'text' => 'Nice']);

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.reviews.update', $review), [
                'status' => 'published',
                'rating' => 4,
                'author_name' => 'Nina',
                'text' => 'Nice',
                'reviewable_type' => '',
                'reviewable_id' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ReviewStatus::Published, $review->refresh()->status);
        $this->assertNull($review->reviewable_type);
    }

    public function test_reviews_belong_to_their_site(): void
    {
        $this->review(['author_name' => 'Main site']);
        $other = Site::query()->create(['code' => 'second', 'name' => 'Second', 'folder' => 'site2']);
        (new Review(['author_name' => 'Second site', 'rating' => 5, 'text' => 'Hello', 'status' => ReviewStatus::Published]))->forceFill(['site_id' => $other->id])->save();

        $this->get('/reviews')->assertSee('Main site')->assertDontSee('Second site');
        $this->get('/site2/reviews')->assertOk()->assertSee('Second site')->assertDontSee('Main site');
    }

    public function test_the_theme_shows_the_reviews_under_a_reviewable_record(): void
    {
        if (! class_exists(ThemeServiceProvider::class)) {
            $this->markTestSkipped('The public theme is not installed.');
        }

        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        Article::query()->create(['name' => 'News', 'code' => 'news']);
        $this->review(['author_name' => 'Ivan', 'rating' => 4, 'text' => 'Good phone'], $phone);

        $response = $this->get('/products/phone')->assertOk()
            ->assertSee('Good phone')
            ->assertSee('name="about" value="products:'.$phone->id.'"', false);

        $jsonLd = $this->jsonLd($response->getContent());
        $this->assertSame('Product', $jsonLd['@type']);
        $this->assertSame('Phone', $jsonLd['name']);
        $this->assertSame(rtrim(url('/'), '/').'/products/phone', $jsonLd['url']);
        $this->assertSame(1, $jsonLd['aggregateRating']['reviewCount']);

        $this->get('/articles/news')->assertOk()->assertDontSee('id="reviews"', false);
    }

    public function test_a_component_draws_the_reviews_anywhere(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $this->review(['author_name' => 'Ivan', 'rating' => 3, 'text' => 'Fine'], $phone);

        $html = Blade::render('<x-atrium-reviews::reviews :for="$phone" name="Phone" url="https://shop.test/phone" />', ['phone' => $phone]);

        $this->assertStringContainsString('Fine', $html);
        $this->assertSame('https://shop.test/phone', $this->jsonLd($html)['url']);
    }

    public function test_errors_come_back_into_the_form(): void
    {
        $this->from('/reviews')->post('/reviews', ['author_name' => 'Pat', 'rating' => 5, 'text' => '']);

        $this->get('/reviews')
            ->assertSee('value="Pat"', false)
            ->assertSee('The Your review field is required.');
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function review(array $attributes = [], ?Product $about = null): Review
    {
        $review = new Review([
            'author_name' => 'Someone',
            'rating' => 5,
            'text' => 'Text',
            'status' => ReviewStatus::Published,
            ...$attributes,
        ]);

        if ($about !== null) {
            $review->reviewable_type = $about->getMorphClass();
            $review->reviewable_id = $about->id;
        }

        $review->save();

        return $review;
    }

    /**
     * @return array<string,mixed>
     */
    private function jsonLd(string|false $html): array
    {
        $this->assertNotFalse($html);
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }
}

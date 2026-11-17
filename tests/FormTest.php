<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews\Tests;

use Atrium\Admin\Captcha\CaptchaManager;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Reviews\Http\Controllers\ReviewController;
use Atrium\Reviews\Models\Review;
use Atrium\Reviews\Reviews;
use Atrium\Reviews\ReviewStatus;
use Atrium\Reviews\Tests\Fixtures\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The public page of the site's reviews and the form a review is sent
 * with: what is kept, what is refused and how often one address may send.
 */
final class FormTest extends TestCase
{
    private const FORM = ['author_name' => 'Pat', 'rating' => 5, 'text' => 'Fine'];

    /**
     * @return array<string,array{0:array<string,mixed>,1:string|null}>
     */
    public static function inputs(): array
    {
        return [
            'everything filled' => [self::FORM, null],
            'about the site, sent empty' => [self::FORM + ['about' => ''], null],
            'no name' => [['rating' => 5, 'text' => 'Fine'], 'author_name'],
            'name of spaces' => [['author_name' => '   '] + self::FORM, 'author_name'],
            'longest name' => [['author_name' => str_repeat('n', 255)] + self::FORM, null],
            'name one too long' => [['author_name' => str_repeat('n', 256)] + self::FORM, 'author_name'],
            'name not a string' => [['author_name' => ['Pat']] + self::FORM, 'author_name'],
            'no rating' => [['author_name' => 'Pat', 'text' => 'Fine'], 'rating'],
            'rating one' => [['rating' => 1] + self::FORM, null],
            'rating zero' => [['rating' => 0] + self::FORM, 'rating'],
            'rating six' => [['rating' => 6] + self::FORM, 'rating'],
            'rating not a number' => [['rating' => 'five'] + self::FORM, 'rating'],
            'rating a fraction' => [['rating' => '4.5'] + self::FORM, 'rating'],
            'no text' => [['author_name' => 'Pat', 'rating' => 5], 'text'],
            'longest text' => [['text' => str_repeat('t', 5000)] + self::FORM, null],
            'text one too long' => [['text' => str_repeat('t', 5001)] + self::FORM, 'text'],
            'Cyrillic' => [['author_name' => 'Вера', 'text' => 'Отличный магазин'] + self::FORM, null],
            'Chinese' => [['author_name' => '王芳', 'text' => '很好的商店'] + self::FORM, null],
            'Japanese' => [['author_name' => '佐藤', 'text' => '素晴らしい店'] + self::FORM, null],
            'Arabic, right to left' => [['author_name' => 'ليلى', 'text' => 'متجر رائع'] + self::FORM, null],
            'Hebrew, right to left' => [['author_name' => 'נועה', 'text' => 'חנות נהדרת'] + self::FORM, null],
            'emoji' => [['text' => 'Great 👍'] + self::FORM, null],
            'about too long' => [['about' => 'products:'.str_repeat('1', 300)] + self::FORM, 'about'],
            'about a list' => [['about' => ['products:1']] + self::FORM, 'about'],
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     */
    #[DataProvider('inputs')]
    public function test_the_form_is_validated(array $input, ?string $error): void
    {
        $response = $this->from('/reviews')->post('/reviews', $input)->assertStatus(302);

        if ($error === null) {
            $response->assertSessionHasNoErrors()->assertSessionHas('atrium-reviews.sent', true);
            $this->assertSame(1, Review::query()->count());
            $this->assertSame(trim((string) $input['author_name']), Review::query()->sole()->author_name);
        } else {
            $response->assertSessionHasErrors([$error], null, 'reviews')->assertSessionMissing('atrium-reviews.sent');
            $this->assertSame(0, Review::query()->count());
        }
    }

    public function test_a_missing_name_is_reported_with_the_form_label(): void
    {
        $this->from('/reviews')->post('/reviews', ['rating' => 5, 'text' => 'Fine']);

        $this->assertSame('The Your name field is required.', session('errors')->getBag('reviews')->first('author_name'));
    }

    public function test_hidden_and_captcha_fields_never_come_back_into_the_form(): void
    {
        $this->from('/reviews')->post('/reviews', [
            'author_name' => 'Pat',
            'rating' => 9,
            'text' => 'Fine',
            ReviewController::HONEYPOT => '',
            ...array_fill_keys(CaptchaManager::INPUTS, 'secret'),
        ])->assertSessionHasErrors(['rating'], null, 'reviews');

        $old = session()->getOldInput();
        $this->assertSame('Pat', $old['author_name']);

        foreach ([ReviewController::HONEYPOT, ...CaptchaManager::INPUTS] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $old);
        }
    }

    public function test_the_form_goes_back_to_the_reviews_of_the_page_it_was_sent_from(): void
    {
        // Without a previous page: the site's reviews.
        $this->post('/reviews', self::FORM)->assertStatus(302)->assertRedirect(url('/reviews').'#reviews');

        $this->from('/products/phone#comments')->post('/reviews', self::FORM)
            ->assertStatus(302)
            ->assertRedirect(url('/products/phone').'#reviews');
    }

    public function test_a_review_is_kept_trimmed_with_a_whole_rating_and_waits_for_moderation(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $request = Request::create('/reviews', 'POST', [
            'author_name' => "  Vera\n",
            'rating' => '4',
            'text' => "\t Lovely ",
            'about' => app(Reviews::class)->key($phone),
        ]);
        $this->app->instance('request', $request);

        $response = app()->call([app(ReviewController::class), 'store'], ['request' => $request]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue(session('atrium-reviews.sent'));
        $review = Review::query()->sole();
        $this->assertSame('Vera', $review->author_name);
        $this->assertSame('Lovely', $review->text);
        $this->assertSame(4, $review->rating);
        $this->assertSame(ReviewStatus::New, $review->status);
        $this->assertSame($phone->getMorphClass(), $review->reviewable_type);
        $this->assertSame($phone->id, $review->reviewable_id);
    }

    public function test_the_limit_is_kept_per_address(): void
    {
        config(['atrium-reviews.rate_limit' => ['attempts' => 1, 'minutes' => 10]]);

        $this->from('/reviews')->post('/reviews', self::FORM)->assertSessionHasNoErrors();
        $this->assertSame(1, RateLimiter::attempts('atrium-reviews:127.0.0.1'));

        $this->from('/reviews')->post('/reviews', self::FORM)->assertSessionHasErrors(['text'], null, 'reviews');

        // Another address is not held back by the first one.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->from('/reviews')->post('/reviews', self::FORM)->assertSessionHasNoErrors();
        $this->assertSame(1, RateLimiter::attempts('atrium-reviews:10.0.0.2'));
        RateLimiter::clear('atrium-reviews:10.0.0.2');

        $this->assertSame(2, Review::query()->count());
    }

    /**
     * @return array<string,array{0:array<string,mixed>|null,1:int,2:int}>
     */
    public static function limits(): array
    {
        return [
            'not set: three in ten minutes' => [null, 3, 600],
            'one in a minute' => [['attempts' => 1, 'minutes' => 1], 1, 60],
            'zero is still one' => [['attempts' => 0, 'minutes' => 0], 1, 60],
            'negative is still one' => [['attempts' => -4, 'minutes' => -4], 1, 60],
            'two in five minutes' => [['attempts' => 2, 'minutes' => 5], 2, 300],
            'only attempts given' => [['attempts' => 2], 2, 600],
            'only minutes given' => [['minutes' => 2], 3, 120],
        ];
    }

    /**
     * @param  array<string,mixed>|null  $limit
     */
    #[DataProvider('limits')]
    public function test_how_many_reviews_one_address_may_send_and_for_how_long(?array $limit, int $attempts, int $seconds): void
    {
        $this->freezeSecond();
        $reviews = config('atrium-reviews');
        unset($reviews['rate_limit']);
        config(['atrium-reviews' => $limit === null ? $reviews : $reviews + ['rate_limit' => $limit]]);

        foreach (range(1, $attempts) as $i) {
            $this->from('/reviews')->post('/reviews', ['text' => "Review {$i}"] + self::FORM)->assertSessionHasNoErrors();
        }

        $this->assertSame($seconds, RateLimiter::availableIn('atrium-reviews:127.0.0.1'));

        $this->from('/reviews')->post('/reviews', self::FORM)->assertSessionHasErrors(['text'], null, 'reviews');
        $this->assertSame($attempts, Review::query()->count());
    }

    public function test_the_refusal_says_in_how_many_minutes_to_try_again_rounded_up(): void
    {
        $this->freezeSecond();
        config(['atrium-reviews.rate_limit' => ['attempts' => 1, 'minutes' => 2]]);
        $this->from('/reviews')->post('/reviews', self::FORM)->assertSessionHasNoErrors();

        // 61 seconds are left: two minutes, not one.
        $this->travel(59)->seconds();

        $this->from('/reviews')->post('/reviews', self::FORM + ['website' => '']);

        $this->assertSame(
            'Too many reviews from your address. Try again in 2 min.',
            session('errors')->getBag('reviews')->first('text'),
        );
        $this->assertSame('Pat', session()->getOldInput('author_name'));
        $this->assertArrayNotHasKey('website', session()->getOldInput());
    }

    public function test_the_refusal_counts_whole_minutes_as_they_are(): void
    {
        $this->freezeSecond();
        config(['atrium-reviews.rate_limit' => ['attempts' => 1, 'minutes' => 2]]);
        $this->from('/reviews')->post('/reviews', self::FORM)->assertSessionHasNoErrors();

        // 120 seconds are left: two minutes exactly.
        $this->from('/reviews')->post('/reviews', self::FORM);

        $this->assertSame(
            'Too many reviews from your address. Try again in 2 min.',
            session('errors')->getBag('reviews')->first('text'),
        );
    }

    public function test_the_page_is_titled_by_the_site_then_by_the_app_and_then_by_nothing(): void
    {
        $this->get('/reviews')->assertViewHas('siteTitle', Site::query()->firstOrFail()->name);

        Site::query()->update(['name' => '']);
        app(Sites::class)->flush();
        $this->get('/reviews')->assertViewHas('siteTitle', 'Demo shop');

        config(['app.name' => null]);
        $this->get('/reviews')->assertViewHas('siteTitle', '');
    }

    public function test_a_refused_review_keeps_the_form_but_not_the_hidden_fields(): void
    {
        config(['atrium-reviews.rate_limit' => ['attempts' => 1, 'minutes' => 1]]);
        $this->from('/reviews')->post('/reviews', self::FORM);

        $this->from('/reviews')->post('/reviews', self::FORM + array_fill_keys(CaptchaManager::INPUTS, 'secret'))
            ->assertSessionHasErrors(['text'], null, 'reviews');

        $old = session()->getOldInput();
        $this->assertSame('Fine', $old['text']);

        foreach (CaptchaManager::INPUTS as $hidden) {
            $this->assertArrayNotHasKey($hidden, $old);
        }
    }

    public function test_the_page_shows_ten_reviews_when_the_site_does_not_say(): void
    {
        $reviews = config('atrium-reviews');
        unset($reviews['per_page']);
        config(['atrium-reviews' => $reviews]);

        foreach (range(1, 11) as $i) {
            (new Review(['author_name' => "Author {$i}", 'rating' => 5, 'text' => "Text {$i}", 'status' => ReviewStatus::Published, 'posted_at' => now()->subDays($i)]))->save();
        }

        $content = (string) $this->get('/reviews')->assertOk()->assertSee('page=2', false)->getContent();

        $this->assertStringContainsString('Text 10', $content);
        $this->assertStringNotContainsString('Text 11', $content);
        $this->assertCount(10, app(Reviews::class)->latest());
        $this->assertCount(3, app(Reviews::class)->latest(limit: 3));
    }

    public function test_the_page_links_every_language_but_only_on_its_first_page(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch']);

        $this->get('/reviews')->assertOk()
            ->assertSee('<link rel="alternate" hreflang="de" href="'.url('/de/reviews').'">', false)
            ->assertSee('href="'.url('/de/reviews').'"', false);

        $this->get('/reviews?page=2')->assertOk()
            ->assertDontSee('rel="alternate"', false)
            // The language switch stays.
            ->assertSee('href="'.url('/de/reviews').'"', false);
    }

    public function test_the_page_is_titled_with_the_site_or_the_application_name(): void
    {
        Site::query()->update(['name' => 'Main street']);
        app(Sites::class)->flush();
        $this->get('/reviews')->assertOk()->assertSee('Reviews of Main street', false)->assertDontSee('Reviews of Demo shop', false);

        Site::query()->update(['name' => '']);
        app(Sites::class)->flush();

        $this->get('/reviews')->assertOk()->assertSee('Reviews of Demo shop', false);
    }

    public function test_the_controller_actions_can_be_called_from_outside(): void
    {
        $view = app()->call([app(ReviewController::class), 'index'], ['request' => Request::create('/reviews')]);

        $this->assertSame('atrium-reviews::page', $view->name());
        $this->assertSame([], $view->getData()['items']->items());
    }
}

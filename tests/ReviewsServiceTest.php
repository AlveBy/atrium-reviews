<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews\Tests;

use Atrium\Reviews\Models\Review;
use Atrium\Reviews\ReviewCounters;
use Atrium\Reviews\Reviews;
use Atrium\Reviews\ReviewsServiceProvider;
use Atrium\Reviews\ReviewStatus;
use Atrium\Reviews\Tests\Fixtures\Article;
use Atrium\Reviews\Tests\Fixtures\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What can be reviewed, how a form names its record, the rating summary
 * and the schema.org markup.
 */
final class ReviewsServiceTest extends TestCase
{
    /**
     * @return array<string,array{0:mixed,1:array<string,string>}>
     */
    public static function modelLists(): array
    {
        return [
            'with types' => [[Product::class => 'Product', Article::class => 'Article'], [Product::class => 'Product', Article::class => 'Article']],
            'without a type: Thing' => [[Product::class, Article::class], [Product::class => 'Thing', Article::class => 'Thing']],
            'a type with spaces: Thing' => [[Product::class => 'Local Business'], [Product::class => 'Thing']],
            'a type with markup: Thing' => [[Product::class => '"><script>'], [Product::class => 'Thing']],
            'a type not a string: Thing' => [[Product::class => 5], [Product::class => 'Thing']],
            'one class, not a list' => [Product::class, [Product::class => 'Thing']],
            'not a model' => [[Reviews::class => 'Thing'], []],
            'no such class' => [['App\\Models\\Nothing' => 'Thing'], []],
            'nothing' => [[], []],
        ];
    }

    /**
     * @param  array<string,string>  $expected
     */
    #[DataProvider('modelLists')]
    public function test_the_models_that_take_reviews(mixed $config, array $expected): void
    {
        config(['atrium-reviews.models' => $config]);

        $this->assertSame($expected, app(Reviews::class)->models());
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public static function keys(): array
    {
        return [
            'the key of the record' => ['products:{id}', true],
            'no id' => ['products', false],
            'empty id' => ['products:', false],
            'an id with letters after it' => ['products:{id}abc', false],
            'an id with a colon after it' => ['products:{id}:1', false],
            'a negative id' => ['products:-{id}', false],
            'an unknown id' => ['products:999999', false],
            'an unknown module' => ['shop:{id}', false],
            'a class name' => [Product::class.':{id}', false],
            'empty' => ['', false],
            'Cyrillic' => ['товары:{id}', false],
        ];
    }

    #[DataProvider('keys')]
    public function test_the_record_a_form_is_about(string $key, bool $found): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);

        $record = app(Reviews::class)->resolve(str_replace('{id}', (string) $phone->id, $key));

        $found ? $this->assertTrue($phone->is($record)) : $this->assertNull($record);
    }

    public function test_the_summary_counts_every_rating(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);

        foreach ([5, 3, 2, 1] as $rating) {
            $this->review(['rating' => $rating], $phone);
        }

        $this->assertSame(
            ['count' => 4, 'average' => 2.8, 'ratings' => [5 => 1, 4 => 0, 3 => 1, 2 => 1, 1 => 1]],
            app(Reviews::class)->summary($phone),
        );
        $this->assertSame(
            [app(Reviews::class)->key($phone) => ['count' => 4, 'average' => 2.8, 'ratings' => [5 => 1, 4 => 0, 3 => 1, 2 => 1, 1 => 1]]],
            app(Reviews::class)->summaries([$phone]),
        );
    }

    public function test_the_average_is_rounded_to_one_decimal(): void
    {
        foreach ([5, 3, 2] as $rating) {
            $this->review(['rating' => $rating]);
        }

        $this->assertSame(3.3, app(Reviews::class)->summary()['average']);
    }

    public function test_a_record_without_reviews_has_an_empty_summary(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $empty = ['count' => 0, 'average' => null, 'ratings' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];

        $this->assertSame($empty, app(Reviews::class)->summary($phone));
        $this->assertSame([app(Reviews::class)->key($phone) => $empty], app(Reviews::class)->summaries([$phone]));
    }

    public function test_no_records_need_no_query(): void
    {
        DB::enableQueryLog();
        $summaries = app(Reviews::class)->summaries([]);

        $this->assertSame([], $summaries);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_the_number_of_latest_reviews_may_come_from_the_config_as_text(): void
    {
        foreach (range(1, 4) as $i) {
            $this->review(['text' => "Text {$i}"]);
        }

        config(['atrium-reviews.per_page' => '3']);

        $this->assertCount(3, app(Reviews::class)->latest());
    }

    public function test_the_markup_of_a_record_with_its_reviews(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $anna = $this->review(['author_name' => 'Anna', 'rating' => 4, 'text' => 'Good', 'posted_at' => Carbon::parse('2026-02-03 10:00')], $phone);
        $boris = $this->review(['author_name' => 'Boris', 'rating' => 2, 'text' => 'Slow'], $phone);
        // A date taken away later is left out, not written as null.
        $boris->forceFill(['posted_at' => null])->save();

        $this->assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Phone',
            'url' => 'https://shop.test/phone',
            'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => 3.0, 'reviewCount' => 2, 'bestRating' => 5, 'worstRating' => 1],
            'review' => [
                [
                    '@type' => 'Review',
                    'author' => ['@type' => 'Person', 'name' => 'Anna'],
                    'datePublished' => '2026-02-03',
                    'reviewBody' => 'Good',
                    'reviewRating' => ['@type' => 'Rating', 'ratingValue' => 4, 'bestRating' => 5, 'worstRating' => 1],
                ],
                [
                    '@type' => 'Review',
                    'author' => ['@type' => 'Person', 'name' => 'Boris'],
                    'reviewBody' => 'Slow',
                    'reviewRating' => ['@type' => 'Rating', 'ratingValue' => 2, 'bestRating' => 5, 'worstRating' => 1],
                ],
            ],
        ], app(Reviews::class)->jsonLd($phone, 'Phone', 'https://shop.test/phone', [$anna, $boris->fresh()]));
    }

    public function test_the_markup_without_an_address_or_shown_reviews(): void
    {
        $this->review(['rating' => 4]);

        $data = app(Reviews::class)->jsonLd(null, 'Demo shop', null);

        $this->assertNotNull($data);
        $this->assertSame(['@context', '@type', 'name', 'aggregateRating'], array_keys($data));
        $this->assertNull(app(Reviews::class)->jsonLd(Product::query()->create(['name' => 'Phone', 'code' => 'phone']), 'Phone', null));
    }

    /**
     * @return array<string,array{0:mixed,1:string}>
     */
    public static function siteTypes(): array
    {
        return [
            'a schema.org type' => ['LocalBusiness', 'LocalBusiness'],
            'with spaces' => ['Local Business', 'Organization'],
            'with markup' => ['"><script>', 'Organization'],
            'empty' => ['', 'Organization'],
            'not a string' => [5, 'Organization'],
            'Cyrillic' => ['Организация', 'Organization'],
        ];
    }

    #[DataProvider('siteTypes')]
    public function test_the_site_is_marked_up_with_its_configured_type(mixed $type, string $expected): void
    {
        config(['atrium-reviews.site_type' => $type]);
        $this->review();

        $this->assertSame($expected, app(Reviews::class)->jsonLd(null, 'Demo shop', null)['@type'] ?? null);
    }

    public function test_the_defaults_and_services_of_the_module(): void
    {
        $this->assertSame(['attempts' => 3, 'minutes' => 10], config('atrium-reviews.rate_limit'));
        $this->assertSame('Organization', config('atrium-reviews.site_type'));
        $this->assertSame(app(Reviews::class), app(Reviews::class));
        $this->assertSame(app(ReviewCounters::class), app(ReviewCounters::class));
        $this->assertSame(
            realpath(__DIR__.'/../resources/views/components'),
            realpath((string) array_search('atrium-reviews', array_column(Blade::getAnonymousComponentPaths(), 'prefix', 'path'), true)),
        );
    }

    public function test_the_config_and_views_can_be_published(): void
    {
        $config = ServiceProvider::pathsToPublish(ReviewsServiceProvider::class, 'atrium-reviews-config');
        $views = ServiceProvider::pathsToPublish(ReviewsServiceProvider::class, 'atrium-reviews-views');

        $this->assertCount(1, $config);
        $this->assertSame(realpath(__DIR__.'/../config/atrium-reviews.php'), realpath((string) array_key_first($config)));
        $this->assertSame(config_path('atrium-reviews.php'), $config[array_key_first($config)]);

        $this->assertCount(1, $views);
        $this->assertSame(realpath(__DIR__.'/../resources/views'), realpath((string) array_key_first($views)));
        $this->assertSame(resource_path('views/vendor/atrium-reviews'), $views[array_key_first($views)]);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function review(array $attributes = [], ?Product $about = null): Review
    {
        $review = new Review(['author_name' => 'Someone', 'rating' => 5, 'text' => 'Text', 'status' => ReviewStatus::Published, ...$attributes]);

        if ($about !== null) {
            $review->reviewable_type = $about->getMorphClass();
            $review->reviewable_id = $about->id;
        }

        $review->save();

        return $review;
    }
}

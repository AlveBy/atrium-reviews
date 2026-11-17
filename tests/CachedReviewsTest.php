<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews\Tests;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Reviews\Models\Review;
use Atrium\Reviews\Reviews;
use Atrium\Reviews\ReviewStatus;
use Atrium\Reviews\Tests\Fixtures\Product;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Event;

/**
 * The summary and the latest reviews come from the record cache, under
 * keys of their own, and are fresh once a review is saved.
 */
final class CachedReviewsTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function keysWrittenBy(callable $callback): array
    {
        $keys = [];
        Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$keys): void {
            $keys[] = $event->key;
        });

        $callback();

        return $keys;
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

    public function test_the_summary_of_a_record_is_kept_under_its_type_and_id(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $this->review(['rating' => 4], $phone);
        app(RecordCache::class)->reset();

        $keys = $this->keysWrittenBy(fn () => app(Reviews::class)->summary($phone));

        $expected = ':summary:'.$phone->getMorphClass().':'.$phone->id;
        $this->assertCount(1, array_filter($keys, static fn (string $key): bool => str_ends_with($key, $expected)), implode("\n", $keys));
    }

    public function test_the_summary_of_the_whole_site_is_kept_under_an_empty_type_and_id_zero(): void
    {
        $this->review(['rating' => 4]);
        app(RecordCache::class)->reset();

        $keys = $this->keysWrittenBy(fn () => app(Reviews::class)->summary());

        $this->assertCount(1, array_filter($keys, static fn (string $key): bool => str_ends_with($key, ':summary::0')), implode("\n", $keys));
    }

    public function test_a_warm_summary_is_read_from_the_cache_and_a_saved_review_drops_it(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $this->review(['rating' => 4], $phone);
        $reviews = app(Reviews::class);
        $cold = $reviews->summary($phone);
        $this->assertSame(1, $cold['count']);

        $cache = app(RecordCache::class);
        $cache->reset();
        $this->assertSame($cold, $reviews->summary($phone));
        $this->assertSame(['hits' => 1, 'misses' => 0], $cache->requestStats());

        $this->review(['rating' => 2], $phone);
        $cache->reset();

        $summary = $reviews->summary($phone);
        $this->assertSame(['hits' => 0, 'misses' => 1], $cache->requestStats());
        $this->assertSame(['count' => 2, 'average' => 3.0], array_slice($summary, 0, 2));
    }

    public function test_the_latest_reviews_are_models_newest_first(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $this->review(['text' => 'Old one', 'posted_at' => now()->subDays(2)], $phone);
        $this->review(['text' => 'New one', 'posted_at' => now()->subDay()], $phone);
        $this->review(['text' => 'Hidden', 'status' => ReviewStatus::Rejected, 'posted_at' => now()], $phone);

        $latest = app(Reviews::class)->latest($phone);

        $this->assertInstanceOf(Review::class, $latest->first());
        $this->assertSame(['New one', 'Old one'], $latest->pluck('text')->all());
    }

    public function test_a_saved_review_shows_in_the_latest_ones(): void
    {
        $this->review(['text' => 'First']);
        $this->assertSame(['First'], app(Reviews::class)->latest()->pluck('text')->all());

        $this->review(['text' => 'Second', 'posted_at' => now()->addMinute()]);

        $this->assertSame(['Second', 'First'], app(Reviews::class)->latest()->pluck('text')->all());
    }
}

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews\Tests;

use Atrium\Admin\Models\Site;
use Atrium\Reviews\Models\Review;
use Atrium\Reviews\ReviewCounters;
use Atrium\Reviews\Reviews;
use Atrium\Reviews\ReviewStatus;
use Atrium\Reviews\Tests\Fixtures\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CountersTest extends TestCase
{
    public function test_the_counter_follows_every_change_of_a_review(): void
    {
        $phone = $this->product('phone');
        $tablet = $this->product('tablet');
        $reviews = app(Reviews::class);

        $review = $this->review(4, $phone, ReviewStatus::New);
        $this->assertSame(0, $reviews->summary($phone)['count'], 'a new review waits for moderation');

        $review->update(['status' => ReviewStatus::Published]);
        $this->review(2, $phone);
        $this->assertSame(['count' => 2, 'average' => 3.0, 'ratings' => [5 => 0, 4 => 1, 3 => 0, 2 => 1, 1 => 0]], $reviews->summary($phone));

        $review->update(['rating' => 5]);
        $this->assertSame(3.5, $reviews->summary($phone)['average']);

        // Moved to another record: both are counted again.
        $review->update(['reviewable_id' => $tablet->id]);
        $this->assertSame(['count' => 1, 'average' => 2.0], array_slice($reviews->summary($phone), 0, 2));
        $this->assertSame(['count' => 1, 'average' => 5.0], array_slice($reviews->summary($tablet), 0, 2));

        $review->update(['status' => ReviewStatus::Rejected]);
        $this->assertSame(0, $reviews->summary($tablet)['count']);

        Review::query()->each(static fn (Review $review) => $review->delete());
        $this->assertSame(0, $reviews->summary($phone)['count']);
        $this->assertNull($reviews->summary($phone)['average']);
    }

    public function test_a_page_reads_the_counter_and_does_not_count_reviews(): void
    {
        $phone = $this->product('phone');
        $this->review(5, $phone);
        $this->review(4, $phone);

        DB::enableQueryLog();
        $summary = app(Reviews::class)->summary($phone);
        $queries = array_column(DB::getQueryLog(), 'query');

        $this->assertSame(2, $summary['count']);
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('review_counters', $queries[0]);
        $this->assertDoesNotMatchRegularExpression('/\breviews\b/', $queries[0]);
    }

    public function test_the_text_of_a_review_does_not_touch_the_counter(): void
    {
        $review = $this->review(5, $this->product('phone'));

        DB::enableQueryLog();
        $review->update(['text' => 'Edited', 'author_name' => 'Ivan P.']);

        $this->assertSame([], array_filter(array_column(DB::getQueryLog(), 'query'), static fn (string $query): bool => str_contains($query, 'review_counters')));
    }

    public function test_the_summaries_of_a_list_come_in_one_query(): void
    {
        [$phone, $tablet, $watch] = [$this->product('phone'), $this->product('tablet'), $this->product('watch')];
        $this->review(5, $phone);
        $this->review(3, $phone);
        $this->review(4, $tablet);
        $this->review(1);
        $reviews = app(Reviews::class);

        DB::enableQueryLog();
        $summaries = $reviews->summaries([$phone, $tablet, $watch]);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame([$reviews->key($phone), $reviews->key($tablet), $reviews->key($watch)], array_keys($summaries));
        $this->assertSame([2, 4.0], [$summaries[$reviews->key($phone)]['count'], $summaries[$reviews->key($phone)]['average']]);
        $this->assertSame([1, 4.0], [$summaries[$reviews->key($tablet)]['count'], $summaries[$reviews->key($tablet)]['average']]);
        $this->assertSame([0, null], [$summaries[$reviews->key($watch)]['count'], $summaries[$reviews->key($watch)]['average']]);
        $this->assertSame([], $reviews->summaries([]));
    }

    public function test_each_site_has_its_own_counter_of_the_site_reviews(): void
    {
        $this->review(5);
        $other = Site::query()->create(['code' => 'second', 'name' => 'Second', 'folder' => 'site2']);
        (new Review(['author_name' => 'Ann', 'rating' => 1, 'text' => 'Bad', 'status' => ReviewStatus::Published]))->forceFill(['site_id' => $other->id])->save();

        $this->get('/reviews')->assertSee('"ratingValue":5', false)->assertSee('"reviewCount":1', false);
        $this->get('/site2/reviews')->assertSee('"ratingValue":1', false)->assertSee('"reviewCount":1', false);
        $this->assertSame(2, DB::table(ReviewCounters::TABLE)->where('reviewable_type', '')->count());
    }

    public function test_the_command_counts_everything_again(): void
    {
        $phone = $this->product('phone');
        $this->review(5, $phone);
        $this->review(5);
        // Changed in the database directly: no events, the counter is off.
        DB::table('reviews')->update(['rating' => 1]);
        DB::table('reviews')->insert(['author_name' => 'Imported', 'rating' => 3, 'text' => 'Old', 'status' => 'published', 'reviewable_type' => $phone->getMorphClass(), 'reviewable_id' => $phone->id, 'site_id' => Review::query()->value('site_id')]);
        $this->assertSame(5.0, app(Reviews::class)->summary($phone)['average']);

        $this->artisan('atrium:reviews:recount')->expectsOutputToContain('Counted the reviews of 2 records.')->assertSuccessful();

        $this->assertSame(['count' => 2, 'average' => 2.0], array_slice(app(Reviews::class)->summary($phone), 0, 2));
        $this->assertSame(1.0, app(Reviews::class)->summary()['average']);
    }

    public function test_the_counter_keeps_every_rating_apart(): void
    {
        $phone = $this->product('phone');

        foreach ([5, 5, 4, 3, 2, 1] as $rating) {
            $this->review($rating, $phone);
        }

        $row = DB::table(ReviewCounters::TABLE)->where('reviewable_type', $phone->getMorphClass())->sole();
        $this->assertSame([6, 20, 2, 1, 1, 1, 1], array_map('intval', [$row->count, $row->rating_sum, $row->rating_5, $row->rating_4, $row->rating_3, $row->rating_2, $row->rating_1]));

        $this->artisan('atrium:reviews:recount')->assertSuccessful();

        $row = DB::table(ReviewCounters::TABLE)->where('reviewable_type', $phone->getMorphClass())->sole();
        $this->assertSame([6, 20, 2, 1, 1, 1, 1], array_map('intval', [$row->count, $row->rating_sum, $row->rating_5, $row->rating_4, $row->rating_3, $row->rating_2, $row->rating_1]));
        $this->assertNotNull($row->created_at);
        $this->assertNotNull($row->updated_at);
    }

    public function test_a_counter_is_dated_when_made_and_when_counted_again(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 10:00:00'));
        $phone = $this->product('phone');
        $this->review(5, $phone);

        $this->travelTo(Carbon::parse('2026-01-02 10:00:00'));
        $this->review(3, $phone);

        $row = DB::table(ReviewCounters::TABLE)->where('reviewable_type', $phone->getMorphClass())->sole();
        $this->assertSame('2026-01-01 10:00:00', Carbon::parse($row->created_at)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-02 10:00:00', Carbon::parse($row->updated_at)->format('Y-m-d H:i:s'));
    }

    public function test_reviews_of_no_site_are_counted_on_their_own(): void
    {
        $this->review(4);
        DB::table('reviews')->insert(['author_name' => 'Imported', 'rating' => 2, 'text' => 'Old', 'status' => 'published', 'site_id' => null]);

        app(ReviewCounters::class)->recount(['site_id' => 0, 'reviewable_type' => '', 'reviewable_id' => 0]);

        $row = DB::table(ReviewCounters::TABLE)->where('site_id', 0)->where('reviewable_type', '')->sole();
        $this->assertSame([1, 2, 1], array_map('intval', [$row->count, $row->rating_sum, $row->rating_2]));
        $this->assertNotNull($row->created_at);
    }

    private function product(string $code): Product
    {
        return Product::query()->create(['name' => ucfirst($code), 'code' => $code]);
    }

    private function review(int $rating, ?Product $about = null, ReviewStatus $status = ReviewStatus::Published): Review
    {
        $review = new Review(['author_name' => 'Someone', 'rating' => $rating, 'text' => 'Text', 'status' => $status]);

        if ($about !== null) {
            $review->reviewable_type = $about->getMorphClass();
            $review->reviewable_id = $about->id;
        }

        $review->save();

        return $review;
    }
}

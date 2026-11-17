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
use Atrium\Reviews\ReviewStatus;
use Atrium\Reviews\Tests\Fixtures\Product;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;

/**
 * How a counter is kept: which statements are made, in which order and how
 * often, and how the key and the numbers of a row are read.
 */
final class CounterDetailsTest extends TestCase
{
    /**
     * @return array<int,array{query:string,bindings:array<int,mixed>}>
     */
    private function queriesOf(callable $run): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return array_values(array_filter($log, static fn (array $entry): bool => str_contains($entry['query'], ReviewCounters::TABLE)));
    }

    /**
     * @param  array<int,array{query:string,bindings:array<int,mixed>}>  $queries
     */
    private function verbs(array $queries, string $verb): int
    {
        return count(array_filter($queries, static fn (array $entry): bool => str_starts_with($entry['query'], $verb)));
    }

    private function published(?Product $about = null, int $rating = 5): Review
    {
        $review = new Review(['author_name' => 'Someone', 'rating' => $rating, 'text' => 'Text', 'status' => ReviewStatus::Published]);

        if ($about !== null) {
            $review->reviewable_type = $about->getMorphClass();
            $review->reviewable_id = $about->id;
        }

        $review->save();

        return $review;
    }

    public function test_a_review_that_stays_with_its_record_counts_it_once(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $tablet = Product::query()->create(['name' => 'Tablet', 'code' => 'tablet']);
        $review = $this->published($phone);

        $stays = $this->queriesOf(fn () => $review->update(['rating' => 3]));
        $this->assertSame(1, $this->verbs($stays, 'update'));

        $moves = $this->queriesOf(fn () => $review->update(['reviewable_id' => $tablet->id]));
        $this->assertSame(2, $this->verbs($moves, 'update'));
    }

    public function test_a_new_counter_is_locked_after_it_is_made_and_an_old_one_at_once(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);

        $first = $this->queriesOf(fn () => $this->published($phone));
        $this->assertSame([2, 1, 1], [$this->verbs($first, 'select'), $this->verbs($first, 'insert'), $this->verbs($first, 'update')]);

        $second = $this->queriesOf(fn () => $this->published($phone));
        $this->assertSame([1, 0, 1], [$this->verbs($second, 'select'), $this->verbs($second, 'insert'), $this->verbs($second, 'update')]);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:array<int,mixed>}>
     */
    public static function keys(): array
    {
        return [
            'nothing: the site, of no record' => [[], [0, '', 0]],
            'a type without an id' => [['reviewable_type' => 'x'], [0, 'x', 0]],
            'a record with its site' => [['site_id' => 3, 'reviewable_type' => 'x', 'reviewable_id' => 7], [3, 'x', 7]],
            'numbers that came as text' => [['site_id' => '3', 'reviewable_type' => 'x', 'reviewable_id' => '7'], [3, 'x', 7]],
            'an id without a type is nobody' => [['reviewable_id' => 7], [0, '', 0]],
            'a site that is null' => [['site_id' => null, 'reviewable_type' => null, 'reviewable_id' => null], [0, '', 0]],
        ];
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @param  array<int,mixed>  $key
     */
    #[DataProvider('keys')]
    public function test_the_counter_of_a_review_is_looked_up_by_a_key_of_plain_numbers_and_text(array $attributes, array $key): void
    {
        $review = (new Review)->forceFill(['status' => ReviewStatus::Published->value, 'rating' => 4] + $attributes);

        $queries = $this->queriesOf(fn () => app(ReviewCounters::class)->changed($review));

        $this->assertSame($key, $queries[0]['bindings']);
        $this->assertSame(1, DB::table(ReviewCounters::TABLE)->where('site_id', $key[0])->where('reviewable_type', $key[1])->where('reviewable_id', $key[2])->count());
    }

    public function test_a_site_given_as_zero_text_counts_the_reviews_of_no_site(): void
    {
        DB::table('reviews')->insert(['author_name' => 'Imported', 'rating' => 2, 'text' => 'Old', 'status' => 'published', 'site_id' => null]);

        app(ReviewCounters::class)->changed((new Review)->forceFill(['status' => 'published', 'rating' => 2, 'site_id' => '0']));

        $row = DB::table(ReviewCounters::TABLE)->where('site_id', 0)->where('reviewable_type', '')->sole();
        $this->assertSame([1, 2], [(int) $row->count, (int) $row->rating_sum]);
    }

    public function test_the_counters_are_made_in_batches_of_five_hundred(): void
    {
        foreach (array_chunk(range(1, 1001), 100) as $ids) {
            DB::table('reviews')->insert(array_map(static fn (int $id): array => [
                'author_name' => 'Imported', 'rating' => 5, 'text' => 'Old', 'status' => 'published', 'site_id' => null, 'reviewable_type' => 'x', 'reviewable_id' => $id,
            ], $ids));
        }

        $queries = $this->queriesOf(fn () => $this->assertSame(1001, app(ReviewCounters::class)->rebuild()));
        $inserts = array_values(array_filter($queries, static fn (array $entry): bool => str_starts_with($entry['query'], 'insert')));

        // 12 values a row: 3 of the key, 7 sums, 2 dates.
        $this->assertSame([500, 500, 1], array_map(static fn (array $entry): int => intdiv(count($entry['bindings']), 12), $inserts));
    }

    public function test_the_sums_are_asked_for_five_ratings_and_no_more(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->published();
        $sums = array_values(array_filter(DB::getQueryLog(), static fn (array $entry): bool => str_contains($entry['query'], 'rating_sum') && str_starts_with($entry['query'], 'select')));
        DB::disableQueryLog();

        $this->assertCount(1, $sums);

        foreach ([1, 2, 3, 4, 5] as $rating) {
            $this->assertStringContainsString("rating_{$rating}", $sums[0]['query']);
        }

        $this->assertStringNotContainsString('rating_0', $sums[0]['query']);
        $this->assertStringNotContainsString('rating_6', $sums[0]['query']);
    }

    public function test_the_numbers_of_a_counted_row_are_numbers_whatever_the_database_returns(): void
    {
        $values = new ReflectionMethod(ReviewCounters::class, 'values');
        $row = (object) ['count' => '6', 'rating_sum' => '20', 'rating_1' => '1', 'rating_2' => '1', 'rating_3' => '1', 'rating_4' => '1', 'rating_5' => '2'];

        $this->assertSame(
            ['count' => 6, 'rating_sum' => 20, 'rating_1' => 1, 'rating_2' => 1, 'rating_3' => 1, 'rating_4' => 1, 'rating_5' => 2],
            $values->invoke(app(ReviewCounters::class), $row),
        );
        $this->assertSame(
            ['count' => 0, 'rating_sum' => 0, 'rating_1' => 0, 'rating_2' => 0, 'rating_3' => 0, 'rating_4' => 0, 'rating_5' => 0],
            $values->invoke(app(ReviewCounters::class), null),
        );
        $this->assertSame(
            ['count' => 0, 'rating_sum' => 0, 'rating_1' => 0, 'rating_2' => 0, 'rating_3' => 0, 'rating_4' => 0, 'rating_5' => 0],
            $values->invoke(app(ReviewCounters::class), (object) []),
        );
    }

    public function test_a_summary_is_made_of_numbers_whatever_the_database_returns(): void
    {
        $summary = new ReflectionMethod(Reviews::class, 'toSummary');
        $row = (object) ['count' => '4', 'rating_sum' => '11', 'rating_5' => '1', 'rating_4' => '1', 'rating_3' => '1', 'rating_2' => '1', 'rating_1' => '0'];

        $this->assertSame(
            ['count' => 4, 'average' => 2.8, 'ratings' => [5 => 1, 4 => 1, 3 => 1, 2 => 1, 1 => 0]],
            $summary->invoke(app(Reviews::class), $row),
        );
        $this->assertSame(
            ['count' => 0, 'average' => null, 'ratings' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]],
            $summary->invoke(app(Reviews::class), (object) []),
        );
    }

    public function test_the_summaries_ask_for_the_asked_records_only(): void
    {
        [$phone, $tablet, $watch] = [
            Product::query()->create(['name' => 'Phone', 'code' => 'phone']),
            Product::query()->create(['name' => 'Tablet', 'code' => 'tablet']),
            Product::query()->create(['name' => 'Watch', 'code' => 'watch']),
        ];
        $this->published($phone);
        $this->published($watch);

        $queries = $this->queriesOf(fn () => app(Reviews::class)->summaries([$phone, $tablet]));

        $this->assertCount(1, $queries);
        $this->assertSame([$phone->getMorphClass(), $phone->id, $tablet->id], $queries[0]['bindings']);
    }

    /**
     * Runs a recount that deadlocks $times times. The test's own transaction
     * is ended first, so the deadlock is retried as it is in production;
     * what was made is wiped by hand.
     *
     * @return array{0:int,1:?RuntimeException}
     */
    private function deadlocked(int $times): array
    {
        $attempts = 0;
        $failure = null;

        DB::rollBack();
        DB::listen(function ($query) use (&$attempts, $times): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, ReviewCounters::TABLE) && $attempts < $times) {
                $attempts++;

                throw new RuntimeException('Deadlock found when trying to get lock; try restarting transaction');
            }
        });

        try {
            app(ReviewCounters::class)->recount(['site_id' => 0, 'reviewable_type' => '', 'reviewable_id' => 0]);
        } catch (RuntimeException $exception) {
            $failure = $exception;
        } finally {
            DB::table(ReviewCounters::TABLE)->delete();
        }

        return [$attempts, $failure];
    }

    public function test_a_counter_made_by_two_requests_at_once_is_tried_three_times(): void
    {
        [$attempts, $failure] = $this->deadlocked(2);

        $this->assertSame(2, $attempts);
        $this->assertNull($failure);
    }

    public function test_a_counter_that_deadlocks_every_time_is_given_up_after_three_tries(): void
    {
        [$attempts, $failure] = $this->deadlocked(99);

        $this->assertSame(3, $attempts);
        $this->assertInstanceOf(RuntimeException::class, $failure);
    }
}

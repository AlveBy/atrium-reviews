<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews;

use Atrium\Reviews\Models\Review;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * The number of published reviews of each record, their rating sum and how
 * many of each rating, kept in `review_counters` so a page does not count
 * reviews on every view.
 *
 * A counter is never moved by one: every change of a review counts its
 * record again from `reviews`, after the change is committed, with the
 * counter row locked. Two reviews of one record sent at once are then both
 * counted, and a review that is unpublished, moved or re-rated cannot leave
 * the counter off.
 */
final class ReviewCounters
{
    public const TABLE = 'review_counters';

    /** Changes that move a counter; the text and the name do not. */
    public const COUNTED = ['status', 'rating', 'site_id', 'reviewable_type', 'reviewable_id'];

    /** Counts the record of a review that was just created, changed or deleted. */
    public function changed(Review $review): void
    {
        $attributes = $review->getAttributes();
        // A review moved to another record or site leaves the old one too.
        $keys = array_unique([
            serialize($this->key($attributes)),
            serialize($this->key(array_merge($attributes, $review->getPrevious()))),
        ]);

        // After the commit: only then are this review and the ones other
        // requests have just saved visible to the count.
        $this->connection()->afterCommit(function () use ($keys): void {
            foreach ($keys as $key) {
                $this->recount(unserialize($key));
            }
        });
    }

    /**
     * Counts one record (or the site's own reviews) again.
     *
     * @param  array{site_id:int,reviewable_type:string,reviewable_id:int}  $key
     */
    public function recount(array $key): void
    {
        $db = $this->connection();

        // Retried: on MySQL two first reviews of one record may deadlock
        // while both create its counter row.
        $db->transaction(function () use ($db, $key): void {
            // The lock waits for a recount of the same record running in
            // another request, so the count below includes its review.
            if ($db->table(self::TABLE)->where($key)->lockForUpdate()->first() === null) {
                $db->table(self::TABLE)->insertOrIgnore($key + ['created_at' => now(), 'updated_at' => now()]);
                $db->table(self::TABLE)->where($key)->lockForUpdate()->first();
            }

            $row = $this->published()
                ->where(static fn (Builder $query) => $key['site_id'] === 0 ? $query->whereNull('site_id') : $query->where('site_id', $key['site_id']))
                ->where(static fn (Builder $query) => $key['reviewable_type'] === ''
                    ? $query->whereNull('reviewable_type')
                    : $query->where('reviewable_type', $key['reviewable_type'])->where('reviewable_id', $key['reviewable_id']))
                ->selectRaw($this->sums())
                ->first();

            $db->table(self::TABLE)->where($key)->update($this->values($row) + ['updated_at' => now()]);
        }, 3);
    }

    /**
     * Counts every record again, for reviews imported or changed in the
     * database directly. Returns the number of counters.
     */
    public function rebuild(): int
    {
        $db = $this->connection();
        $site = 'coalesce(site_id, 0)';
        $type = "coalesce(reviewable_type, '')";
        $id = 'coalesce(reviewable_id, 0)';

        $rows = $this->published()
            ->selectRaw("$site as site_id, $type as reviewable_type, $id as reviewable_id, ".$this->sums())
            ->groupByRaw("$site, $type, $id")
            ->get();

        $db->transaction(function () use ($db, $rows): void {
            $db->table(self::TABLE)->delete();

            foreach ($rows->chunk(500) as $chunk) {
                $db->table(self::TABLE)->insert($chunk->map(fn (object $row): array => $this->key((array) $row) + $this->values($row) + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->values()->all());
            }
        });

        return $rows->count();
    }

    /**
     * The counter key of a review's raw attributes: no NULLs, so it fits the
     * unique index.
     *
     * @param  array<string,mixed>  $attributes
     * @return array{site_id:int,reviewable_type:string,reviewable_id:int}
     */
    private function key(array $attributes): array
    {
        $type = (string) ($attributes['reviewable_type'] ?? '');

        return [
            'site_id' => (int) ($attributes['site_id'] ?? 0),
            'reviewable_type' => $type,
            'reviewable_id' => $type === '' ? 0 : (int) ($attributes['reviewable_id'] ?? 0),
        ];
    }

    private function published(): Builder
    {
        return Review::query()->withoutGlobalScopes()->toBase()->where('status', ReviewStatus::Published->value);
    }

    private function sums(): string
    {
        $sums = 'count(*) as count, coalesce(sum(rating), 0) as rating_sum';

        foreach (range(1, 5) as $rating) {
            $sums .= ", coalesce(sum(case when rating = $rating then 1 else 0 end), 0) as rating_$rating";
        }

        return $sums;
    }

    /**
     * @return array<string,int>
     */
    private function values(?object $row): array
    {
        $values = ['count' => (int) ($row->count ?? 0), 'rating_sum' => (int) ($row->rating_sum ?? 0)];

        foreach (range(1, 5) as $rating) {
            $values['rating_'.$rating] = (int) ($row->{'rating_'.$rating} ?? 0);
        }

        return $values;
    }

    private function connection(): Connection
    {
        return (new Review)->getConnection();
    }
}

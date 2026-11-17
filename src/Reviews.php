<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Modules\Soft;
use Atrium\Admin\Support\Sites;
use Atrium\Reviews\Models\Review;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

/**
 * What can be reviewed, the rating of a record and its schema.org markup.
 *
 * A review keeps the reviewed record as a type and an id (a soft morphTo,
 * Atrium\Admin\Traits\HasSoftRelations), with no foreign key and no
 * Composer dependency on its module: a module that is removed leaves its
 * reviews in place, they just lose their record.
 */
final class Reviews
{
    /** A schema.org type name: letters only, it goes into the page as is. */
    private const SCHEMA_TYPE = '/^[A-Za-z]+$/';

    /**
     * The models that take reviews and their schema.org type; models whose
     * module is not installed are left out.
     *
     * @return array<class-string<Model>,string>
     */
    public function models(): array
    {
        $models = [];

        foreach ((array) config('atrium-reviews.models', []) as $class => $type) {
            if (is_int($class)) {
                [$class, $type] = [$type, 'Thing'];
            }

            if (is_string($class) && Soft::available($class) && is_subclass_of($class, Model::class)) {
                $models[$class] = is_string($type) && preg_match(self::SCHEMA_TYPE, $type) ? $type : 'Thing';
            }
        }

        return $models;
    }

    public function accepts(Model $record): bool
    {
        return isset($this->models()[$record::class]);
    }

    /**
     * What the public form sends to say which record it is about: the
     * module's key and the id, never a class name.
     */
    public function key(Model $record): string
    {
        return $this->alias($record::class).':'.$record->getKey();
    }

    /**
     * The record a form is about, only among the models that take reviews
     * and only if the visitor can see it (the current site's).
     */
    public function resolve(string $key): ?Model
    {
        [$alias, $id] = explode(':', $key, 2) + [1 => ''];

        if (! ctype_digit($id)) {
            return null;
        }

        foreach (array_keys($this->models()) as $class) {
            if ($this->alias($class) === $alias) {
                return $class::query()->find((int) $id);
            }
        }

        return null;
    }

    /**
     * "Products: Phone X" for the admin list; a missing record keeps its
     * type and id, so an editor sees what the review was about.
     */
    public function describe(Review $review): string
    {
        if ($review->reviewable_type === null) {
            return __('atrium-reviews::reviews.site');
        }

        $record = $review->reviewable;

        if ($record === null) {
            return __('atrium-reviews::reviews.missing', ['type' => class_basename($review->reviewable_type), 'id' => $review->reviewable_id]);
        }

        $title = $record instanceof Administrable && method_exists($record, 'getAdminTitle')
            ? $record->getAdminTitle()
            : '#'.$record->getKey();

        return $this->moduleLabel($record::class).': '.$title;
    }

    /**
     * The average rating, the number of published reviews and how many of
     * each rating (5 first), from the counters: no reviews are counted here.
     *
     * @return array{count:int,average:float|null,ratings:array<int,int>}
     */
    public function summary(?Model $record = null): array
    {
        $type = $record === null ? '' : $record->getMorphClass();
        $id = $record === null ? 0 : $record->getKey();

        return $this->toSummary($this->counters()->where('reviewable_type', $type)->where('reviewable_id', $id)->first());
    }

    /**
     * The summaries of many records in one query, for a list of products
     * with their stars; keyed by key($record).
     *
     * @param  iterable<Model>  $records
     * @return array<string,array{count:int,average:float|null,ratings:array<int,int>}>
     */
    public function summaries(iterable $records): array
    {
        $summaries = [];
        $byType = [];

        foreach ($records as $record) {
            $summaries[$this->key($record)] = $this->toSummary(null);
            $byType[$record->getMorphClass()][$record->getKey()] = $record;
        }

        // No records: nothing to ask the counters.
        if ($summaries === []) {
            return [];
        }

        $rows = $this->counters()
            ->where(static function (Builder $query) use ($byType): void {
                foreach ($byType as $type => $records) {
                    $query->orWhere(static fn (Builder $query) => $query->where('reviewable_type', $type)->whereIn('reviewable_id', array_keys($records)));
                }
            })
            ->addSelect(['reviewable_type', 'reviewable_id'])
            ->groupBy('reviewable_type', 'reviewable_id')
            ->get();

        foreach ($rows as $row) {
            $record = $byType[$row->reviewable_type][$row->reviewable_id] ?? null;

            if ($record !== null) {
                $summaries[$this->key($record)] = $this->toSummary($row);
            }
        }

        return $summaries;
    }

    /**
     * schema.org markup of the reviewed thing with its AggregateRating and
     * the reviews shown on the page; null without published reviews, where
     * a rating would be invalid.
     *
     * @param  iterable<Review>  $reviews
     * @return array<string,mixed>|null
     */
    public function jsonLd(?Model $record, string $name, ?string $url, iterable $reviews = []): ?array
    {
        $summary = $this->summary($record);

        if ($summary['count'] === 0) {
            return null;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => $record === null ? $this->siteType() : ($this->models()[$record::class] ?? 'Thing'),
            'name' => $name,
        ];

        if ($url !== null) {
            $data['url'] = $url;
        }

        $data['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $summary['average'],
            'reviewCount' => $summary['count'],
            'bestRating' => 5,
            'worstRating' => 1,
        ];

        foreach ($reviews as $review) {
            $data['review'][] = array_filter([
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $review->author_name],
                'datePublished' => $review->posted_at?->toDateString(),
                'reviewBody' => $review->text,
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $review->rating, 'bestRating' => 5, 'worstRating' => 1],
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $data;
    }

    /**
     * The newest published reviews of a record (or of the site).
     *
     * @return Collection<int,Review>
     */
    public function latest(?Model $record = null, ?int $limit = null): Collection
    {
        return Review::query()->published()->about($record)
            ->orderByDesc('posted_at')->orderByDesc('id')
            ->limit($limit ?? config('atrium-reviews.per_page', 10))
            ->get();
    }

    /**
     * The counters summed up: of the current site once a request has said
     * which one it is, of all sites otherwise (like the reviews themselves).
     */
    private function counters(): Builder
    {
        $sums = 'coalesce(sum(count), 0) as count, coalesce(sum(rating_sum), 0) as rating_sum';

        foreach (range(1, 5) as $rating) {
            $sums .= ", coalesce(sum(rating_$rating), 0) as rating_$rating";
        }

        $sites = app(Sites::class);

        return (new Review)->getConnection()->table(ReviewCounters::TABLE)
            ->selectRaw($sums)
            ->when($sites->isResolved(), static fn (Builder $query) => $query->where('site_id', $sites->current()->id));
    }

    /**
     * @return array{count:int,average:float|null,ratings:array<int,int>}
     */
    private function toSummary(?object $row): array
    {
        $count = (int) ($row->count ?? 0);
        $ratings = [];

        foreach ([5, 4, 3, 2, 1] as $rating) {
            $ratings[$rating] = (int) ($row->{'rating_'.$rating} ?? 0);
        }

        return [
            'count' => $count,
            'average' => $count > 0 ? round($row->rating_sum / $count, 1) : null,
            'ratings' => $ratings,
        ];
    }

    private function siteType(): string
    {
        $type = config('atrium-reviews.site_type', 'Organization');

        return is_string($type) && preg_match(self::SCHEMA_TYPE, $type) ? $type : 'Organization';
    }

    private function alias(string $class): string
    {
        return is_subclass_of($class, Administrable::class)
            ? $class::getAdminRoutePrefix()
            : Str::snake(class_basename($class));
    }

    private function moduleLabel(string $class): string
    {
        return is_subclass_of($class, Administrable::class) ? $class::getAdminLabel() : class_basename($class);
    }
}

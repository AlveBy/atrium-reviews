<?php

declare(strict_types=1);

namespace Atrium\Reviews;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Reviews\Models\Review;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * What can be reviewed, the rating of a record and its schema.org markup.
 *
 * A review keeps the reviewed record as a type and an id, with no foreign
 * key and no Composer dependency on its module: a module that is removed
 * leaves its reviews in place, they just lose their record.
 */
final class Reviews
{
    /** A schema.org type name: letters only, it goes into the page as is. */
    private const SCHEMA_TYPE = '/^[A-Za-z]+$/';

    /**
     * The models that take reviews and their schema.org type; models that
     * are not installed are left out.
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

            if (is_string($class) && class_exists($class) && is_subclass_of($class, Model::class)) {
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
     * The record behind a stored type and id; null for the site, for a
     * deleted record and for a module that is no longer installed.
     */
    public function find(?string $type, ?int $id): ?Model
    {
        $class = $this->modelClass($type);

        if ($class === null || $id === null) {
            return null;
        }

        return $class::query()->find($id);
    }

    /**
     * "Products: Phone X" for the admin list; a missing record keeps its
     * type and id, so an editor sees what the review was about.
     */
    public function describe(?string $type, ?int $id): string
    {
        if ($type === null) {
            return __('atrium-reviews::reviews.site');
        }

        $record = $this->find($type, $id);

        if ($record === null) {
            return __('atrium-reviews::reviews.missing', ['type' => class_basename($type), 'id' => (string) $id]);
        }

        $title = $record instanceof Administrable && method_exists($record, 'getAdminTitle')
            ? (string) $record->getAdminTitle()
            : '#'.$record->getKey();

        return $this->moduleLabel($record::class).': '.$title;
    }

    /**
     * The choice of a module in the admin form, by the value a review keeps.
     *
     * @return array<string,string>
     */
    public function typeOptions(): array
    {
        $options = [];

        foreach (array_keys($this->models()) as $class) {
            $options[(new $class)->getMorphClass()] = $this->moduleLabel($class);
        }

        return $options;
    }

    /**
     * The average rating and the number of published reviews.
     *
     * @return array{count:int,average:float|null}
     */
    public function summary(?Model $record = null): array
    {
        $row = Review::query()->published()->about($record)
            ->toBase()
            ->selectRaw('count(*) as aggregate_count, avg(rating) as aggregate_average')
            ->first();

        $count = (int) ($row->aggregate_count ?? 0);

        return [
            'count' => $count,
            'average' => $count > 0 ? round((float) $row->aggregate_average, 1) : null,
        ];
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
            ->limit($limit ?? (int) config('atrium-reviews.per_page', 10))
            ->get();
    }

    private function siteType(): string
    {
        $type = config('atrium-reviews.site_type', 'Organization');

        return is_string($type) && preg_match(self::SCHEMA_TYPE, $type) ? $type : 'Organization';
    }

    /**
     * @return class-string<Model>|null
     */
    private function modelClass(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
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

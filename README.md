# Atrium reviews

Reviews for [Atrium](https://github.com/AlveBy/atrium): a 1–5 rating, the author's name, the text and the date, per site, with moderation in the admin. A review is about the site as a whole or about a record of any other module: a product, a page, a store. The average rating goes to the page as schema.org `AggregateRating`.

## Install

```bash
composer require alveby/atrium-reviews
php artisan atrium:module:install alveby/atrium-reviews
```

The second command runs the migrations and lists the module in `config/atrium.php` → `modules`. "Reviews" appears in the admin menu. `php artisan atrium:module:remove alveby/atrium-reviews` takes it out and asks whether to keep the reviews. A new review waits there as **New** until a moderator sets it to **Published** (shown on the site) or **Rejected**.

## Reviews of the site

`/reviews` (and `/de/reviews` in other content languages) lists the site's published reviews, newest first, with the average rating and the form. In the public theme ([`alveby/atrium-theme`](https://github.com/AlveBy/atrium-theme)) it is drawn in the theme's frame, otherwise in a plain page of its own.

## Reviews of a record

Name the modules whose records take reviews and the schema.org type of their rating in `config/atrium-reviews.php` (`php artisan vendor:publish --tag=atrium-reviews-config`):

```php
'models' => [
    App\Models\Product::class => 'Product',
    App\Models\Store::class => 'LocalBusiness',
],
```

The theme then shows the reviews and the form under each record page of these modules. In a template of your own, put the component where they belong:

```blade
<x-atrium-reviews::reviews :for="$product" :name="$product->name" />
```

Without `for` it shows the reviews of the site. For the numbers alone:

```php
$reviews = app(Atrium\Reviews\Reviews::class);

$reviews->summary($product);
// ['count' => 12, 'average' => 4.6, 'ratings' => [5 => 9, 4 => 2, 3 => 1, 2 => 0, 1 => 0]]

// A list of products with their stars: one query for all of them.
$summaries = $reviews->summaries($products);
$summaries[$reviews->key($product)]['average'];
```

## Counters

The numbers are not counted on the page: `review_counters` keeps, per record and site, the number of published reviews, their rating sum and how many of each rating. A counter is never moved by one. When a review is created, deleted, published or rejected, re-rated or moved to another record, its record is counted again from `reviews` once the change is committed, with the counter row locked (`SELECT … FOR UPDATE`). Reviews of one record sent at the same moment are all counted, and a review moved to another record updates both. Editing the text or the name does not touch the counter.

Reviews imported or changed in the database directly go past the model, so count everything again:

```bash
php artisan atrium:reviews:recount
```

## How the link to another module works

Reviews does not require the reviewed module's package and the reviewed module knows nothing about reviews. A review keeps the record as `reviewable_type` (the model class) and `reviewable_id`, with no foreign key, and reads it through Atrium's soft `morphTo` (`HasSoftRelations::softMorphTo()`):

- `$review->reviewable` is the product, the page, the store; for a review of the site it is `null`;
- when the reviewed module is removed (`atrium:module:remove`) or its package is gone, its reviews stay; `$review->reviewable` is `null` instead of failing, also with `with('reviewable')`, and the admin shows them as "Store #7 (removed)";
- the public form names the record by the module's key and id (`products:12`), never by a class, and only modules from `models` that are installed are accepted;
- in the admin a review can be moved to a record of any module of the panel.

## Spam

- A hidden field that people do not see and bots fill in: such a review is thanked for and dropped.
- At most 3 reviews from one address in 10 minutes (`rate_limit`).
- Nothing reaches the site before a moderator publishes it.

## Settings

| Key | Default | |
|---|---|---|
| `routes` | `true` | the `/reviews` page and the address the forms post to |
| `prefix` | `reviews` | the address of that page |
| `models` | `[]` | modules whose records take reviews, with their schema.org type |
| `site_type` | `Organization` | the schema.org type of the site's own rating |
| `per_page` | `10` | reviews on a page |
| `rate_limit` | 3 in 10 minutes | reviews from one address |
| `theme` | `true` | show the reviews under the theme's record pages |

The templates can be copied and changed with `php artisan vendor:publish --tag=atrium-reviews-views`. The interface speaks the 15 languages of the admin: English and Russian are written by hand, the others are machine translations.

## Tests

From the repository root:

```bash
vendor/bin/phpunit packages/reviews/tests
```

## Contributing

This repository is a read-only copy of `packages/reviews` in [AlveBy/atrium](https://github.com/AlveBy/atrium). Issues and pull requests go there.

## License

MIT

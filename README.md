# Atrium reviews

Reviews for [Atrium](https://github.com/AlveBy/atrium): a 1–5 rating, the author's name, the text and the date, per site, with moderation in the admin. A review is about the site as a whole or about a record of any other module: a product, a page, a store. The average rating goes to the page as schema.org `AggregateRating`.

## Install

```bash
composer require alveby/atrium-reviews
php artisan migrate
```

"Reviews" appears in the admin menu. A new review waits there as **New** until a moderator sets it to **Published** (shown on the site) or **Rejected**.

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
app(Atrium\Reviews\Reviews::class)->summary($product); // ['count' => 12, 'average' => 4.6]
```

## How the link to another module works

Reviews does not require the reviewed module's package and the reviewed module knows nothing about reviews. A review keeps the record as `reviewable_type` (the model's morph class) and `reviewable_id`, with no foreign key:

- the public form names the record by the module's key and id (`products:12`), never by a class, and only modules from `models` are accepted;
- when a module is removed or renamed, its reviews stay; the admin shows them as "Store #7 (removed)" and `$review->subject()` returns `null` instead of failing.

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

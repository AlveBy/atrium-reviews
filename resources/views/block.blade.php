{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
{{--
    The reviews of one record (or of the site when $record is null): the
    rating, the newest published reviews, the form and the schema.org
    markup. $items may be a paginator; without it the newest are shown.
--}}
@php
    $reviewsService = app(\Atrium\Reviews\Reviews::class);
    $record ??= null;
    $name ??= (string) (app(\Atrium\Admin\Support\Sites::class)->current()->name ?: config('app.name'));
    $url ??= null;
    $items ??= $reviewsService->latest($record);
    $summary = $reviewsService->summary($record);
    $jsonLd = $reviewsService->jsonLd($record, $name, $url, $items);
    $reviewErrors = (session('errors') ?? new \Illuminate\Support\ViewErrorBag)->getBag('reviews');
    $about = $record === null ? '' : $reviewsService->key($record);
    // Old input belongs to the form it came from when a page shows two.
    $mine = old('about') === null || old('about') === $about;
@endphp
<section class="reviews" id="reviews" aria-labelledby="reviews-title">
    <style>
        .reviews { margin-top: 2.5rem; max-width: 46rem; }
        .reviews-summary { font-size: 1.1rem; margin: 0 0 1rem; }
        .reviews-list { list-style: none; margin: 0 0 2rem; padding: 0; }
        .review { padding: 1rem 0; border-top: 1px solid var(--line, #e2e5ea); }
        .review-head { display: flex; flex-wrap: wrap; gap: .25rem 1rem; align-items: baseline; margin: 0 0 .4rem; }
        .review-stars, .reviews-summary .review-stars { letter-spacing: .1em; }
        .review time { color: var(--muted, #4f5662); font-size: .9rem; }
        .review-text { margin: 0; }
        .reviews-form { display: grid; gap: 1rem; padding: 1.25rem; background: var(--surface, #f3f4f6); border-radius: .6rem; }
        .reviews-form h3 { margin: 0; }
        .reviews-form label, .reviews-form legend { display: block; font-weight: 600; margin-bottom: .25rem; }
        .reviews-form input[type="text"], .reviews-form textarea { width: 100%; font: inherit; color: inherit; background: var(--bg, #fff); border: 1px solid var(--control, #6b7280); border-radius: .4rem; padding: .5rem .65rem; }
        .reviews-form fieldset { border: 0; margin: 0; padding: 0; }
        .reviews-rating { display: flex; flex-wrap: wrap; gap: .25rem 1rem; }
        .reviews-rating label { font-weight: 400; display: inline-flex; gap: .35rem; align-items: center; }
        .reviews-form button { justify-self: start; font: inherit; padding: .55rem 1.2rem; border: 0; border-radius: .4rem; background: var(--accent, #1d4ed8); color: var(--bg, #fff); cursor: pointer; }
        .reviews-error { color: #b42318; margin: .25rem 0 0; }
        :root[data-theme="dark"] .reviews-error { color: #ff9b8f; }
        .reviews-trap { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        .reviews-sent { padding: .75rem 1rem; border-left: 4px solid var(--accent, #1d4ed8); background: var(--surface, #f3f4f6); }
    </style>

    <h2 id="reviews-title">{{ __('atrium-reviews::reviews.title') }}</h2>

    @if ($summary['count'] > 0)
        <p class="reviews-summary">
            <span class="review-stars" aria-hidden="true">{{ str_repeat('★', (int) round($summary['average'])) }}{{ str_repeat('☆', 5 - (int) round($summary['average'])) }}</span>
            {{ __('atrium-reviews::reviews.average', ['rating' => number_format((float) $summary['average'], 1, '.', '')]) }}
            · {{ trans_choice('atrium-reviews::reviews.count', $summary['count'], ['count' => $summary['count']]) }}
        </p>
    @endif

    @if (count($items) > 0)
        <ul class="reviews-list">
            @foreach ($items as $review)
                <li class="review">
                    <p class="review-head">
                        <strong>{{ $review->author_name }}</strong>
                        <span class="review-stars" role="img" aria-label="{{ __('atrium-reviews::reviews.rated', ['rating' => $review->rating]) }}">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                        @if ($review->posted_at)
                            <time datetime="{{ $review->posted_at->toAtomString() }}">{{ $review->posted_at->isoFormat('LL') }}</time>
                        @endif
                    </p>
                    <p class="review-text">{!! nl2br(e($review->text)) !!}</p>
                </li>
            @endforeach
        </ul>
    @else
        <p class="empty">{{ __('atrium-reviews::reviews.empty') }}</p>
    @endif

    @if ($items instanceof \Illuminate\Contracts\Pagination\Paginator && view()->exists('atrium-theme::partials.pagination'))
        @include('atrium-theme::partials.pagination', ['paginator' => $items])
    @endif

    @if (session('atrium-reviews.sent') && $mine)
        <p class="reviews-sent" role="status">{{ __('atrium-reviews::reviews.form.sent') }}</p>
    @endif

    <form class="reviews-form" method="post" action="{{ app(\Atrium\Admin\Support\ContentLanguages::class)->route('atrium.reviews.store') }}">
        @csrf
        <h3>{{ __('atrium-reviews::reviews.form.title') }}</h3>
        <input type="hidden" name="about" value="{{ $about }}">

        <div class="reviews-trap" aria-hidden="true">
            <label for="reviews-website">{{ __('atrium-reviews::reviews.form.trap') }}</label>
            <input type="text" id="reviews-website" name="{{ \Atrium\Reviews\Http\Controllers\ReviewController::HONEYPOT }}" value="" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="reviews-name">{{ __('atrium-reviews::reviews.form.name') }}</label>
            <input type="text" id="reviews-name" name="author_name" value="{{ $mine ? old('author_name') : '' }}" maxlength="255" required autocomplete="name">
            @if ($mine && $reviewErrors->has('author_name'))
                <p class="reviews-error">{{ $reviewErrors->first('author_name') }}</p>
            @endif
        </div>

        <fieldset>
            <legend>{{ __('atrium-reviews::reviews.form.rating') }}</legend>
            <div class="reviews-rating">
                @foreach ([5, 4, 3, 2, 1] as $rating)
                    <label><input type="radio" name="rating" value="{{ $rating }}" required @checked($mine && (string) old('rating') === (string) $rating)> {{ __('atrium-reviews::reviews.rated', ['rating' => $rating]) }}</label>
                @endforeach
            </div>
            @if ($mine && $reviewErrors->has('rating'))
                <p class="reviews-error">{{ $reviewErrors->first('rating') }}</p>
            @endif
        </fieldset>

        <div>
            <label for="reviews-text">{{ __('atrium-reviews::reviews.form.text') }}</label>
            <textarea id="reviews-text" name="text" rows="5" maxlength="5000" required>{{ $mine ? old('text') : '' }}</textarea>
            @if ($mine && ($reviewErrors->has('text') || $reviewErrors->has('about')))
                <p class="reviews-error">{{ $reviewErrors->first('text') ?: $reviewErrors->first('about') }}</p>
            @endif
        </div>

        <p class="reviews-note">{{ __('atrium-reviews::reviews.form.moderation') }}</p>
        <button type="submit">{{ __('atrium-reviews::reviews.form.submit') }}</button>
    </form>

    @if ($jsonLd)
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif
</section>

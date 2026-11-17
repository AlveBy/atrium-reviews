<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews\Http\Controllers;

use Atrium\Admin\Captcha\CaptchaManager;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Reviews\Models\Review;
use Atrium\Reviews\Reviews;
use Atrium\Reviews\ReviewStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * The public side: the reviews of the site and the form every review is
 * sent with, about the site or about a record.
 */
final class ReviewController extends Controller
{
    /** The field people do not see and bots fill in. */
    public const HONEYPOT = 'website';

    public function __construct(private readonly Reviews $reviews) {}

    public function index(Request $request, ContentLanguages $languages, Sites $sites): View
    {
        $items = Review::query()->published()->about(null)
            ->orderByDesc('posted_at')->orderByDesc('id')
            ->paginate((int) config('atrium-reviews.per_page', 10))
            ->withQueryString();

        $switch = [];

        foreach ($languages->codes() as $code) {
            $switch[$code] = $languages->route('atrium.reviews.index', [], $code);
        }

        return view('atrium-reviews::page', [
            'items' => $items,
            'siteTitle' => (string) ($sites->current()->name ?: config('app.name')),
            'canonical' => $languages->route('atrium.reviews.index'),
            'alternates' => $request->query('page') ? [] : $switch,
            'switch' => $switch,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // A bot that filled the hidden field is told it worked and nothing
        // is kept: it learns nothing to get around.
        if ($request->filled(self::HONEYPOT)) {
            return $this->sent();
        }

        $validator = Validator::make($request->all(), [
            'author_name' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'text' => ['required', 'string', 'max:5000'],
            'about' => ['nullable', 'string', 'max:255'],
            // The site's captcha (atrium.captcha.driver), when one is on.
            ...(config('atrium-reviews.captcha', true) ? app(CaptchaManager::class)->rules() : []),
        ], [], [
            'author_name' => __('atrium-reviews::reviews.form.name'),
            'rating' => __('atrium-reviews::reviews.form.rating'),
            'text' => __('atrium-reviews::reviews.form.text'),
        ]);

        $record = null;

        $validator->after(function ($validator) use ($request, &$record): void {
            $about = $request->input('about', '');

            // Something that is not text has been refused by the rules above.
            if (is_string($about) && $about !== '' && ($record = $this->reviews->resolve($about)) === null) {
                $validator->errors()->add('about', __('atrium-reviews::reviews.form.unknown'));
            }
        });

        if ($validator->fails()) {
            return $this->back()->withErrors($validator, 'reviews')->withInput($request->except([self::HONEYPOT, ...CaptchaManager::INPUTS]));
        }

        $limiter = 'atrium-reviews:'.$request->ip();

        // Fewer than one attempt a window still lets the first one in: the
        // limiter forgets a window nobody has hit yet.
        if (RateLimiter::tooManyAttempts($limiter, (int) config('atrium-reviews.rate_limit.attempts', 3))) {
            return $this->back()
                ->withErrors(['text' => __('atrium-reviews::reviews.form.too_many', ['minutes' => ceil(RateLimiter::availableIn($limiter) / 60)])], 'reviews')
                ->withInput($request->except([self::HONEYPOT, ...CaptchaManager::INPUTS]));
        }

        RateLimiter::hit($limiter, 60 * max(1, (int) config('atrium-reviews.rate_limit.minutes', 10)));

        $review = new Review([
            'author_name' => trim((string) $request->input('author_name')),
            'rating' => $request->input('rating'),
            'text' => trim((string) $request->input('text')),
            'status' => ReviewStatus::New,
        ]);

        if ($record !== null) {
            $review->reviewable_type = $record->getMorphClass();
            $review->reviewable_id = $record->getKey();
        }

        $review->save();

        return $this->sent();
    }

    private function sent(): RedirectResponse
    {
        return $this->back()->with('atrium-reviews.sent', true);
    }

    private function back(): RedirectResponse
    {
        $back = redirect()->back(302, [], route('atrium.reviews.index'));

        return $back->setTargetUrl(strtok($back->getTargetUrl(), '#').'#reviews');
    }
}

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    /*
    | The public page with the reviews of the site and the address the forms
    | post to: /reviews (and /de/reviews in other languages).
    */
    'routes' => true,

    'prefix' => 'reviews',

    /*
    | Records that can be reviewed, with the schema.org type their rating is
    | marked up as. Only these get the form on their page; a model that is
    | not installed is skipped, so the list may name modules the site does
    | not have yet.
    |
    |     App\Models\Product::class => 'Product',
    |     App\Models\Store::class => 'LocalBusiness',
    */
    'models' => [],

    /* The schema.org type of the reviews about the site as a whole. */
    'site_type' => 'Organization',

    /* Reviews on a page. */
    'per_page' => 10,

    /* Reviews a visitor may send from one address in the given minutes. */
    'rate_limit' => [
        'attempts' => 3,
        'minutes' => 10,
    ],

    /*
    | Ask the site's captcha in the form (atrium.captcha.driver in
    | config/atrium.php: image, recaptcha or none). False: never here.
    */
    'captcha' => true,

    /*
    | Show the reviews and the form under the record pages of the public
    | theme (alveby/atrium-theme) for the models above.
    */
    'theme' => true,
];

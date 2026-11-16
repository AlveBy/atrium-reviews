<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Reviews',
    'site' => 'The site',
    'missing' => ':type #:id (removed)',
    'status' => [
        'new' => 'New',
        'published' => 'Published',
        'rejected' => 'Rejected',
    ],
    'fields' => [
        'status' => 'Status',
        'rating' => 'Rating',
        'author_name' => 'Author',
        'posted_at' => 'Date',
        'text' => 'Text',
        'subject_type' => 'About a record of',
        'subject_id' => 'Record ID',
        'subject' => 'About',
    ],
    'title' => 'Reviews',
    'description' => 'Reviews of :site',
    'average' => ':rating out of 5',
    'count' => '{1} :count review|[2,*] :count reviews',
    'rated' => ':rating of 5',
    'empty' => 'No reviews yet. Be the first.',
    'form' => [
        'title' => 'Leave a review',
        'name' => 'Your name',
        'rating' => 'Rating',
        'text' => 'Your review',
        'submit' => 'Send',
        'sent' => 'Thank you! Your review will appear after moderation.',
        'moderation' => 'Reviews appear on the site after a moderator checks them.',
        'too_many' => 'Too many reviews from your address. Try again in :minutes min.',
        'unknown' => 'This page no longer takes reviews.',
        'trap' => 'Leave this field empty',
    ],
];
